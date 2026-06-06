<?php

namespace App\Support;

/**
 * Coloquios de recuperación (diciembre / febrero) — nivel secundario.
 *
 * Elegibilidad: alumno regular con fila en `calificaciones` para la materia y
 * (algún módulo desaprobado en esa materia o TEA activo en el curso).
 */
final class CalificacionesColoquioSecundario
{
    public const PERIODO_DICIEMBRE = 'dic';

    public const PERIODO_FEBRERO = 'feb';

    /** @return list<string> */
    public static function periodos(): array
    {
        return [self::PERIODO_DICIEMBRE, self::PERIODO_FEBRERO];
    }

    public static function normalizarPeriodo(?string $value): string
    {
        $v = strtolower(trim((string) $value));

        return in_array($v, self::periodos(), true) ? $v : self::PERIODO_DICIEMBRE;
    }

    public static function etiquetaPeriodo(string $periodo): string
    {
        return match (self::normalizarPeriodo($periodo)) {
            self::PERIODO_FEBRERO => 'Febrero',
            default => 'Diciembre',
        };
    }

    /** Texto del encabezado «Alumnos condición» en actas volantes. */
    public static function tituloCondicionColoquio(string $periodo): string
    {
        return match (self::normalizarPeriodo($periodo)) {
            self::PERIODO_FEBRERO => 'Coloquio de Febrero',
            default => 'Coloquio de Diciembre',
        };
    }

    /**
     * @param  array<string, mixed>  $row  Fila `calificaciones` (ic01..ic28, tea, etc.)
     */
    public static function tieneAlgunBloqueDesaprobado(array $row, float $notaMinima = PromedioAnualCalificacionesSecundario::DEFAULT_NOTA_MINIMA_APROBACION): bool
    {
        foreach (PromedioAnualCalificacionesSecundario::modulosPlanilla() as $mod) {
            if (PromedioAnualCalificacionesSecundario::bloqueDesaprobado($mod['campos'], $row, $notaMinima)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function motivoElegibilidad(array $row, bool $teaEnCurso): string
    {
        if ($teaEnCurso || ((int) ($row['tea'] ?? 0)) === 1) {
            return 'TEA';
        }

        if (self::tieneAlgunBloqueDesaprobado($row)) {
            return 'Módulos desaprobados';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function esElegible(array $row, bool $teaEnCurso): bool
    {
        return $teaEnCurso
            || ((int) ($row['tea'] ?? 0)) === 1
            || self::tieneAlgunBloqueDesaprobado($row);
    }

    /**
     * Si el alumno debe figurar en la grilla o contar para el select de materias.
     *
     * Criterio base: rinde coloquio (módulo con nota &lt; 7 o TEA). No se oculta por tener
     * ya cargada una nota aprobatoria en Dic o Feb (puede editarse).
     *
     * En febrero no se listan quienes ya aprobaron diciembre (Dic ≥ 7): van a febrero solo
     * quienes no cerraron en diciembre.
     *
     * @param  array<string, mixed>  $rowModulos  ic01..ic28 y tea (entero 0/1)
     */
    public static function apareceEnListadoColoquio(
        string $periodo,
        array $rowModulos,
        string $dic,
        bool $teaEnCurso,
    ): bool {
        if (! self::esElegible($rowModulos, $teaEnCurso)) {
            return false;
        }

        if (self::normalizarPeriodo($periodo) === self::PERIODO_FEBRERO
            && self::notaColoquioAprobada($dic)) {
            return false;
        }

        return true;
    }

    /** Misma regla que la grilla: el select de materias sigue al período activo. */
    public static function cuentaParaMateriaConCarga(
        string $periodo,
        array $rowModulos,
        string $dic,
        bool $teaEnCurso,
    ): bool {
        return self::apareceEnListadoColoquio($periodo, $rowModulos, $dic, $teaEnCurso);
    }

    public static function parseNotaColoquio(mixed $raw): ?float
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

    public static function notaColoquioAprobada(
        mixed $nota,
        float $notaMinima = PromedioAnualCalificacionesSecundario::DEFAULT_NOTA_MINIMA_APROBACION,
    ): bool {
        $n = self::parseNotaColoquio($nota);

        return $n !== null && $n >= $notaMinima;
    }

    /** Valor a persistir en `calificaciones.calif` cuando el coloquio aprueba. */
    public static function califDesdeNotaColoquio(mixed $nota): string
    {
        $n = self::parseNotaColoquio($nota);
        if ($n === null) {
            return '';
        }

        return PromedioAnualCalificacionesSecundario::formatPromedioDisplay((string) $n);
    }
}
