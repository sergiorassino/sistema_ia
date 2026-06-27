<?php

namespace App\Support;

/**
 * Menú de Administración — grupo «Medios de pago» y subgrupos (SIRO, etc.).
 */
final class PermisosMediosPago
{
    /** @return list<string> */
    public static function rutasSubgrupoSiro(): array
    {
        return [
            'cuotas.siro-subida',
            'cuotas.siro-subida.archivo',
            'cuotas.siro-cupones-vencidos',
            'cuotas.siro-cupones-vencidos.archivo',
            'cuotas.siro-descarga',
            'cuotas.siro-descarga.detalle',
        ];
    }

    public static function enRutaSubgrupoSiro(?string $route): bool
    {
        return in_array($route ?? '', self::rutasSubgrupoSiro(), true);
    }

    public static function muestraSubgrupoSiro(): bool
    {
        if (! tenantCuotasSiroHabilitado()) {
            return false;
        }

        return PermisosCuotas::puedeSiroSubidaBaseDeuda()
            || PermisosCuotas::puedeSiroCuponesVencidos()
            || PermisosCuotas::puedeSiroDescargaRendicion();
    }

    public static function muestraGrupo(): bool
    {
        return self::muestraSubgrupoSiro();
    }
}
