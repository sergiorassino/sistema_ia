<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Barra rearmada 0449/0444/0447 en rendición (medios electrónicos).
 *
 * SIRO completa Id. usuario (pos. 36–43) desde pos. 06–13 del barcode; este parser
 * sirve de respaldo si el comprobante no alcanza pero la barra trae el bloque cliente.
 */
final class SiroDescargaRendicionBarcodeElectronico
{
    private const OFFSET_ID_USUARIO = 5;

    private const LARGO_ID_USUARIO = 8;

    /**
     * Id. usuario de 8 dígitos en barra rearmada (pos. 06–13, base 1).
     */
    public static function idUsuarioDesdeBarcode(string $codigoBarras): string
    {
        $digits = preg_replace('/\D+/', '', $codigoBarras) ?? '';
        if (strlen($digits) < self::OFFSET_ID_USUARIO + self::LARGO_ID_USUARIO) {
            return '';
        }

        return substr($digits, self::OFFSET_ID_USUARIO, self::LARGO_ID_USUARIO);
    }

    public static function idFacturaDesdeLinea(string $idComprobante, string $codigoBarras, string $idUsuarioLinea): ?string
    {
        $idFactura = SiroDescargaRendicionIdComprobante::idFacturaDesdeLinea($idComprobante, $idUsuarioLinea);
        if ($idFactura !== null) {
            return $idFactura;
        }

        $idUsuarioBarcode = self::idUsuarioDesdeBarcode($codigoBarras);
        if ($idUsuarioBarcode === '' || $idUsuarioBarcode === $idUsuarioLinea) {
            return null;
        }

        return SiroDescargaRendicionIdComprobante::idFacturaDesdeLinea($idComprobante, $idUsuarioBarcode);
    }
}
