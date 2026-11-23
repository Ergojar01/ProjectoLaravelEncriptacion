<?php

namespace App\Http\Controllers;

use App\Models\PasswordReset;
use App\Services\Audit;
use App\Services\Credentials;
use App\Services\LoginThrottle;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El usuario define SU contraseña con un enlace de un solo uso y con vencimiento.
 * El administrador solo autoriza: nunca conoce la contraseña.
 */
class PasswordResetController extends Controller
{
    public function show(string $token)
    {
        $reset = PasswordReset::findValid($token);

        if (! $reset) {
            Audit::log('password_reset', Audit::FAILURE, null, null, null, 'Enlace inválido, usado o vencido');

            return response()->view('auth.reset', ['valid' => false, 'token' => null, 'seconds' => 0], 410);
        }

        return view('auth.reset', [
            'valid'   => true,
            'token'   => $token,
            'seconds' => max(0, $reset->expires_at->getTimestamp() - now()->getTimestamp()),
            'total'   => config('security.reset.ttl_minutes') * 60,
        ]);
    }

    public function update(Request $request, string $token)
    {
        $reset = PasswordReset::findValid($token);

        if (! $reset) {
            Audit::log('password_reset', Audit::FAILURE, null, null, null, 'Enlace inválido, usado o vencido');

            return response()->view('auth.reset', ['valid' => false, 'token' => null, 'seconds' => 0], 410);
        }

        $user = $reset->user;

        $request->validate([
            'password' => ['required', 'confirmed', ...PasswordPolicy::rules($user->email)],
        ], PasswordPolicy::messages());

        $done = DB::transaction(function () use ($reset, $user, $request) {
            // Un solo uso: solo gana quien marque used_at primero
            $claimed = PasswordReset::where('id', $reset->id)->whereNull('used_at')->update(['used_at' => now()]);
            if ($claimed !== 1) {
                return false;
            }

            Credentials::apply($user, $request->input('password'));
            $user->forceFill([
                'must_change_password'     => false,
                'temp_password_expires_at' => null,
                'temp_password_used_at'    => null,
                'temp_requested_at'        => null,
                'remember_token'           => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();   // invalida todas las sesiones
            LoginThrottle::release($user->email);                           // levanta bloqueos

            return true;
        });

        if (! $done) {
            return response()->view('auth.reset', ['valid' => false, 'token' => null, 'seconds' => 0], 410);
        }

        Audit::log('password_reset', Audit::SUCCESS, $user, $user->email, null, 'Contraseña definida por el propio usuario con enlace de un solo uso');

        return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
