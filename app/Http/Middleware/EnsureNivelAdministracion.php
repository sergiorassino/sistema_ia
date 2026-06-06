<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas exclusivas del nivel Administración (`school.idNivel = 5`).
 */
class EnsureNivelAdministracion
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            schoolEsAdministracion(),
            403,
            'Este módulo está disponible solo para usuarios del nivel Administración.',
        );

        return $next($request);
    }
}
