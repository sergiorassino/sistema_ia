<?php

namespace App\Support;

/**
 * Permisos por ítem del Menú de Administración — consultas ARCA.
 */
final class PermisosArca
{
    private static function enAdministracion(): bool
    {
        return schoolEsAdministracion();
    }

    private static function tiene(int $orden): bool
    {
        return self::enAdministracion() && tienePermiso($orden);
    }

    public static function puedeConsultaCuitPorDni(): bool
    {
        return self::tiene(PermisosIaCatalog::ADMIN_ARCA_CONSULTA_CUIT_DNI);
    }

    /** Grupo sidebar «ARCA». */
    public static function muestraGrupoArca(): bool
    {
        return self::puedeConsultaCuitPorDni();
    }

    /** Guías PDF de configuración ARCA (facturación, padrón, etc.). */
    public static function puedeDescargarGuiasArca(): bool
    {
        if (self::puedeConsultaCuitPorDni()) {
            return true;
        }

        if (PermisosCuotas::puedeConsultaAfipComprobante() || PermisosCuotas::puedeFacturacionMasivaAfip()) {
            return true;
        }

        return self::enAdministracion() && tienePermiso(PermisosConfiguracion::PARAMETROS_SISTEMA);
    }
}
