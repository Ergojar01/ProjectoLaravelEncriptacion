<?php

namespace App\Http\Middleware;

use App\Services\CredentialHasher;
use App\Services\LoginAttempts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien entra con una clave temporal solo puede cambiar su contraseña,
 * y dispone de 5 minutos. Si no lo hace, se cierra la sesión y se bloquea 5 minutos.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $proof = $request->session()->get('temporary_password_proof');
        if (! is_string($proof) || ! hash_equals(CredentialHasher::temporarySessionProof($user), $proof)) {
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $deadline = $user->temp_password_used_at?->copy()->addMinutes(5);

        if (! $deadline || now()->greaterThan($deadline)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            app(LoginAttempts::class)->lock($user, 300);
            $request->session()->flash('error', 'No cambiaste la contraseña a tiempo. Cuenta bloqueada durante 5 minutos.');
            $request->session()->flash('lock_seconds', 300);

            // Sale del iframe del portal y vuelve al login
            return response('<!DOCTYPE html><script>window.top.location.replace('.Js::from(route('login', absolute: false)).')</script>');
        }

        if (! $request->routeIs('profile.edit', 'profile.password', 'logout')) {
            return redirect()->route('profile.edit');
        }

        return $next($request);
    }
}
