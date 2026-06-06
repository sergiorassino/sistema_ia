<?php

namespace App\Http\Middleware;

use App\Models\Profesor;
use App\Support\ProfesorMenuPortal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe rutas al Menú de Docentes, Administración o Secretaría pedagógica.
 *
 * @see docs/08-menus-de-navegacion.md
 */
class EnsureMenuPortal
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $profesor = Auth::user();

        if (! $profesor instanceof Profesor) {
            return $next($request);
        }

        return match ($portal) {
            'docente' => $this->asegurarDocente($profesor, $next, $request),
            'administracion' => $this->asegurarAdministracion($profesor, $next, $request),
            'secretaria' => $this->asegurarSecretariaPedagogica($profesor, $next, $request),
            'staff' => $this->asegurarStaff($profesor, $next, $request),
            default => $next($request),
        };
    }

    private function asegurarDocente(Profesor $profesor, Closure $next, Request $request): Response
    {
        if (! ProfesorMenuPortal::usaMenuDocentes($profesor)) {
            return ProfesorMenuPortal::redirectInicio($profesor);
        }

        return $next($request);
    }

    private function asegurarAdministracion(Profesor $profesor, Closure $next, Request $request): Response
    {
        if (ProfesorMenuPortal::usaMenuDocentes($profesor) || ! ProfesorMenuPortal::usaMenuAdministracion()) {
            return ProfesorMenuPortal::redirectInicio($profesor);
        }

        return $next($request);
    }

    private function asegurarSecretariaPedagogica(Profesor $profesor, Closure $next, Request $request): Response
    {
        if (! ProfesorMenuPortal::usaMenuSecretariaPedagogica($profesor)) {
            return ProfesorMenuPortal::redirectInicio($profesor);
        }

        return $next($request);
    }

    private function asegurarStaff(Profesor $profesor, Closure $next, Request $request): Response
    {
        if (! ProfesorMenuPortal::usaMenuStaff($profesor)) {
            return ProfesorMenuPortal::redirectInicio($profesor);
        }

        return $next($request);
    }
}
