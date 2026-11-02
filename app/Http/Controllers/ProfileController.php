<?php

namespace App\Http\Controllers;

use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Cambia la contraseña respetando el esquema criptográfico propio:
     * sal nueva de 256 bits + PBKDF2-SHA512 + XOR + sello HMAC.
     * No usa Hash::make (bcrypt) para no romper el login.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user   = $request->user();
        $forced = (bool) $user->must_change_password;   // entró con clave temporal
        $key    = 'pwchange:' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'current_password' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $rules = ['password' => ['required', 'confirmed', PasswordPolicy::rule()]];
        if (! $forced) {
            $rules['current_password'] = ['required', 'string'];
            $rules['password'][]       = 'different:current_password';
        }
        $data = $request->validate($rules, PasswordPolicy::messages());

        if ($forced) {
            if (CredentialHasher::check($user->email, $data['password'], $user->salt, $user->password)) {
                return back()->withErrors(['password' => 'La nueva contraseña debe ser distinta de la temporal.']);
            }
        } elseif (! CredentialHasher::check($user->email, $data['current_password'], $user->salt, $user->password)) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $salt = CredentialHasher::newSalt();

        $user->forceFill([
            'salt'                     => $salt,
            'password'                 => CredentialHasher::make($user->email, $data['password'], $salt),
            'crypto_version'           => config('crypto.version'),
            'must_change_password'     => false,
            'temp_password_expires_at' => null,
            'temp_password_used_at'    => null,
        ])->save();

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return $forced
            ? redirect()->route('dashboard')->with('status', 'Contraseña actualizada. Ya puedes usar el sistema.')
            : back()->with('status', 'Contraseña actualizada. Se generó una sal nueva y se volvió a sellar.');
    }
}
