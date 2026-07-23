<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    /**
     * Handle an incoming request.
     *
     * Acepta uno o más órdenes de permisos_ia (OR): basta con que el usuario
     * tenga alguno. Laravel parte `permiso:68,69` en parámetros distintos;
     * por eso se usan argumentos variádicos (no un solo string con explode).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$ordenes): Response
    {
        foreach ($ordenes as $ordenParam) {
            foreach (explode(',', $ordenParam) as $orden) {
                $orden = trim($orden);
                if ($orden !== '' && tienePermiso((int) $orden)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'No tiene permiso para acceder a esta sección.');
    }
}
