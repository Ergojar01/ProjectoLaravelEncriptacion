<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registro de eventos de seguridad.
 *
 * Regla de oro: aquí NUNCA se pasan contraseñas, claves temporales, tokens
 * ni cookies. Solo identificadores, tipo de evento y resultado.
 */
final class Audit
{
    public const SUCCESS = 'success';
    public const FAILURE = 'failure';
    public const DENIED  = 'denied';

    /** Eventos conocidos y su etiqueta en español. */
    public const EVENTS = [
        'login'            => 'Inicio de sesión',
        'login_blocked'    => 'Intento durante bloqueo',
        'account_locked'   => 'Cuenta bloqueada',
        'logout'           => 'Cierre de sesión',
        'password_changed' => 'Cambio de contraseña',
        'temp_requested'   => 'Solicitud de clave temporal',
        'temp_generated'   => 'Clave temporal generada',
        'temp_used'        => 'Clave temporal usada',
        'temp_expired'     => 'Clave temporal vencida',
        'user_registered'  => 'Registro de cuenta',
        'user_updated'     => 'Datos de usuario modificados',
        'user_deactivated' => 'Cuenta desactivada',
        'user_activated'   => 'Cuenta reactivada',
        'reset_authorized' => 'Restablecimiento autorizado',
        'password_reset'   => 'Contraseña restablecida (enlace)',
        'hash_viewed'      => 'Consulta de hash almacenado',
        'user_created'     => 'Usuario creado',
        'user_deleted'     => 'Usuario eliminado',
        'level_changed'    => 'Cambio de nivel',
        'access_denied'    => 'Acceso no autorizado',
    ];

    public static function log(
        string $event,
        string $result = self::SUCCESS,
        ?User $user = null,
        ?string $identifier = null,
        ?User $target = null,
        ?string $details = null,
    ): void {
        try {
            $request = request();

            AuditLog::create([
                'user_id'        => $user?->id,
                'target_user_id' => $target?->id,
                'identifier'     => $identifier !== null ? Str::limit($identifier, 255, '') : ($user?->email),
                'event'          => $event,
                'result'         => $result,
                'ip_address'     => $request?->ip(),
                'user_agent'     => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
                'details'        => $details !== null ? Str::limit($details, 500, '') : null,
                'created_at'     => now(),
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe tumbar la aplicación, pero el fallo se reporta.
            report($e);
        }
    }

    /** Atajo: usuario autenticado actual como autor. */
    public static function byCurrent(string $event, string $result = self::SUCCESS, ?User $target = null, ?string $details = null): void
    {
        self::log($event, $result, Auth::user(), null, $target, $details);
    }
}
