<?php

namespace App\Support\Certificados;

use App\Support\AnoEnLetrasEs;

/**
 * Textos en español para certificados de finalización (jardín / sexto).
 */
final class CertificadoFinalizacionTextoEs
{
    public static function mesNombre(int $mes): string
    {
        return match ($mes) {
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
            default => '',
        };
    }

    /**
     * Día o año en letras (minúsculas), p. ej. «veintiuno», «dos mil veintiséis».
     */
    public static function enLetras(int $numero): string
    {
        if ($numero < 0) {
            return (string) $numero;
        }

        if (extension_loaded('intl') && class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
            $texto = $formatter->format($numero);
            if (is_string($texto) && $texto !== '') {
                return mb_strtolower($texto, 'UTF-8');
            }
        }

        if ($numero >= 1900 && $numero <= 2099) {
            return mb_strtolower(AnoEnLetrasEs::format($numero), 'UTF-8');
        }

        if ($numero >= 1 && $numero <= 31) {
            return self::diaCardinal($numero);
        }

        return (string) $numero;
    }

    private static function diaCardinal(int $n): string
    {
        return [
            1 => 'uno',
            2 => 'dos',
            3 => 'tres',
            4 => 'cuatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'siete',
            8 => 'ocho',
            9 => 'nueve',
            10 => 'diez',
            11 => 'once',
            12 => 'doce',
            13 => 'trece',
            14 => 'catorce',
            15 => 'quince',
            16 => 'dieciséis',
            17 => 'diecisiete',
            18 => 'dieciocho',
            19 => 'diecinueve',
            20 => 'veinte',
            21 => 'veintiuno',
            22 => 'veintidós',
            23 => 'veintitrés',
            24 => 'veinticuatro',
            25 => 'veinticinco',
            26 => 'veintiséis',
            27 => 'veintisiete',
            28 => 'veintiocho',
            29 => 'veintinueve',
            30 => 'treinta',
            31 => 'treinta y uno',
        ][$n] ?? (string) $n;
    }

    /**
     * Si el valor es un entero (p. ej. «2025» o «19»), lo pasa a letras; si no, lo deja igual.
     */
    public static function enLetrasDesdeTexto(string $valor): string
    {
        $t = trim($valor);
        if ($t === '') {
            return '';
        }
        if (preg_match('/^\d+$/', $t) === 1) {
            return self::enLetras((int) $t);
        }

        return $t;
    }

    public static function dniConPuntos(string $dni): string
    {
        $digitos = preg_replace('/\D+/', '', $dni) ?? '';
        $len = strlen($digitos);
        if ($len === 8) {
            return substr($digitos, 0, 2).'.'.substr($digitos, 2, 3).'.'.substr($digitos, 5, 3);
        }
        if ($len === 7) {
            return substr($digitos, 0, 1).'.'.substr($digitos, 1, 3).'.'.substr($digitos, 4, 3);
        }

        return trim($dni);
    }
}
