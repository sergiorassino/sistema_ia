<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuotaGenerada;

/**
 * Localiza {@see CuotaGenerada} para descarga SIRO sin exigir el ciclo de sesión.
 *
 * Si hay varias filas (mismo legajo + idCuotas en distintos años), prioriza el
 * ciclo preferido (sesión) y, si no hay, la más reciente.
 */
final class SiroDescargaRendicionCuotaAlcance
{
    public static function porId(int $idCuotasgeneradas): ?CuotaGenerada
    {
        if ($idCuotasgeneradas <= 0) {
            return null;
        }

        return CuotaGenerada::query()->where('id', $idCuotasgeneradas)->first();
    }

    /**
     * @param  int  $idTerlecPreferido  Ciclo de sesión (0 = solo el más reciente).
     */
    public static function porLegajoYCuota(int $idLegajos, int $idCuotas, int $idTerlecPreferido = 0): ?CuotaGenerada
    {
        if ($idLegajos <= 0 || $idCuotas <= 0) {
            return null;
        }

        $base = CuotaGenerada::query()
            ->where('idLegajos', $idLegajos)
            ->where('idCuotas', $idCuotas);

        if ($idTerlecPreferido > 0) {
            $enSesion = (clone $base)->where('idTerlec', $idTerlecPreferido)->first();
            if ($enSesion !== null) {
                return $enSesion;
            }
        }

        return $base
            ->orderByDesc('idTerlec')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  int  $idTerlecPreferido  Ciclo de sesión (0 = buscar en cualquier año).
     */
    public static function ultUpload(int $idLegajos, int $idCuotas, int $idTerlecPreferido = 0): ?int
    {
        $cuota = self::porLegajoYCuota($idLegajos, $idCuotas, $idTerlecPreferido);
        if ($cuota === null) {
            return null;
        }

        $ultUpload = (int) ($cuota->ultUpload ?? 0);

        return $ultUpload > 0 ? $ultUpload : null;
    }
}
