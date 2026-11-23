<?php

namespace App\Http\Controllers;

use App\Models\PasswordReset;
use App\Services\Audit;
use App\Services\Credentials;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Cambia la contraseña: exige la actual + nueva + confirmación (mínimo 12),
     * guarda un hash Argon2id nuevo e invalida todas las demás sesiones.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $key = 'pwchange:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Audit::log('password_changed', Audit::DENIED, $user, null, null, 'Cambio de clave bloqueado por demasiados intentos');

            return back()->withErrors(['current_password' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos."]);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'password' => ['required', 'confirmed', 'different:current_password', ...PasswordPolicy::rules($user->email)],
        ], PasswordPolicy::messages());

        if (! Credentials::check($user, $data['current_password'])) {
            RateLimiter::hit($key, 300);
            Audit::log('password_changed', Audit::FAILURE, $user, null, null, 'Contraseña actual incorrecta');

            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $oldHash = $user->password;
        Credentials::apply($user, $data['password']);
        $user->forceFill(['remember_token' => Str::random(60)]);
        $updated = DB::transaction(function () use ($user, $oldHash) {
            $updated = $user->newQuery()->whereKey($user->id)->where('password', $oldHash)
                ->where('is_active', true)->update($user->getDirty());
            if ($updated) {
                PasswordReset::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->update(['expires_at' => now()]);
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            return $updated;
        });
        if (! $updated) {
            Audit::log('password_changed', Audit::FAILURE, $user, details: 'La credencial cambió durante la solicitud');

            return back()->withErrors(['current_password' => 'La credencial cambió. Vuelve a iniciar sesión.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate(true);
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), Auth::guard()->hashPasswordForCookie($user->password));

        Audit::log('password_changed', Audit::SUCCESS, $user, null, null, 'Cambio voluntario de contraseña; otras sesiones invalidadas');

        return back()->with('status', 'Contraseña actualizada. Se guardó un hash Argon2id nuevo y se cerraron tus otras sesiones.');
    }
}
