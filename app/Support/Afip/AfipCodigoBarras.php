<?php

namespace App\Support\Afip;

/**
 * Código de barras de comprobantes electrónicos AFIP (formato legacy del sistema).
 */
final class AfipCodigoBarras
{
    public static function generar(
        string $cuit,
        int $tipoComprobante,
        int $puntoVenta,
        string $cae,
        string $vtoCaeYmd,
    ): string {
        $cuitLimpio = preg_replace('/\D/', '', $cuit) ?? '';
        $caeLimpio = preg_replace('/\D/', '', $cae) ?? '';
        $vtoLimpio = preg_replace('/\D/', '', $vtoCaeYmd) ?? '';

        return str_pad($cuitLimpio, 11, '0', STR_PAD_LEFT)
            .str_pad((string) $tipoComprobante, 3, '0', STR_PAD_LEFT)
            .str_pad((string) $puntoVenta, 5, '0', STR_PAD_LEFT)
            .str_pad($caeLimpio, 14, '0', STR_PAD_LEFT)
            .str_pad(substr($vtoLimpio, -8), 8, '0', STR_PAD_LEFT);
    }
}
