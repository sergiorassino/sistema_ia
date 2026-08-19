<?php

namespace App\Support;

/**
 * Cálculo del promedio anual para nivel secundario según módulos (Eval 1..8 y JIS 1..2).
 *
 * Política del proyecto (docs/05 §7): `calcular()` solo debe invocarse desde
 * `RecalculoPromedioAnualSecundario::califDesdeFilaModulos()`. Ese helper lo usan
 * la carga manual (`syncPromedioAnual` al guardar `ic01..ic28`) y el recálculo masivo.
 * Planillas PDF, boletines, consultas e importaciones leen `calif` tal cual está en BD.
 *
 * Regla de negocio:
 * - Por cada módulo se toma la MAYOR nota entre sus instancias (Eval: N/R1/R2, JIS: N/R).
 * - Se promedian solo los módulos que tienen al menos una nota numérica parseable.
 * - Si existe al menos un módulo con nota y alguno NO está aprobado, NO se escribe promedio (cadena vacía).
 * - Si todos los módulos con nota están aprobados, el promedio se representa como string con 2 decimales,
 *   salvo que sea 10 (en cuyo caso se muestra "10" sin decimales).
 *
 * `bloqueDesaprobado()` es solo presentación (resaltado en planilla PDF); no calcula ni persiste promedios.
 *
 * Nota: el umbral de aprobación por defecto es 7. Si en el futuro depende del nivel/institución,
 * centralizar la configuración aquí o inyectarla desde el caller.
 */
final class PromedioAnualCalificacionesSecundario
{
    public const DEFAULT_NOTA_MINIMA_APROBACION = 7.0;

    /**
     * Único caller permitido: `RecalculoPromedioAnualSecundario::califDesdeFilaModulos()`.
     *
     * @param  array<string, mixed>  $row  Debe incluir ic01..ic28 como strings (vacío si no hay dato)
     * @return array{promedio: string, aprobado: bool, modulos_con_nota: int, modulos_aprobados: int, modulos_totales: int}
     */
    public static function calcular(array $row, float $notaMinimaAprobacion = self::DEFAULT_NOTA_MINIMA_APROBACION): array
    {
        $modulos = self::gruposModulosCalculo();

        $suma = 0.0;
        // `conNota`: módulos donde hay al menos un valor numérico parseable (N/R1/R2, etc.).
        // `aprobadosConNota`: entre esos módulos, cuántos alcanzan `notaMinimaAprobacion` con su máximo.
        $conNota = 0;
        $aprobadosConNota = 0;

        foreach ($modulos as $grupo) {
            $campos = $grupo[0];
            $vals = [];
            foreach ($campos as $c) {
                // `null` = vacío o no numérico: no participa del máximo ni del promedio.
                $vals[] = self::parseNota($row[$c] ?? null);
            }

            $presentes = array_values(array_filter($vals, fn ($v) => $v !== null));
            if ($presentes === []) {
                // Módulo “sin datos”: no cuenta para el promedio ni para la regla de aprobación parcial.
                continue;
            }

            $conNota++;
            $max = max($presentes);

            if ($max >= $notaMinimaAprobacion) {
                $aprobadosConNota++;
            }

            // Importante: al promedio entra el máximo del módulo (no el promedio interno N/R1/R2).
            $suma += $max;
        }

        $totalModulos = count($modulos);

        if ($conNota === 0) {
            return [
                'promedio' => '',
                'aprobado' => false,
                'modulos_con_nota' => 0,
                'modulos_aprobados' => 0,
                'modulos_totales' => $totalModulos,
            ];
        }

        // Aprobación “estricta entre módulos con nota”: si hay alguno desaprobado, no mostramos promedio (cadena vacía).
        $aprobado = $aprobadosConNota === $conNota;
        $prom = $suma / $conNota;

        return [
            'promedio' => $aprobado ? self::formatNota($prom) : '',
            'aprobado' => $aprobado,
            'modulos_con_nota' => $conNota,
            'modulos_aprobados' => $aprobadosConNota,
            'modulos_totales' => $totalModulos,
        ];
    }

    /**
     * Bloque desaprobado en planilla: hay al menos una nota numérica y ninguna alcanza el mínimo.
     *
     * @param  list<string>  $campos  Columnas del bloque (p. ej. ic01–ic03)
     * @param  array<string, mixed>  $row
     */
    /**
     * Celda de planilla resumen: mejor nota del módulo y estilo (rojo si &lt; mínimo, gris si hubo recuperatorio).
     *
     * @param  list<string>  $campos  Orden legacy: primera columna = nota inicial (N), siguientes = recuperatorios
     * @param  array<string, mixed>  $row
     * @return array{texto: string, rojo: bool, gris: bool}
     */
    public static function celdaMejorNotaModulo(array $campos, array $row, float $notaMinimaAprobacion = self::DEFAULT_NOTA_MINIMA_APROBACION): array
    {
        $vacío = ['texto' => '', 'rojo' => false, 'gris' => false];

        if ($campos === []) {
            return $vacío;
        }

        $porIndice = [];
        foreach ($campos as $i => $c) {
            $v = self::parseNota($row[$c] ?? null);
            if ($v !== null) {
                $porIndice[$i] = $v;
            }
        }

        if ($porIndice === []) {
            return $vacío;
        }

        $max = max($porIndice);
        $notaInicial = self::parseNota($row[$campos[0]] ?? null);
        $usoRecuperatorio = count($porIndice) > 1
            || ($notaInicial === null && count($porIndice) > 0)
            || ($notaInicial !== null && $notaInicial < $notaMinimaAprobacion && $max >= $notaMinimaAprobacion);

        $gris = $usoRecuperatorio;
        $rojo = $max < $notaMinimaAprobacion;

        return [
            'texto' => self::formatNotaCorta($max),
            'rojo' => $rojo,
            'gris' => $gris,
        ];
    }

    /**
     * Columnas `ic01`…`ic28` que participan del promedio anual (Eval 1..8 y JIS 1..2).
     *
     * @return list<string>
     */
    public static function camposIcModulos(): array
    {
        $campos = [];
        foreach (self::gruposModulosCalculo() as $grupo) {
            foreach ($grupo[0] as $c) {
                $campos[] = $c;
            }
        }

        return $campos;
    }

    /**
     * Cada “módulo” es un grupo de columnas legacy (`ic**`) que compiten entre sí (se toma el máximo numérico).
     * El doble array (`[['ic..']]`) deja lugar a futuros subgrupos sin reescribir el `foreach` principal.
     *
     * @return list<list<list<string>>>
     */
    private static function gruposModulosCalculo(): array
    {
        return [
            [['ic01', 'ic02', 'ic03']],
            [['ic04', 'ic05', 'ic06']],
            [['ic07', 'ic08', 'ic09']],
            [['ic10', 'ic11', 'ic12']],
            [['ic13', 'ic14', 'ic15']],
            [['ic16', 'ic17', 'ic18']],
            [['ic19', 'ic20', 'ic21']],
            [['ic22', 'ic23', 'ic24']],
            [['ic25', 'ic26']],
            [['ic27', 'ic28']],
        ];
    }

    /** Formato de promedio / nota para planillas (público; no recalcula promedios de módulos). */
    public static function formatPromedioDisplay(mixed $raw): string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return '';
        }

        $n = str_replace(',', '.', $s);
        if (! is_numeric($n)) {
            return $s;
        }

        return self::formatNota((float) $n);
    }

    public static function bloqueDesaprobado(array $campos, array $row, float $notaMinimaAprobacion = self::DEFAULT_NOTA_MINIMA_APROBACION): bool
    {
        $presentes = [];
        foreach ($campos as $c) {
            $v = self::parseNota($row[$c] ?? null);
            if ($v !== null) {
                $presentes[] = $v;
            }
        }

        if ($presentes === []) {
            return false;
        }

        return max($presentes) < $notaMinimaAprobacion;
    }

    /**
     * Módulos de la planilla impresa (N1..N8 y JIS), con columnas legacy asociadas.
     *
     * @return list<array{label: string, campos: list<string>, slots: int}>
     */
    public static function modulosPlanilla(): array
    {
        $out = [];
        for ($n = 1; $n <= 8; $n++) {
            $base = ($n - 1) * 3 + 1;
            $out[] = [
                'label' => 'N'.$n,
                'campos' => [
                    sprintf('ic%02d', $base),
                    sprintf('ic%02d', $base + 1),
                    sprintf('ic%02d', $base + 2),
                ],
                'slots' => 3,
            ];
        }

        $out[] = ['label' => 'Jis1', 'campos' => ['ic25', 'ic26'], 'slots' => 3];
        $out[] = ['label' => 'Jis2', 'campos' => ['ic27', 'ic28'], 'slots' => 3];

        return $out;
    }

    private static function parseNota(mixed $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    private static function formatNota(float $v): string
    {
        $rounded = round($v, 2, PHP_ROUND_HALF_UP);

        // Regla UI: 10 sin decimales; resto con 2 decimales fijos.
        if (abs($rounded - 10.0) < 1e-9) {
            return '10';
        }

        return number_format($rounded, 2, '.', '');
    }

    private static function formatNotaCorta(float $v): string
    {
        $rounded = round($v, 2, PHP_ROUND_HALF_UP);
        if (abs($rounded - round($rounded)) < 1e-9) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }
}
