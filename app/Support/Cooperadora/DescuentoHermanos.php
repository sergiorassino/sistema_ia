<?php

namespace App\Support\Cooperadora;

use App\Models\Legajo;

/**
 * Descuento por hermanos matriculados en el ciclo activo.
 */
final class DescuentoHermanos
{
    public static function porcentajeParaLegajo(int $idLegajo): float
    {
        $pctConfig = CooperadoraConfig::descuentoHermanosPct();
        if ($pctConfig <= 0) {
            return 0.0;
        }

        $legajo = Legajo::query()->find($idLegajo);
        if ($legajo === null || ! self::tieneFamiliaReal($legajo)) {
            return 0.0;
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        $idFamilia = (int) $legajo->idFamilias;

        $cantidad = Legajo::query()
            ->where('idFamilias', $idFamilia)
            ->whereHas('matriculas', function ($q) use ($idTerlec) {
                $q->where('idTerlec', $idTerlec)
                    ->where(function ($sub) {
                        $sub->whereNull('fechaBaja')
                            ->orWhere('fechaBaja', '0000-00-00')
                            ->orWhere('fechaBaja', '');
                    });
            })
            ->count();

        return $cantidad >= 2 ? $pctConfig : 0.0;
    }

    public static function importeConDescuento(float $importeBruto, float $descuentoPct): float
    {
        if ($importeBruto <= 0 || $descuentoPct <= 0) {
            return round($importeBruto, 2);
        }

        return round($importeBruto * (1 - ($descuentoPct / 100)), 2);
    }

    private static function tieneFamiliaReal(Legajo $legajo): bool
    {
        $id = (int) ($legajo->idFamilias ?? 0);

        return $id > 1;
    }
}
