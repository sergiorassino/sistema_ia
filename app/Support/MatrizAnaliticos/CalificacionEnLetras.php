<?php

namespace App\Support\MatrizAnaliticos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calificación en letras para analítico (tabla enletras + códigos especiales + numéricas).
 *
 * La tabla legacy `enletras` suele traer solo códigos cualitativos (adeud, aprob, A, S…).
 * Las notas numéricas (6, 6.00, 7.50…) se convierten a «seis c/00», «siete c/50», «diez» si no hay fila en la tabla.
 */
final class CalificacionEnLetras
{
    /** @var array<string, string>|null */
    private static ?array $tabla = null;

    public static function resolver(string $calif): string
    {
        $c = mb_strtolower(trim($calif));
        if ($c === '') {
            return '';
        }

        $especial = match ($c) {
            'aprob' => 'APROBADO',
            'adeud' => 'ADEUDA',
            'elimi' => 'ELIMINADO',
            'excep' => 'EXCEPCIONAL',
            'apequ' => 'APROB. POR EQUIV.',
            'a-ams' => 'A-AMS',
            'a-as' => 'A-AS',
            default => null,
        };
        if ($especial !== null) {
            return $especial;
        }

        $desdeTabla = self::buscarEnTabla($calif);
        if ($desdeTabla !== null) {
            return $desdeTabla;
        }

        $numerica = self::numericaALetras($calif);
        if ($numerica !== null) {
            return $numerica;
        }

        return mb_strtoupper($calif, 'UTF-8');
    }

    /**
     * Nota numérica escolar (0–10) → letras en minúsculas.
     * Formato: «seis c/50», «siete c/00»; el 10 es solo «diez».
     * Null si no es numérica o está fuera de rango.
     */
    public static function numericaALetras(string $calif): ?string
    {
        $raw = trim($calif);
        if ($raw === '') {
            return null;
        }

        $normalizado = str_replace(',', '.', $raw);
        if (! is_numeric($normalizado)) {
            return null;
        }

        $valor = round((float) $normalizado, 2);
        if ($valor < 0 || $valor > 10) {
            return null;
        }

        $entero = (int) floor($valor + 1e-9);
        $centesimas = (int) round(($valor - $entero) * 100);
        if ($centesimas >= 100) {
            $entero++;
            $centesimas = 0;
        }

        if ($entero > 10) {
            return null;
        }

        // El 10 no lleva centésimas en el impreso (ni «diez c/00»).
        if ($entero === 10 && $centesimas === 0) {
            return 'diez';
        }

        return self::cardinal0a99($entero).' c/'.str_pad((string) $centesimas, 2, '0', STR_PAD_LEFT);
    }

    /** @internal tests */
    public static function olvidarCache(): void
    {
        self::$tabla = null;
    }

    private static function buscarEnTabla(string $calif): ?string
    {
        $tabla = self::tabla();
        if ($tabla === []) {
            return null;
        }

        $trim = trim($calif);
        $lower = mb_strtolower($trim, 'UTF-8');
        if (isset($tabla[$lower])) {
            return $tabla[$lower];
        }
        if (isset($tabla[$trim])) {
            return $tabla[$trim];
        }

        $normalizado = str_replace(',', '.', $trim);
        if (! is_numeric($normalizado)) {
            return null;
        }

        $valor = (float) $normalizado;
        $candidatos = [
            (string) $valor,
            number_format($valor, 2, '.', ''),
            number_format($valor, 1, '.', ''),
        ];
        if (abs($valor - (int) $valor) < 1e-9) {
            $candidatos[] = (string) (int) $valor;
        }

        foreach ($candidatos as $clave) {
            $kLower = mb_strtolower($clave, 'UTF-8');
            if (isset($tabla[$kLower])) {
                return $tabla[$kLower];
            }
            if (isset($tabla[$clave])) {
                return $tabla[$clave];
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private static function tabla(): array
    {
        if (self::$tabla !== null) {
            return self::$tabla;
        }

        self::$tabla = [];
        try {
            if (! Schema::hasTable('enletras')) {
                return self::$tabla;
            }

            $rows = DB::table('enletras')->select(['nota', 'enLetras'])->get();
        } catch (\Throwable) {
            return self::$tabla;
        }

        foreach ($rows as $row) {
            $nota = trim((string) ($row->nota ?? ''));
            $letras = trim((string) ($row->enLetras ?? ''));
            if ($nota === '' || $letras === '') {
                continue;
            }
            self::$tabla[mb_strtolower($nota, 'UTF-8')] = $letras;
            self::$tabla[$nota] = $letras;
        }

        return self::$tabla;
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
