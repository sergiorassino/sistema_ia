<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuotaGenerada;

/**
 * ult_upload en modalidad legacy 0448 (relleno o cuotasgeneradas).
 */
final class SiroDescargaRendicionBarcode448UltUpload
{
    /**
     * @param  array{
     *     idLegajos: int,
     *     idCuotas: int,
     *     identUsuario?: string,
     *     ultUpload?: ?int
     * }  $parsed
     */
    public static function desdeLineaLegacy(array $linea, int $idTerlec, array $parsed): ?int
    {
        if (isset($parsed['ultUpload']) && $parsed['ultUpload'] !== null && (int) $parsed['ultUpload'] > 0) {
            return (int) $parsed['ultUpload'];
        }

        $identUsuario = (string) ($parsed['identUsuario'] ?? '');
        if ($identUsuario !== '') {
            $ult = SiroDescargaRendicionBarcodeComprobante448::ultUploadDesdeIdentUsuarioLegacy($identUsuario);
            if ($ult !== null) {
                return $ult;
            }
        }

        return self::desdeCuotaGenerada($parsed['idLegajos'], $parsed['idCuotas'], $idTerlec);
    }

    private static function desdeCuotaGenerada(int $idLegajos, int $idCuotas, int $idTerlec): ?int
    {
        if ($idTerlec <= 0) {
            return null;
        }

        $ultUpload = CuotaGenerada::query()
            ->where('idLegajos', $idLegajos)
            ->where('idCuotas', $idCuotas)
            ->where('idTerlec', $idTerlec)
            ->value('ultUpload');

        $ultUpload = (int) $ultUpload;

        return $ultUpload > 0 ? $ultUpload : null;
    }
}
