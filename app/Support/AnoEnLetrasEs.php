<?php

namespace App\Support;

/**
 * Año en letras (español) para encabezados de PDF.
 * Usa ext/intl si está disponible; si no, conversión propia para años escolares habituales.
 */
final class AnoEnLetrasEs
{
    public static function format(int $ano): string
    {
        if (extension_loaded('intl') && class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
            $texto = $formatter->format($ano);
            if (is_string($texto) && $texto !== '') {
                return mb_strtoupper($texto, 'UTF-8');
            }
        }

        return mb_strtoupper(self::sinIntl($ano), 'UTF-8');
    }

    private static function sinIntl(int $ano): string
    {
        if ($ano >= 2000 && $ano <= 2099) {
            $resto = $ano - 2000;

            return $resto === 0 ? 'dos mil' : 'dos mil '.self::cardinal0a99($resto);
        }

        if ($ano >= 1900 && $ano <= 1999) {
            $resto = $ano - 1900;

            return $resto === 0 ? 'mil novecientos' : 'mil novecientos '.self::cardinal0a99($resto);
        }

        return (string) $ano;
    }

    private static function cardinal0a99(int $n): string
    {
        if ($n < 0 || $n > 99) {
            return (string) $n;
        }

        if ($n < 10) {
            return ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'][$n];
        }

        if ($n < 20) {
            return [
                'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
                'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            ][$n - 10];
        }

        $decenas = intdiv($n, 10);
        $unidad = $n % 10;

        $nombreDecena = match ($decenas) {
            2 => 'veinte',
            3 => 'treinta',
            4 => 'cuarenta',
            5 => 'cincuenta',
            6 => 'sesenta',
            7 => 'setenta',
            8 => 'ochenta',
            9 => 'noventa',
            default => '',
        };

        if ($unidad === 0) {
            return $nombreDecena;
        }

        if ($decenas === 2) {
            return match ($unidad) {
                1 => 'veintiuno',
                2 => 'veintidós',
                3 => 'veintitrés',
                4 => 'veinticuatro',
                5 => 'veinticinco',
                6 => 'veintiséis',
                7 => 'veintisiete',
                8 => 'veintiocho',
                9 => 'veintinueve',
                default => $nombreDecena,
            };
        }

        return $nombreDecena.' y '.self::cardinal0a99($unidad);
    }
}
