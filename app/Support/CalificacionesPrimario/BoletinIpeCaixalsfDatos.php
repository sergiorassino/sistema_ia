<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Matricula;

/**
 * Datos del IPE primario — variante Caixal SF (A4 vertical, 1ª/2ª etapa).
 *
 * Layout legacy: hasta 16 espacios curriculares + filas de inasistencias desde `matricula`.
 */
final class BoletinIpeCaixalsfDatos
{
    public const MAX_MATERIAS = 16;

    /**
     * @return array<string, mixed>
     */
    public static function buildForMatriculaEnContextoEscolar(int $idMatricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;

        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($idMatricula);
        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        return self::buildDesdeMatricula($mat, $etapa);
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa: int,
     *     ano: int,
     *     titulo: string,
     *     subtitulo: string,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     cicloLabel: string,
     *     filas: list<array{materia: string, ic01: string, ic02: string, ic03: string, indice: int}>,
     *     just1: string,
     *     just2: string,
     *     justAf: string,
     *     inju1: string,
     *     inju2: string,
     *     injuAf: string,
     *     obsEtapa: string,
     *     obsAnual: string
     * }
     */
    public static function buildDesdeMatricula(Matricula $matricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;
        $matricula->loadMissing(['legajo', 'curso.curplan']);
        $form = CalificacionesPrimarioDatos::cargarFormulario($matricula);

        $filas = [];
        $indice = 0;
        foreach ($form['materias'] as $m) {
            if ($indice >= self::MAX_MATERIAS) {
                break;
            }
            $indice++;
            $idMaterias = (int) $m->id;
            $nota = $form['notas'][$idMaterias] ?? ['ic01' => '', 'ic02' => '', 'ic03' => ''];

            $filas[] = [
                'materia' => (string) ($m->materia ?? ''),
                'ic01' => (string) ($nota['ic01'] ?? ''),
                'ic02' => (string) ($nota['ic02'] ?? ''),
                'ic03' => (string) ($nota['ic03'] ?? ''),
                'indice' => $indice,
            ];
        }

        $just1 = self::formatoInasistencia($matricula->just1 ?? null);
        $just2 = self::formatoInasistencia($matricula->just2 ?? null);
        $inju1 = self::formatoInasistencia($matricula->inju1 ?? null);
        $inju2 = self::formatoInasistencia($matricula->inju2 ?? null);

        $legajo = $matricula->legajo;
        $apellido = trim((string) ($legajo?->apellido ?? ''));
        $nombre = trim((string) ($legajo?->nombre ?? ''));
        $alumnoLinea = trim($apellido.' '.$nombre);

        $grado = (int) ($matricula->curso?->c ?? 0);
        $cicloLabel = self::cicloLabelDesdeGradoYCurso($grado, (string) ($form['cursoLabel'] ?? ''));

        $titulo = 'INFORME DE PROGRESO ESCOLAR';
        $subtitulo = $cicloLabel !== '' ? $titulo.' - '.$cicloLabel : $titulo;

        $ctx = schoolCtx();

        return [
            'ok' => true,
            'etapa' => $etapa,
            'ano' => (int) ($ctx->terlecAno() ?? now()->year),
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'alumnoLinea' => $alumnoLinea,
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursoLabel' => $form['cursoLabel'],
            'cicloLabel' => $cicloLabel,
            'filas' => $filas,
            'just1' => $just1,
            'just2' => $just2,
            'justAf' => self::sumaInasistencias($just1, $just2),
            'inju1' => $inju1,
            'inju2' => $inju2,
            'injuAf' => self::sumaInasistencias($inju1, $inju2),
            'obsEtapa' => $etapa === 1 ? $form['obs1'] : $form['obs2'],
            'obsAnual' => $form['obsAnual'],
        ];
    }

    /**
     * Ciclo del IPE: 1-2 primer, 3-4 segundo, 5-6 tercero; fallback por nombre de curso.
     */
    public static function cicloLabelDesdeGradoYCurso(int $ordenCurso, string $cursec): string
    {
        if ($ordenCurso >= 1 && $ordenCurso <= 2) {
            return 'PRIMER CICLO';
        }
        if ($ordenCurso >= 3 && $ordenCurso <= 4) {
            return 'SEGUNDO CICLO';
        }
        if ($ordenCurso >= 5 && $ordenCurso <= 6) {
            return 'TERCER CICLO';
        }

        $upper = mb_strtoupper(trim($cursec), 'UTF-8');
        if ($upper === '') {
            return '';
        }

        if (str_contains($upper, 'PRIMERO') || str_contains($upper, 'SEGUNDO')) {
            return 'PRIMER CICLO';
        }
        if (str_contains($upper, 'TERCERO') || str_contains($upper, 'CUARTO')) {
            return 'SEGUNDO CICLO';
        }
        if (str_contains($upper, 'QUINTO') || str_contains($upper, 'SEXTO')) {
            return 'TERCER CICLO';
        }

        return '';
    }

    private static function formatoInasistencia(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        if (is_numeric($texto) && (float) $texto == 0.0) {
            return '0';
        }

        return $texto;
    }

    private static function sumaInasistencias(string $a, string $b): string
    {
        if ($a === '' && $b === '') {
            return '';
        }

        if (is_numeric($a) && is_numeric($b)) {
            $suma = (float) $a + (float) $b;
            if ($suma == (int) $suma) {
                return (string) (int) $suma;
            }

            return rtrim(rtrim(sprintf('%.2f', $suma), '0'), '.');
        }

        if ($a !== '' && $b === '') {
            return $a;
        }
        if ($a === '' && $b !== '') {
            return $b;
        }

        return trim($a.' + '.$b);
    }
}
