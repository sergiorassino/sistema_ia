<?php

namespace App\Support\PortalDocente;

use App\Models\Profesor;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\Auth;

/**
 * Contexto del Menú de Docentes (`/portal-docente`, rutas `portalDocente.*`).
 *
 * Los ítems del sidebar docente no usan permisos_ia: cualquier usuario del portal
 * puede usarlos (alcance por ppc / tenant config). Solo el Menú de Secretaría exige permisos.
 */
final class PortalDocenteContext
{
    public static function esActivo(): bool
    {
        if (request()->routeIs('portalDocente.*')) {
            return true;
        }

        $profesor = Auth::user();
        if ($profesor instanceof Profesor && ProfesorMenuPortal::usaMenuDocentes($profesor)) {
            return true;
        }

        $referer = (string) request()->headers->get('referer', '');

        return $referer !== '' && str_contains($referer, '/portal-docente');
    }

    /**
     * Exige permiso IA solo fuera del Menú de Docentes.
     */
    public static function abortSiStaffSinPermisoIa(int $orden, string $mensaje = 'Sin permiso.'): void
    {
        if (self::esActivo()) {
            return;
        }

        abort_unless(tienePermiso($orden), 403, $mensaje);
    }
}
