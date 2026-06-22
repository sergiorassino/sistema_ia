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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ordenes): Response
    {
        foreach (explode(',', $ordenes) as $orden) {
            $orden = trim($orden);
            if ($orden !== '' && tienePermiso((int) $orden)) {
                return $next($request);
            }
        }

        abort(403, 'No tiene permiso para acceder a esta sección.');
    }
}
