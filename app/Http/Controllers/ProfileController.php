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
        $user = $request->user();
        $key  = 'pwchange:' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'current_password' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', 'different:current_password', PasswordPolicy::rule()],
        ], PasswordPolicy::messages());

        if (! CredentialHasher::check($user->email, $data['current_password'], $user->salt, $user->password)) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $salt = CredentialHasher::newSalt();

        $user->forceFill([
            'salt'           => $salt,
            'password'       => CredentialHasher::make($user->email, $data['password'], $salt),
            'crypto_version' => config('crypto.version'),
        ])->save();

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return back()->with('status', 'Contraseña actualizada. Se generó una sal nueva y se volvió a sellar.');
    }
}
