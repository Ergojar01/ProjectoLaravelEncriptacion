<?php

namespace App\Http\Middleware;

use App\Services\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleLevel
{
    public function handle(Request $request, Closure $next, int $level): Response
    {
        $user = Auth::user();

        if (! $user || ! in_array($user->role_level, $level === 500 ? [500] : [499, 500], true)) {
            Audit::log('access_denied', Audit::DENIED, $user, null, null, $request->route()->getName());
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
