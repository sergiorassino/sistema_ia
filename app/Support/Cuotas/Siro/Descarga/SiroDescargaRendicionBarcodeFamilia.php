<?php

namespace App\Support\Cuotas\Siro\Descarga;

/**
 * Familia del código de barras en rendición SIRO (Integrado v5.2).
 *
 * - 0448: cupón impreso (PF, RP, ventanilla).
 * - 0449 / 0444 / 0447: barra rearmada por medios electrónicos (PMC, LK, BPD, etc.).
 */
final class SiroDescargaRendicionBarcodeFamilia
{
    public const CUPON_448 = '448';

    public const ELECTRONICO_449 = '449';

    public const ELECTRONICO_444 = '444';

    public const ELECTRONICO_447 = '447';

    public const DESCONOCIDO = 'desconocido';

    public static function desdeCodigoBarras(string $codigoBarras): string
    {
        $digits = preg_replace('/\D+/', '', $codigoBarras) ?? '';
        if (strlen($digits) < 4) {
            return self::DESCONOCIDO;
        }

        return match (substr($digits, 0, 4)) {
            '0448' => self::CUPON_448,
            '0449' => self::ELECTRONICO_449,
            '0444' => self::ELECTRONICO_444,
            '0447' => self::ELECTRONICO_447,
            default => self::DESCONOCIDO,
        };
    }

    public static function esCupón448(string $familia): bool
    {
        return $familia === self::CUPON_448;
    }

    public static function esElectronico(string $familia): bool
    {
        return in_array($familia, [
            self::ELECTRONICO_449,
            self::ELECTRONICO_444,
            self::ELECTRONICO_447,
        ], true);
    }
}
