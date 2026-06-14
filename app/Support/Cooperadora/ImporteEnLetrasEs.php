<?php

namespace App\Support\Cooperadora;

/**
 * Importe monetario en letras (español, pesos).
 */
final class ImporteEnLetrasEs
{
    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = [
        '', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa',
    ];

    private const CENTENAS = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos',
    ];

    public static function pesos(float $importe): string
    {
        $importe = round(max(0, $importe), 2);
        $entero = (int) floor($importe);
        $centavos = (int) round(($importe - $entero) * 100);

        if ($entero === 0 && $centavos === 0) {
            return 'Cero pesos';
        }

        $texto = self::entero($entero);
        $texto = $entero === 1 ? 'Un peso' : ucfirst($texto).' pesos';

        if ($centavos > 0) {
            $texto .= ' con '.self::entero($centavos).($centavos === 1 ? ' centavo' : ' centavos');
        }

        return $texto;
    }

    private static function entero(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }
        if ($n === 100) {
            return 'cien';
        }
        if ($n < 30) {
            return self::UNIDADES[$n];
        }
        if ($n < 100) {
            $dec = intdiv($n, 10);
            $uni = $n % 10;
            if ($dec === 2 && $uni > 0) {
                return self::UNIDADES[$n];
            }
            $base = self::DECENAS[$dec];

            return $uni === 0 ? $base : $base.' y '.self::UNIDADES[$uni];
        }
        if ($n < 1000) {
            $cen = intdiv($n, 100);
            $resto = $n % 100;
            $base = $cen === 1 && $resto === 0 ? 'cien' : self::CENTENAS[$cen];

            return $resto === 0 ? $base : $base.' '.self::entero($resto);
        }
        if ($n < 1_000_000) {
            $mil = intdiv($n, 1000);
            $resto = $n % 1000;
            $base = $mil === 1 ? 'mil' : self::entero($mil).' mil';

            return $resto === 0 ? $base : $base.' '.self::entero($resto);
        }

        $mill = intdiv($n, 1_000_000);
        $resto = $n % 1_000_000;
        $base = $mill === 1 ? 'un millón' : self::entero($mill).' millones';

        return $resto === 0 ? $base : $base.' '.self::entero($resto);
    }
}
