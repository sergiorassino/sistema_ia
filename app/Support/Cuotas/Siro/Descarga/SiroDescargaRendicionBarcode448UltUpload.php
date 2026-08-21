<?php

namespace App\Support\Cuotas\Siro\Descarga;

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

        return SiroDescargaRendicionCuotaAlcance::ultUpload(
            $parsed['idLegajos'],
            $parsed['idCuotas'],
            $idTerlec,
        );
    }
}
