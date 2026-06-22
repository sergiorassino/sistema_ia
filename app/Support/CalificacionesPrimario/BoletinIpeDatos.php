<?php

namespace App\Support\CalificacionesPrimario;

use App\Models\Matricula;

/**
 * Datos del Informe de Progreso Escolar (IPE) — nivel primario.
 */
final class BoletinIpeDatos
{
    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa?: int,
     *     ano?: int,
     *     titulo?: string,
     *     alumnoLinea?: string,
     *     dni?: string,
     *     cursoLabel?: string,
     *     filas?: list<array{materia: string, ic01: string, ic02: string, ic03: string, indice: int}>,
     *     obsEtapa?: string,
     *     obsAnual?: string
     * }
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
     *     alumnoLinea: string,
     *     dni: string,
     *     cursoLabel: string,
     *     filas: list<array{materia: string, ic01: string, ic02: string, ic03: string, indice: int}>,
     *     obsEtapa: string,
     *     obsAnual: string
     * }
     */
    public static function buildDesdeMatricula(Matricula $matricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;
        $form = CalificacionesPrimarioDatos::cargarFormulario($matricula);

        $filas = [];
        $indice = 0;
        foreach ($form['materias'] as $m) {
            $indice++;
            $idMaterias = (int) $m->id;
            $nota = $form['notas'][$idMaterias] ?? ['ic01' => '', 'ic02' => '', 'ic03' => ''];

            $filas[] = [
                'materia' => (string) $m->materia,
                'ic01' => (string) ($nota['ic01'] ?? ''),
                'ic02' => (string) ($nota['ic02'] ?? ''),
                'ic03' => (string) ($nota['ic03'] ?? ''),
                'indice' => $indice,
            ];
        }

        $legajo = $matricula->legajo;
        $apellido = trim((string) ($legajo?->apellido ?? ''));
        $nombre = trim((string) ($legajo?->nombre ?? ''));
        $alumnoLinea = trim($apellido.' '.$nombre);

        $ctx = schoolCtx();

        return [
            'ok' => true,
            'etapa' => $etapa,
            'ano' => (int) ($ctx->terlecAno() ?? now()->year),
            'titulo' => 'INFORME DE PROGRESO ESCOLAR',
            'alumnoLinea' => $alumnoLinea,
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursoLabel' => $form['cursoLabel'],
            'filas' => $filas,
            'obsEtapa' => $etapa === 1 ? $form['obs1'] : $form['obs2'],
            'obsAnual' => $form['obsAnual'],
        ];
    }
}
