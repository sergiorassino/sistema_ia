<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Support\Cuotas\Siro\SiroIdFactura;

/**
 * Interpreta el Identificador de comprobante (pos. 104–123) de la rendición SIRO.
 *
 * Puede venir como {@see SiroIdFactura} completo (20 dígitos con legajo) o como
 * comprobante de 20 dígitos sin legajo (idCuotas + ult + sufijo), con legajo en Id. Usuario.
 */
final class SiroDescargaRendicionIdComprobante
{
    public static function idFacturaDesdeLinea(string $idComprobante, string $idUsuario): ?string
    {
        $digits = preg_replace('/\D+/', '', $idComprobante) ?? '';
        if ($digits === '' || preg_match('/^0+$/', $digits) === 1) {
            return null;
        }

        $digits = str_pad(substr($digits, 0, 20), 20, '0', STR_PAD_LEFT);

        $dec = SiroIdFactura::decodificar($digits);
        if ($dec !== null) {
            return $digits;
        }

        return self::idFacturaDesdeComprobanteSinLegajo($digits, $idUsuario);
    }

    /**
     * Legajo desde Id. Usuario (pos. 36–43): bloque SIRO prefijo(2) + legajo(7).
     */
    public static function legajoDesdeIdUsuario(string $idUsuario): int
    {
        $digits = preg_replace('/\D+/', '', $idUsuario) ?? '';
        if ($digits === '') {
            return 0;
        }

        $bloque9 = str_pad($digits, 9, '0', STR_PAD_LEFT);

        return (int) substr($bloque9, 2, 7);
    }

    private static function idFacturaDesdeComprobanteSinLegajo(string $digits20, string $idUsuario): ?string
    {
        if (strlen($digits20) !== 20) {
            return null;
        }

        $idCuotas = (int) substr($digits20, 0, 11);
        $concepto = substr($digits20, 11, 1);
        $idCuotas3 = substr($digits20, 12, 3);
        $ultUpload = (int) substr($digits20, 15, 2);
        $sufijo3 = substr($digits20, 17, 3);

        if ($idCuotas <= 0 || $ultUpload <= 0) {
            return null;
        }

        if (! in_array($concepto, ['1', '3', '4'], true)) {
            return null;
        }

        $sufijoEsperado = substr(str_pad((string) $idCuotas, 7, '0', STR_PAD_LEFT), -3);
        if ($sufijo3 !== $sufijoEsperado || $idCuotas3 !== substr(str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT), -3)) {
            return null;
        }

        $idLegajos = self::legajoDesdeIdUsuario($idUsuario);
        if ($idLegajos <= 0) {
            return null;
        }

        return SiroIdFactura::generar($idLegajos, $idCuotas, $ultUpload);
    }
}
