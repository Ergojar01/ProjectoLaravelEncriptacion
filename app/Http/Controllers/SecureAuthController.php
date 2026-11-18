<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SecureAuthController extends Controller
{
    public function register(Request $request)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicy::rules(),
        ], PasswordPolicy::messages());

        $user = User::create([
            'name'           => $data['name'],
            'email'          => $data['email'],
            ...CredentialHasher::attributes($data['password']),
            'role_level'     => 300,
        ]);

        Auth::login($user);
        $this->startSession($request, $user);

        return redirect()->route('mask');
    }

    public function login(Request $request)
    {
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

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

        $password = $credentials['password'];
        $temporary = $user?->must_change_password;

        if ($user) {
            $valid = $temporary
                ? CredentialHasher::checkTemporary($user, $password)
                : CredentialHasher::check($user, $password);
        } else {
            // Approximate the work of checking a credential for an existing account.
            Hash::make($password);
            $valid = false;
        }

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

        if ($temporary) {
            $claimed = $user->credentialQuery()
                ->whereNull('temp_password_used_at')
                ->where('temp_password_expires_at', '>', now())
                ->update(['temp_password_used_at' => now()]);

            if (! $claimed) {
                return back()
                    ->withErrors(['email' => 'La clave temporal ya fue usada o venció. Pide una nueva al administrador.'])
                    ->onlyInput('email');
            }
        } elseif ($user->crypto_version === 1 || Hash::needsRehash($user->password)) {
            $attributes = CredentialHasher::attributes($password);

            if (! $user->credentialQuery()->update($attributes)) {
                return back()->withErrors(['email' => 'La credencial cambió. Vuelve a iniciar sesión.'])->onlyInput('email');
            }

            $user->forceFill($attributes);
        }

        RateLimiter::clear($key);
        Auth::login($user, ! $temporary && $request->boolean('remember'));
        $this->startSession($request, $user);

        if ($temporary) {
            $request->session()->put('temporary_password_proof', CredentialHasher::temporarySessionProof($user));
        }

        return redirect()->route('mask');
    }

    private function startSession(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            Auth::guard()->hashPasswordForCookie($user->password),
        );
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
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

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
