<?php

namespace App\Support\ProyectosExtracurriculares;

use App\Models\Profesor;
use App\Support\Navegacion\MenuSecretariaPerfil;
use App\Support\PermisosIaCatalog;
use App\Support\ProfesorMenuPortal;
use Illuminate\Support\Facades\Auth;

/**
 * Rutas del listado de propuestas propias según el portal activo.
 */
final class RutasPortal
{
    public static function esPortalDocente(): bool
    {
        $user = Auth::user();

        return $user instanceof Profesor && ProfesorMenuPortal::usaMenuDocentes($user);
    }

    public static function index(): string
    {
        return self::esPortalDocente()
            ? 'portalDocente.proyectosExtracurriculares.index'
            : 'proyectosExtracurriculares.proponer';
    }

    public static function crear(): string
    {
        return self::esPortalDocente()
            ? 'portalDocente.proyectosExtracurriculares.create'
            : 'proyectosExtracurriculares.create';
    }

    public static function editar(): string
    {
        return self::esPortalDocente()
            ? 'portalDocente.proyectosExtracurriculares.edit'
            : 'proyectosExtracurriculares.edit';
    }

    public static function layout(): string
    {
        return self::esPortalDocente() ? 'layouts.docente' : layoutMenuStaff();
    }

    public static function eyebrow(): string
    {
        return self::esPortalDocente() ? 'Autogestión docente' : 'Menú de Secretaría';
    }

    /** En Secretaría exige el permiso de visualización (orden 109). El portal docente no. */
    public static function asegurarAcceso(): void
    {
        if (self::esPortalDocente()) {
            return;
        }

        abort_unless(
            MenuSecretariaPerfil::muestraProyectosExtracurriculares(),
            403,
            'Proyectos extracurriculares solo están disponibles en el Menú de Secretaría (niveles pedagógicos).',
        );

        abort_unless(
            tienePermiso(PermisosIaCatalog::PROYECTOS_EXTRACURRICULARES_VER),
            403,
            'Sin permiso para ver proyectos extracurriculares.',
        );
    }
}
