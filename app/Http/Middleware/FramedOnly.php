<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mantiene la URL fija en /portal: si alguien abre una pantalla interna
 * escribiendo la dirección a mano (navegación de nivel superior), lo
 * devuelve al portal. Dentro del iframe todo funciona con normalidad.
 */
class FramedOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->header('Sec-Fetch-Dest') === 'document') {
            return redirect()->route('mask');
        }

        return $next($request);
    }
}
