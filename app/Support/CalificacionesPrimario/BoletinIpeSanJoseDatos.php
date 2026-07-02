<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Matricula;
use Illuminate\Support\Collection;

/**
 * Datos del Informe de Progreso Escolar — variante San José (matriz horizontal, A4 apaisado).
 */
final class BoletinIpeSanJoseDatos
{
    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     ano?: int,
     *     titulo?: string,
     *     alumnoLinea?: string,
     *     dni?: string,
     *     cursoLabel?: string,
     *     cicloEscolar?: int,
     *     columnas?: list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}>,
     *     obs1?: string,
     *     obs2?: string,
     *     obsAnual?: string
     * }
     */
    public static function buildForMatriculaEnContextoEscolar(int $idMatricula): array
    {
        $mat = CalificacionesPrimarioDatos::matriculaEnContexto($idMatricula);
        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        return self::buildDesdeMatricula($mat);
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     ano: int,
     *     titulo: string,
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     cicloEscolar: int,
     *     columnas: list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}>,
     *     obs1: string,
     *     obs2: string,
     *     obsAnual: string
     * }
     */
    public static function buildDesdeMatricula(Matricula $matricula): array
    {
        $matricula->loadMissing(['legajo', 'curso.curplan', 'terlec']);
        $form = CalificacionesPrimarioDatos::cargarFormulario($matricula);

        $grado = (int) ($matricula->curso?->c ?? 0);
        $cicloEscolar = BoletinIpeSanJoseLayout::cicloEscolarDesdeGrado($grado);
        $columnas = self::armarColumnasBoletin($form);

        $legajo = $matricula->legajo;
        $apellido = trim((string) ($legajo?->apellido ?? ''));
        $nombre = trim((string) ($legajo?->nombre ?? ''));
        $alumnoLinea = trim($apellido.' '.$nombre);

        $ano = (int) ($matricula->terlec?->ano ?? 0);
        if ($ano <= 0) {
            $ctx = studentCtx()->isValid() ? studentCtx() : schoolCtx();
            $ano = (int) ($ctx->terlecAno() ?? now()->year);
        }

        return [
            'ok' => true,
            'ano' => $ano,
            'titulo' => 'INFORME DE PROGRESO ESCOLAR',
            'alumnoLinea' => $alumnoLinea,
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursoLabel' => $form['cursoLabel'],
            'cicloEscolar' => $cicloEscolar,
            'columnas' => $columnas,
            'obs1' => $form['obs1'],
            'obs2' => $form['obs2'],
            'obsAnual' => $form['obsAnual'],
        ];
    }

    /**
     * Columnas del PDF: oficiales (izq.), institucionales (centro) e inasistencias (vacías en encabezado).
     *
     * @param  array{materias: Collection<int, object>, notas: array<int, array{ic01: string, ic02: string, ic03: string}>}  $form
     * @return list<array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}>
     */
    private static function armarColumnasBoletin(array $form): array
    {
        $slots = BoletinIpeSanJoseLayout::slots();
        $columnas = array_fill(0, $slots['total'], self::columnaVacia());

        $materiasOrdenadas = CalificacionesPrimarioCatalogo::ordenarMateriasParaColumnas($form['materias']);

        $curriculares = $materiasOrdenadas
            ->filter(fn (object $m): bool => ! BoletinIpeSanJoseLayout::materiaEsExtracurricular($m))
            ->values();
        $extracurriculares = $materiasOrdenadas
            ->filter(fn (object $m): bool => BoletinIpeSanJoseLayout::materiaEsExtracurricular($m))
            ->values();

        foreach ($curriculares as $i => $m) {
            if ($i >= $slots['oficial']) {
                break;
            }
            $columnas[$i] = self::columnaDesdeMateria($m, $form);
        }

        foreach ($extracurriculares as $i => $m) {
            if ($i >= $slots['instit']) {
                break;
            }
            $columnas[$slots['offsetInstit'] + $i] = self::columnaDesdeMateria($m, $form);
        }

        return $columnas;
    }

    /**
     * @param  array{materias: Collection<int, object>, notas: array<int, array{ic01: string, ic02: string, ic03: string}>}  $form
     * @return array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}
     */
    private static function columnaDesdeMateria(object $m, array $form): array
    {
        $idMaterias = (int) $m->id;
        $nota = $form['notas'][$idMaterias] ?? ['ic01' => '', 'ic02' => '', 'ic03' => ''];

        return [
            'ord' => (int) $m->ord,
            'materia' => trim((string) ($m->materia ?? '')),
            'ic01' => (string) ($nota['ic01'] ?? ''),
            'ic02' => (string) ($nota['ic02'] ?? ''),
            'ic03' => (string) ($nota['ic03'] ?? ''),
        ];
    }

    /**
     * @return array{ord: int, materia: string, ic01: string, ic02: string, ic03: string}
     */
    private static function columnaVacia(): array
    {
        return [
            'ord' => 0,
            'materia' => '',
            'ic01' => '',
            'ic02' => '',
            'ic03' => '',
        ];
    }
}
