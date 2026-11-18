<?php

namespace App\Http\Controllers;

use App\Services\CredentialHasher;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $forced = $user->must_change_password;
        $key = 'pwchange:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'current_password' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
            ]);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => PasswordPolicy::rules(),
        ], PasswordPolicy::messages());

        $valid = $forced
            ? CredentialHasher::checkTemporary($user, $data['current_password'])
            : CredentialHasher::check($user, $data['current_password']);

        if (! $valid) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }

        if (hash_equals($data['current_password'], $data['password'])) {
            $message = $forced
                ? 'La nueva contraseña debe ser distinta de la clave temporal.'
                : 'La nueva contraseña debe ser distinta de la actual.';

            throw ValidationException::withMessages(['password' => $message]);
        }

        if ($forced && $user->temp_password_hash !== null && CredentialHasher::check($user, $data['password'])) {
            throw ValidationException::withMessages(['password' => 'La nueva contraseña debe ser distinta de la anterior a la recuperación.']);
        }

        $attributes = [
            ...CredentialHasher::attributes($data['password']),
            'remember_token' => Str::random(60),
            'must_change_password' => false,
            'temp_password_hash' => null,
            'temp_password_expires_at' => null,
            'temp_password_used_at' => null,
            'temp_requested_at' => null,
        ];

        $query = $user->credentialQuery();
        if ($forced) {
            $query->where('temp_password_used_at', '>', now()->subMinutes(5));
        }

        if (! $query->update($attributes)) {
            throw ValidationException::withMessages(['current_password' => 'La credencial cambió o venció. Vuelve a iniciar sesión.']);
        }

        $user->forceFill($attributes);
        $user->revokeDatabaseSessions();
        RateLimiter::clear($key);
        $request->session()->forget(['temporary_password_proof', 'auth.password_confirmed_at']);
        $request->session()->regenerate(true);

        return $forced
            ? redirect()->route('dashboard')->with('status', 'Contraseña actualizada. Ya puedes usar el sistema.')
            : back()->with('status', 'Contraseña actualizada.');
    }
}
