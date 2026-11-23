<?php

namespace App\Services;

use App\Models\AccountLock;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Control de intentos de inicio de sesión, guardado en MySQL.
 *
 *  - Por cuenta + IP : 3 fallos en 3 min  → bloqueo de 3 min para esa pareja.
 *  - Por cuenta      : 10 fallos en 3 min desde cualquier IP → bloqueo de la cuenta.
 *  - Por origen (IP) : 20 fallos en 3 min contra cualquier cuenta → bloqueo de la IP.
 *
 * Así un atacante no puede bloquear a un usuario legítimo con solo 3 intentos desde
 * otra IP, ni eludir el bloqueo abriendo sesiones nuevas (el estado vive en la BD).
 */
final class LoginThrottle
{
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    /** Segundos de bloqueo activo para esta cuenta + IP (0 = libre). */
    public static function remaining(string $email, string $ip): int
    {
        $email = self::normalize($email);

        $until = AccountLock::whereNull('released_at')
            ->where('locked_until', '>', now())
            ->where(function ($q) use ($email, $ip) {
                $q->where(fn ($w) => $w->where('scope', 'pair')->where('identifier', $email)->where('ip_address', $ip))
                  ->orWhere(fn ($w) => $w->where('scope', 'account')->where('identifier', $email))
                  ->orWhere(fn ($w) => $w->where('scope', 'ip')->where('ip_address', $ip));
            })
            ->max('locked_until');

        if (! $until) {
            return 0;
        }

        return max(0, \Illuminate\Support\Carbon::parse($until, 'UTC')->getTimestamp() - now()->getTimestamp());
    }

    /**
     * Registra un fallo. Devuelve los segundos de bloqueo si este fallo lo activó (0 si no).
     */
    public static function fail(string $email, string $ip, ?User $user = null): int
    {
        $email  = self::normalize($email);
        $cfg    = config('security.login');
        $window = now()->subSeconds($cfg['window_seconds']);

        LoginAttempt::create(['user_id' => $user?->id, 'identifier' => $email, 'ip_address' => $ip, 'created_at' => now()]);

        $pair = fn () => LoginAttempt::where('identifier', $email)->where('ip_address', $ip)->whereNull('cleared_at')->where('created_at', '>=', $window);

        if ($pair()->count() >= $cfg['max_attempts']) {
            $pair()->update(['cleared_at' => now()]);

            return self::lock('pair', $email, $ip, $user, 'Intentos fallidos desde un mismo origen');
        }

        $account = fn () => LoginAttempt::where('identifier', $email)->whereNull('cleared_at')->where('created_at', '>=', $window);
        if ($account()->count() >= $cfg['account_max']) {
            $account()->update(['cleared_at' => now()]);

            return self::lock('account', $email, null, $user, 'Ataque distribuido contra la cuenta');
        }

        $origin = fn () => LoginAttempt::where('ip_address', $ip)->whereNull('cleared_at')->where('created_at', '>=', $window);
        if ($origin()->count() >= $cfg['ip_max']) {
            $origin()->update(['cleared_at' => now()]);

            return self::lock('ip', null, $ip, null, 'Demasiados fallos desde un mismo origen');
        }

        return 0;
    }

    /** Intentos que le quedan a esta cuenta + IP antes del bloqueo. */
    public static function attemptsLeft(string $email, string $ip): int
    {
        $cfg  = config('security.login');
        $used = LoginAttempt::where('identifier', self::normalize($email))->where('ip_address', $ip)
            ->whereNull('cleared_at')->where('created_at', '>=', now()->subSeconds($cfg['window_seconds']))->count();

        return max(0, $cfg['max_attempts'] - $used);
    }

    /** Autenticación correcta (sin bloqueo activo): reinicia el contador de esa pareja. */
    public static function succeed(string $email, string $ip): void
    {
        LoginAttempt::where('identifier', self::normalize($email))->where('ip_address', $ip)
            ->whereNull('cleared_at')->update(['cleared_at' => now()]);
    }

    /** Levanta bloqueos y contadores de una cuenta (restablecimiento autorizado). */
    public static function release(string $email): void
    {
        $email = self::normalize($email);

        AccountLock::whereNull('released_at')->where('identifier', $email)->update(['released_at' => now()]);
        LoginAttempt::where('identifier', $email)->whereNull('cleared_at')->update(['cleared_at' => now()]);
    }

    private static function lock(string $scope, ?string $email, ?string $ip, ?User $user, string $reason): int
    {
        $seconds = (int) config('security.login.lock_seconds');

        AccountLock::create([
            'user_id'      => $user?->id,
            'scope'        => $scope,
            'identifier'   => $email,
            'ip_address'   => $ip,
            'reason'       => $reason,
            'locked_until' => now()->addSeconds($seconds),
            'created_at'   => now(),
        ]);

        return $seconds;
    }
}
