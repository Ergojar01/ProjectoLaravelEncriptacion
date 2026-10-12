<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleLevel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Verificamos si el usuario está autenticado
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // 2. Obtenemos el nivel del usuario actual
        $userLevel = (int) auth()->user()->role_level;
        $requiredLevel = (int) $role;

        // 3. Validamos si su nivel es menor al requerido
        if ($userLevel < $requiredLevel) {
            // Si no tiene el nivel, devolvemos un error 403 (Prohibido)
            abort(403, 'Acceso denegado. No tienes el nivel de permisos requerido para esta área.');
        }

        // Si pasa la validación, la petición continúa su curso
        return $next($request);
    }
}