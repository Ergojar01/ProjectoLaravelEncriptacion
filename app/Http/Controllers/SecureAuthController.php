<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SecureAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordPolicy::rule()],
        ], PasswordPolicy::messages());

        $salt = CredentialHasher::newSalt();

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            'salt'           => $salt,
            'password'       => CredentialHasher::make($data['email'], $data['password'], $salt),
            'crypto_version' => config('crypto.version'),
            'role_level'     => 300,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('mask');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], PasswordPolicy::messages());

        $key  = 'login:' . $credentials['email'];   // contador de fallos
        $lock = 'lock:'  . $credentials['email'];   // bloqueo activo

        if (RateLimiter::tooManyAttempts($lock, 1)) {
            $s = RateLimiter::availableIn($lock);

            return back()
                ->withErrors(['email' => 'Cuenta bloqueada por intentos fallidos. Intenta de nuevo en ' . ceil($s / 60) . ' min (' . $s . ' s).'])
                ->with('lock_seconds', $s)
                ->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        $valid = $user
            ? CredentialHasher::check($credentials['email'], $credentials['password'], $user->salt, $user->password)
            : CredentialHasher::burn($credentials['email'], $credentials['password']);

        if (! $valid) {
            RateLimiter::hit($key, 300);

            if (RateLimiter::attempts($key) >= 3) {
                RateLimiter::hit($lock, 300);   // bloqueo de 5 min desde el 3.er fallo
                RateLimiter::clear($key);

                return back()
                    ->withErrors(['email' => 'Fallaste 3 veces. Cuenta bloqueada durante 5 minutos.'])
                    ->with('lock_seconds', 300)
                    ->onlyInput('email');
            }

            $left = 3 - RateLimiter::attempts($key);

            return back()
                ->withErrors(['email' => "Credenciales incorrectas. Te quedan {$left} intento(s)."])
                ->onlyInput('email');
        }

        // Clave temporal: sirve una sola vez y solo hasta que venza
        if ($user->must_change_password) {
            if ($user->temp_password_used_at
                || ($user->temp_password_expires_at && now()->greaterThan($user->temp_password_expires_at))) {
                return back()
                    ->withErrors(['email' => 'La clave temporal ya fue usada o venció. Pide una nueva al administrador.'])
                    ->onlyInput('email');
            }

            $user->forceFill(['temp_password_used_at' => now()])->save();
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('mask');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Una persona 300 o 499 pide una clave temporal: queda marcada para el administrador.
     * La respuesta es siempre la misma, exista o no el correo.
     */
    public function requestTemp(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], PasswordPolicy::messages());

        $user = User::where('email', $data['email'])->first();

        if ($user && (int) $user->role_level < 500) {
            $user->forceFill(['temp_requested_at' => now()])->save();
        }

        return back()->with('status', 'Si el correo pertenece a una cuenta de nivel 300 o 499, el administrador ya vio tu solicitud. Él te entregará una clave temporal.');
    }
}
