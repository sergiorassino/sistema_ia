<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Id. cliente extendido — rendición SIRO (Integrado extendido / UNIFICADO).
 *
 * SIRO devuelve los 15 dígitos del identificador 0448 (pos. 05–19 del código de barras)
 * en el campo «ID cliente extendido». En Integrado corto (272) reparte pos. 7 → concepto
 * y pos. 8–15 → id. usuario; en líneas extendidas (>372) suele repetirse al final.
 */
final class SiroDescargaRendicionIdClienteExtendido
{
    public const LARGO = 15;

    /** Posición base 0 del bloque en línea Integrado extendida (pos. 374, base 1). */
    public const OFFSET_LINEA = 373;

    /**
     * @param  array<string, mixed>  $linea  Salida de {@see SiroDescargaRendicionLinea::parsear}
     */
    public static function identUsuario15(array $linea): string
    {
        $desdeCola = self::desdeColaLinea((string) ($linea['cadenaPago'] ?? ''));
        if ($desdeCola !== '') {
            return $desdeCola;
        }

        $codigoBarras = (string) ($linea['codigoBarras'] ?? '');
        if (SiroDescargaRendicionBarcodeComprobante448::esBarcodeComprobante448($codigoBarras)) {
            $parsed = SiroDescargaRendicionBarcodeComprobante448::parseLegacy($codigoBarras);
            if ($parsed !== null && strlen($parsed['identUsuario']) === self::LARGO) {
                return $parsed['identUsuario'];
            }

            $digits = preg_replace('/\D+/', '', $codigoBarras) ?? '';
            if (strlen($digits) >= 19) {
                return substr($digits, 4, self::LARGO);
            }
        }

        return self::desdeConceptoEIdUsuario(
            (string) ($linea['concepto'] ?? ''),
            (string) ($linea['idUsuario'] ?? ''),
        );
    }

    public static function desdeColaLinea(string $cadenaPago): string
    {
        $cadenaPago = rtrim($cadenaPago, "\r\n");
        if (strlen($cadenaPago) <= self::OFFSET_LINEA) {
            return '';
        }

        $cola = trim(substr($cadenaPago, self::OFFSET_LINEA));
        $digits = preg_replace('/\D+/', '', $cola) ?? '';
        if ($digits === '') {
            return '';
        }

        $bloqueExt = trim(substr($cadenaPago, 272, 101));
        $conceptoExt = preg_match('/^\d$/', $bloqueExt) === 1 ? $bloqueExt : '';

        if (strlen($digits) === self::LARGO) {
            return $digits;
        }

        if (strlen($digits) === self::LARGO - 1 && $conceptoExt !== '') {
            return $conceptoExt.str_pad($digits, self::LARGO - 1, '0', STR_PAD_LEFT);
        }

        if (strlen($digits) > self::LARGO) {
            return substr($digits, 0, self::LARGO);
        }

        return str_pad($digits, self::LARGO, '0', STR_PAD_LEFT);
    }

    /**
     * Recompone parcialmente pos. 7 + 8–15 cuando no hay cola extendida (solo 8 dígitos útiles).
     */
    private static function desdeConceptoEIdUsuario(string $concepto, string $idUsuario): string
    {
        $concepto = preg_replace('/\D+/', '', $concepto) ?? '';
        $idUsuario = preg_replace('/\D+/', '', $idUsuario) ?? '';
        if ($concepto === '' || $idUsuario === '') {
            return '';
        }

        $concepto = substr(str_pad($concepto, 1, '0', STR_PAD_LEFT), -1);
        $idUsuario = str_pad(substr($idUsuario, -8), 8, '0', STR_PAD_LEFT);

        return str_pad($concepto.$idUsuario, self::LARGO, '0', STR_PAD_RIGHT);
    }
}
