<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\Credentials;
use App\Services\LoginThrottle;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SecureAuthController extends Controller
{
    public function login(Request $request)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ], PasswordPolicy::messages());

        $email = $credentials['email'];
        $ip = (string) $request->ip();

        try {
            return Cache::lock('login-account:'.hash('sha256', $email), 60)->block(5,
                fn () => Cache::lock('login-ip:'.hash('sha256', $ip), 60)->block(5,
                    fn () => $this->authenticate($request, $email, $ip, $credentials['password'])));
        } catch (LockTimeoutException) {
            return $this->locked(1);
        }
    }

    private function authenticate(Request $request, string $email, string $ip, #[\SensitiveParameter] string $password)
    {
        $user = User::where('email', $email)->first();

        // 1. ¿Bloqueo activo? (cuenta + IP, cuenta, o IP). Se consulta en MySQL.
        if (($seconds = LoginThrottle::remaining($email, $ip)) > 0) {
            Audit::log('login_blocked', Audit::DENIED, $user, $email, null, "Intento durante bloqueo activo ({$seconds} s restantes)");

            return $this->locked($seconds);
        }

        // 2. Verificar con Hash::check (Argon2id) o con el esquema anterior (v1)
        $valid = $user ? Credentials::check($user, $password) : Credentials::burn($password);

        // Cuenta desactivada: misma respuesta que unas credenciales incorrectas
        $inactive = $valid && ! $user->is_active;
        if ($inactive) {
            $valid = false;
        }

        if (! $valid) {
            $lock = LoginThrottle::fail($email, $ip, $user);
            Audit::log('login', Audit::FAILURE, $user, $email, null, $inactive ? 'Cuenta desactivada' : 'Credenciales incorrectas');

            if ($lock > 0) {
                Audit::log('account_locked', Audit::DENIED, $user, $email, null, "Bloqueo temporal de {$lock} s por intentos fallidos");

                return $this->locked($lock);
            }

            return back()
                ->withErrors(['email' => 'Credenciales incorrectas. Te quedan '.LoginThrottle::attemptsLeft($email, $ip).' intento(s).'])
                ->onlyInput('email');
        }

        // 3. Correcto y sin bloqueo: reinicia el contador, migra a Argon2id si hace falta
        LoginThrottle::succeed($email, $ip);

        $upgraded = false;
        if (Credentials::needsUpgrade($user)) {
            $previousHash = $user->password;
            Credentials::apply($user, $password);
            if (! User::whereKey($user->id)->where('password', $previousHash)->where('is_active', true)->update($user->getDirty())) {
                Audit::log('login', Audit::FAILURE, $user, details: 'La credencial cambió durante la solicitud');

                return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
            }
            $upgraded = true;
        }

        Auth::login($user);                       // sin "recordarme": la sesión expira por inactividad
        $request->session()->regenerate(true);
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), Auth::guard()->hashPasswordForCookie($user->password));
        Audit::log('login', Audit::SUCCESS, $user, null, null, $upgraded ? 'Hash actualizado a Argon2id' : null);

        return redirect()->route('mask');
    }

    public function logout(Request $request)
    {
        Audit::log('logout', Audit::SUCCESS, Auth::user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Una persona 300 o 499 pide que el administrador autorice su restablecimiento.
     * La respuesta es siempre la misma, exista o no el correo.
     */
    public function requestReset(Request $request)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], PasswordPolicy::messages());

        $user = User::where('email', $data['email'])->first();
        $eligible = $user && $user->is_active && (int) $user->role_level < 500;

        if ($eligible) {
            $user->forceFill(['temp_requested_at' => now()])->save();
        }

        Audit::log('temp_requested', $eligible ? Audit::SUCCESS : Audit::FAILURE, $eligible ? $user : null, $data['email'], null,
            $eligible ? 'Solicitud enviada al administrador' : 'Correo inexistente, desactivado o nivel no elegible');

        return back()->with('status', 'Si el correo pertenece a una cuenta de nivel 300 o 499, el administrador ya vio tu solicitud. Cuando la autorice recibirás un enlace de un solo uso para crear tu contraseña nueva.');
    }

    private function locked(int $seconds)
    {
        return back()
            ->withErrors(['email' => 'Demasiados intentos fallidos. Intenta de nuevo en '.ceil($seconds / 60).' min ('.$seconds.' s).'])
            ->with('lock_seconds', $seconds)
            ->onlyInput('email');
    }
}
