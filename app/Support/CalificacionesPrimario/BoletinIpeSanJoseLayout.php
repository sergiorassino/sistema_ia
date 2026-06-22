<?php

namespace App\Support\CalificacionesPrimario;

/**
 * Distribución del IPE San José (A4 apaisado).
 *
 * Ciclos escolares (a partir de 2026): 1.º ciclo = grados 1–2, 2.º = 3–4, 3.º = 5–6.
 * Columnas: curriculares (oficiales), extracurriculares (institucionales en ABM) e inasistencias.
 */
final class BoletinIpeSanJoseLayout
{
    /** Ancho útil de la matriz (mm): encabezado menos columna de etiquetas. */
    public const ANCHO_TABLA_MATERIAS = 208.0;

    public const MAX_COLS_CURRICULARES = 14;

    public const MAX_COLS_EXTRACURRICULARES = 2;

    public const COLS_INASIST = 2;

    /**
     * Ciclo escolar del IPE según el grado numérico del curso (`cursos.c`).
     */
    public static function cicloEscolarDesdeGrado(int $grado): int
    {
        if ($grado <= 2) {
            return 1;
        }

        if ($grado <= 4) {
            return 2;
        }

        return 3;
    }

    public static function normalizarCicloEscolar(int $cicloEscolar): int
    {
        return max(1, min(3, $cicloEscolar));
    }

    /**
     * @return array{oficial: int, instit: int, inasist: int, total: int, offsetInstit: int, offsetInasist: int}
     */
    public static function slots(): array
    {
        $oficial = self::MAX_COLS_CURRICULARES;
        $instit = self::MAX_COLS_EXTRACURRICULARES;
        $inasist = self::COLS_INASIST;

        return [
            'oficial' => $oficial,
            'instit' => $instit,
            'inasist' => $inasist,
            'total' => $oficial + $instit + $inasist,
            'offsetInstit' => $oficial,
            'offsetInasist' => $oficial + $instit,
        ];
    }

    /** Materia marcada como extracurricular en Gestión de asignaturas del año (`esInstitucional`). */
    public static function materiaEsExtracurricular(object $materia): bool
    {
        return (int) ($materia->esInstitucional ?? 0) === 1;
    }

    public static function anchoCeldaMm(): float
    {
        return self::ANCHO_TABLA_MATERIAS / self::slots()['total'];
    }
}
