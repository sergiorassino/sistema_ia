<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Support\Cuotas\Siro\SiroIdFactura;

/**
 * Código de barras 0448 del cupón impreso ({@see \App\Support\Alumnos\ComprobantePagoCodigoBarras}).
 *
 * Estructura (59 dígitos): empresa(4) + identUsuario(15) + fecha1erVenc(6) + importe1erVenc(10)
 * + dias2doVenc(2) + importe2doVenc(10) + numeroCuenta(10) + DV(2).
 *
 * Modalidad nueva (emisión): {@see SiroDescargaRendicionIdentUsuario448Nuevo}.
 *
 * Modalidad anterior (legacy): concepto(1) + legajo(5) + idCuotas(3) + relleno(6).
 */
final class SiroDescargaRendicionBarcodeComprobante448
{
    public const PREFIJO = '0448';

    public const LARGO = 59;

    private const LARGO_IDENT_USUARIO = 15;

    private const OFFSET_RELLENO = 9;

    private const LARGO_RELLENO = 6;

    private const RELLENO_LEGACY = '000000';

    public static function esBarcodeComprobante448(string $codigoBarras): bool
    {
        $digits = preg_replace('/\D+/', '', $codigoBarras) ?? '';

        return strlen($digits) >= 4 && substr($digits, 0, 4) === self::PREFIJO;
    }

    /**
     * @return array{
     *     digits: string,
     *     identUsuario: string,
     *     idLegajos: int,
     *     idCuotas: int,
     *     concepto: string,
     *     relleno: string,
     *     ultUpload: ?int,
     *     fecha1erVenc: string,
     *     importe1erVencCentavos: int
     * }|null
     */
    public static function parseLegacy(string $codigoBarras): ?array
    {
        $digits = preg_replace('/\D+/', '', $codigoBarras) ?? '';
        if (strlen($digits) < self::LARGO || substr($digits, 0, 4) !== self::PREFIJO) {
            return null;
        }

        $digits = substr($digits, 0, self::LARGO);
        $identUsuario = substr($digits, 4, self::LARGO_IDENT_USUARIO);
        $concepto = substr($identUsuario, 0, 1);
        if (! in_array($concepto, ['1', '3', '4'], true)) {
            return null;
        }

        $idLegajos = (int) substr($identUsuario, 1, 5);
        $idCuotas = (int) substr($identUsuario, 6, 3);

        if ($idLegajos <= 0 || $idCuotas <= 0) {
            return null;
        }

        $relleno = substr($identUsuario, self::OFFSET_RELLENO, self::LARGO_RELLENO);

        return [
            'digits' => $digits,
            'identUsuario' => $identUsuario,
            'idLegajos' => $idLegajos,
            'idCuotas' => $idCuotas,
            'concepto' => substr($identUsuario, 0, 1),
            'relleno' => $relleno,
            'ultUpload' => self::ultUploadDesdeRelleno($relleno),
            'fecha1erVenc' => substr($digits, 19, 6),
            'importe1erVencCentavos' => (int) substr($digits, 25, 10),
        ];
    }

    /** @deprecated Usar {@see parseLegacy()} */
    public static function parse(string $codigoBarras): ?array
    {
        return self::parseLegacy($codigoBarras);
    }

    public static function armarIdentUsuarioLegacy(
        string $concepto,
        int $idLegajos,
        int $idCuotas,
        int $ultUpload,
    ): string {
        return substr(preg_replace('/\D+/', '', $concepto) ?? '1', -1)
            .str_pad((string) $idLegajos, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $idCuotas, 3, '0', STR_PAD_LEFT)
            .self::rellenoDesdeUltUpload($ultUpload);
    }

    public static function rellenoDesdeUltUpload(int $ultUpload): string
    {
        $ultUpload = max(1, min(99, $ultUpload));

        return str_pad((string) $ultUpload, 2, '0', STR_PAD_LEFT).'0000';
    }

    public static function ultUploadDesdeIdentUsuarioLegacy(string $identUsuario): ?int
    {
        if (strlen($identUsuario) < self::OFFSET_RELLENO + self::LARGO_RELLENO) {
            return null;
        }

        return self::ultUploadDesdeRelleno(substr($identUsuario, self::OFFSET_RELLENO, self::LARGO_RELLENO));
    }

    public static function ultUploadDesdeRelleno(string $relleno): ?int
    {
        if ($relleno === self::RELLENO_LEGACY) {
            return null;
        }

        $ultUpload = (int) substr($relleno, 0, 2);

        return $ultUpload > 0 ? $ultUpload : null;
    }

    /**
     * @param  array{idLegajos: int, idCuotas: int}  $parsed
     */
    public static function idFacturaDesdeParseLegacy(array $parsed, int $ultUpload): ?string
    {
        if ($ultUpload <= 0) {
            return null;
        }

        return SiroIdFactura::generar(
            $parsed['idLegajos'],
            $parsed['idCuotas'],
            $ultUpload,
        );
    }
}
