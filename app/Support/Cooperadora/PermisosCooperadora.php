<?php

namespace App\Support\Cooperadora;

use App\Support\PermisosIaCatalog;

/**
 * Permisos del módulo Cooperadora escolar (Menú de Secretaría).
 */
final class PermisosCooperadora
{
    private static function tiene(int $orden): bool
    {
        return tienePermiso($orden);
    }

    public static function puedeParametrizacion(): bool
    {
        return self::tiene(PermisosIaCatalog::COOP_PARAMETRIZACION);
    }

    public static function puedeIngresos(): bool
    {
        return self::tiene(PermisosIaCatalog::COOP_INGRESOS);
    }

    public static function puedeEgresos(): bool
    {
        return self::tiene(PermisosIaCatalog::COOP_EGRESOS);
    }

    public static function puedeMovimientos(): bool
    {
        return self::tiene(PermisosIaCatalog::COOP_MOVIMIENTOS);
    }

    public static function muestraGrupoCooperadora(): bool
    {
        return self::puedeParametrizacion()
            || self::puedeIngresos()
            || self::puedeEgresos()
            || self::puedeMovimientos();
    }
}
