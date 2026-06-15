<?php

namespace App\Support\Cooperadora;

use App\Models\CoopRubroIngreso;

/**
 * Descuento por hermanos en cooperadora — según marca en matrícula del ciclo activo ({@see Matricula::coop_es_hermano}).
 * Solo aplica a rubros marcados con descuento por hermanos ({@see CoopRubroIngreso::aplicaDescuentoHermano()}).
 */
final class DescuentoHermanos
{
    public static function porcentajeParaLinea(int $idLegajo, int $idRubro): float
    {
        $rubro = CoopRubroIngreso::query()->find($idRubro);
        if ($rubro === null || ! $rubro->aplicaDescuentoHermano()) {
            return 0.0;
        }

        return self::porcentajeParaLegajo($idLegajo);
    }

    public static function porcentajeParaLegajo(int $idLegajo): float
    {
        $pctConfig = CooperadoraConfig::descuentoHermanosPct();
        if ($pctConfig <= 0) {
            return 0.0;
        }

        if (! BusquedaEstudianteCooperadora::esHermanoCooperadora($idLegajo)) {
            return 0.0;
        }

        return $pctConfig;
    }

    public static function importeConDescuento(float $importeBruto, float $descuentoPct): float
    {
        if ($importeBruto <= 0 || $descuentoPct <= 0) {
            return round($importeBruto, 2);
        }

        return round($importeBruto * (1 - ($descuentoPct / 100)), 2);
    }
}
