<?php

namespace App\Support\Mora;

use App\Support\PermisosIaCatalog;

/**
 * Permisos por ítem del Menú de Administración — gestión de mora.
 */
final class PermisosMora
{
    private static function enAdministracion(): bool
    {
        return schoolEsAdministracion();
    }

    private static function tiene(int $orden): bool
    {
        return self::enAdministracion() && tienePermiso($orden);
    }

    public static function puedeEstadoDeudaFamiliar(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_MORA_ESTADO_DEUDA);
    }

    public static function puedeGestionMorosos(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_MORA_GESTION_MOROSOS);
    }

    /** Grupo sidebar «Gestión de mora». */
    public static function muestraGrupoGestionMora(): bool
    {
        return self::puedeEstadoDeudaFamiliar() || self::puedeGestionMorosos();
    }
}
