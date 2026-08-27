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

    /**
     * Primera palabra (tipo: Instituto, Colegio, Escuela…) sin negrita; el resto en título.
     *
     * @return array{tipo: string, nombre: string}
     */
    public static function partesNombreInstitucion(string $insti): array
    {
        $t = trim(preg_replace('/\s+/u', ' ', $insti) ?? '');
        if ($t === '') {
            return ['tipo' => '', 'nombre' => ''];
        }

        $palabras = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
        if ($palabras === false || $palabras === []) {
            return ['tipo' => '', 'nombre' => ''];
        }

        $tipo = self::tituloPalabra((string) $palabras[0]);
        $resto = array_slice($palabras, 1);
        if ($resto === []) {
            return ['tipo' => $tipo, 'nombre' => ''];
        }

        $nombre = implode(' ', array_map([self::class, 'tituloPalabraNombre'], $resto));

        return ['tipo' => $tipo, 'nombre' => $nombre];
    }

    public static function nacidoSegunSexo(mixed $sexo, ?string $etiqueta = null): string
    {
        return self::esFemenino($sexo, $etiqueta) ? 'nacida' : 'nacido';
    }

    public static function acreditadoSegunSexo(mixed $sexo, ?string $etiqueta = null): string
    {
        return self::esFemenino($sexo, $etiqueta) ? 'acreditada' : 'acreditado';
    }

    public static function esFemenino(mixed $sexo, ?string $etiqueta = null): bool
    {
        $texto = mb_strtolower(trim((string) ($etiqueta ?? '')), 'UTF-8');
        if ($texto !== '') {
            if (str_contains($texto, 'femen') || $texto === 'f' || $texto === 'mujer') {
                return true;
            }
            if (str_contains($texto, 'masc') || $texto === 'm' || str_contains($texto, 'varon') || str_contains($texto, 'varón') || $texto === 'hombre') {
                return false;
            }
        }

        if (is_numeric($sexo)) {
            return (int) $sexo === 1;
        }

        $s = mb_strtolower(trim((string) $sexo), 'UTF-8');

        return str_contains($s, 'femen') || $s === 'f' || $s === 'mujer';
    }

    private static function tituloPalabra(string $palabra): string
    {
        $lower = mb_strtolower($palabra, 'UTF-8');
        $primera = mb_substr($lower, 0, 1, 'UTF-8');
        $resto = mb_substr($lower, 1, null, 'UTF-8');

        return mb_strtoupper($primera, 'UTF-8').$resto;
    }

    private static function tituloPalabraNombre(string $palabra): string
    {
        $lower = mb_strtolower($palabra, 'UTF-8');
        $particulas = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'da', 'do'];
        if (in_array($lower, $particulas, true)) {
            return $lower;
        }

        return self::tituloPalabra($palabra);
    }
}
