<?php

namespace App\Support\CalificacionesInicial;

use App\Models\Curso;
use App\Models\Ento;
use App\Models\Matricula;
use App\Support\CalificacionesPrimario\PlanillaCalificacionesPrimarioDatos;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del Informe de Progreso Escolar — nivel inicial (layout provincial).
 */
final class InformeProgresoInicialDatos
{
    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa?: int,
     *     nombreEtapa?: string,
     *     ano?: int,
     *     insti?: string,
     *     direccion?: string,
     *     localidad?: string,
     *     departamento?: string,
     *     escudoProvincia?: ?string,
     *     alumno?: array<string, mixed>,
     *     materias?: list<array<string, mixed>>,
     *     inasistencias?: array{just1e: string, just2e: string, inju1e: string, inju2e: string}
     * }
     */
    public static function buildForMatriculaEnContextoEscolar(int $idMatricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;
        $ctx = schoolCtx();

        $mat = Matricula::query()
            ->with(['legajo', 'curso.turnoClase'])
            ->where('id', $idMatricula)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereNull('fechaBaja')
            ->first();

        if ($mat === null) {
            return ['ok' => false, 'error' => 'Matrícula no encontrada en el contexto activo.'];
        }

        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );
        if (! in_array((int) $mat->idCondiciones, $idsCondiciones, true)) {
            return ['ok' => false, 'error' => 'La matrícula no está en condición regular.'];
        }

        return self::buildDesdeMatricula($mat, $etapa);
    }

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     etapa: int,
     *     nombreEtapa: string,
     *     ano: int,
     *     insti: string,
     *     direccion: string,
     *     localidad: string,
     *     departamento: string,
     *     escudoProvincia: ?string,
     *     alumno: array<string, mixed>,
     *     materias: list<array<string, mixed>>,
     *     inasistencias: array{just1e: string, just2e: string, inju1e: string, inju2e: string}
     * }
     */
    public static function buildDesdeMatricula(Matricula $matricula, int $etapa): array
    {
        $etapa = $etapa === 2 ? 2 : 1;
        CalificacionesInicialObservacionesDatos::abortSiColumnasInexistentes();

        $ctx = schoolCtx();
        $idMatricula = (int) $matricula->id;
        $idCurso = (int) $matricula->idCursos;

        $ento = Ento::query()
            ->where('idNivel', (int) $ctx->idNivel)
            ->first(['insti', 'direccion', 'localidad', 'departamento']);

        $curso = $matricula->curso;
        if ($curso === null || (int) $curso->Id !== $idCurso) {
            $curso = Curso::query()
                ->with('turnoClase')
                ->where('Id', $idCurso)
                ->where('idNivel', (int) $ctx->idNivel)
                ->where('idTerlec', (int) $ctx->idTerlec)
                ->first();
        }

        $cursec = trim((string) ($curso?->cursec ?? ''));
        $legajo = $matricula->legajo;

        $fechnaci = '';
        if ($legajo?->fechnaci !== null) {
            $fechnaci = $legajo->fechnaci instanceof Carbon
                ? $legajo->fechnaci->format('d/m/Y')
                : Carbon::parse($legajo->fechnaci)->format('d/m/Y');
        }

        $edadSala = $cursec;
        if ($edadSala !== '' && mb_strlen($edadSala) > 1) {
            $edadSala = mb_substr($edadSala, 0, -1).' AÑOS';
        }

        $alumno = [
            'apellido' => trim((string) ($legajo?->apellido ?? '')),
            'nombre' => trim((string) ($legajo?->nombre ?? '')),
            'dni' => trim((string) ($legajo?->dni ?? '')),
            'cursec' => $cursec,
            'edadSala' => $edadSala,
            'ln_ciudad' => trim((string) ($legajo?->ln_ciudad ?? '')),
            'fechnaci' => $fechnaci,
            'nacion' => trim((string) ($legajo?->nacion ?? '')),
            'ln_provincia' => trim((string) ($legajo?->ln_provincia ?? '')),
            'callenum' => trim((string) ($legajo?->callenum ?? '')),
            'barrio' => trim((string) ($legajo?->barrio ?? '')),
            'localidad' => trim((string) ($legajo?->localidad ?? '')),
            'nroMatricula' => trim((string) ($matricula->nroMatricula ?? '')),
            'turno' => self::turnoDesdeCurso($curso),
        ];

        $materiasRows = DB::table('materias')
            ->where('idCursos', $idCurso)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'ord', 'materia']);

        $inasistencias = [
            'just1e' => '',
            'just2e' => '',
            'inju1e' => '',
            'inju2e' => '',
        ];

        $materiasPdf = [];
        foreach ($materiasRows as $row) {
            $nombreMateria = trim((string) $row->materia);
            $idMateria = (int) $row->id;
            $ord = (int) $row->ord;

            $obs = self::observacionesPorMateria($idMatricula, $idMateria, $ord);
            $indicadores = self::indicadoresPorMateria($idMateria);

            $materiaUpper = mb_strtoupper($nombreMateria);
            if ($materiaUpper === 'JUSTIFICADAS') {
                $inasistencias['just1e'] = (string) ($obs['etapa1'] ?? '');
                $inasistencias['just2e'] = (string) ($obs['etapa2'] ?? '');

                continue;
            }
            if ($materiaUpper === 'INJUSTIFICADAS') {
                $inasistencias['inju1e'] = (string) ($obs['etapa1'] ?? '');
                $inasistencias['inju2e'] = (string) ($obs['etapa2'] ?? '');

                continue;
            }

            $materiasPdf[] = [
                'materia' => $nombreMateria,
                'indicador1' => $indicadores[1] ?? '',
                'indicador2' => $indicadores[2] ?? '',
                'etapa1' => (string) ($obs['etapa1'] ?? ''),
                'etapa2' => (string) ($obs['etapa2'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'etapa' => $etapa,
            'nombreEtapa' => $etapa === 1 ? 'PRIMERA ETAPA' : 'SEGUNDA ETAPA',
            'ano' => (int) ($ctx->terlecAno() ?? now()->year),
            'insti' => trim((string) ($ento?->insti ?? '')),
            'direccion' => trim((string) ($ento?->direccion ?? '')),
            'localidad' => trim((string) ($ento?->localidad ?? '')),
            'departamento' => trim((string) ($ento?->departamento ?? '')),
            'escudoProvincia' => PlanillaCalificacionesPrimarioDatos::rutaEscudoProvincia(),
            'alumno' => $alumno,
            'materias' => $materiasPdf,
            'inasistencias' => $inasistencias,
        ];
    }

    /**
     * @return array{etapa1: string, etapa2: string}
     */
    private static function observacionesPorMateria(int $idMatricula, int $idMateria, int $ord): array
    {
        $vacío = ['etapa1' => '', 'etapa2' => ''];

        if (Schema::hasTable('calificaciones')
            && Schema::hasColumn('calificaciones', 'obs01')
            && Schema::hasColumn('calificaciones', 'obs02')) {
            $fila = DB::table('calificaciones')
                ->where('idMatricula', $idMatricula)
                ->where('ord', $ord)
                ->first(['obs01', 'obs02']);

            if ($fila !== null) {
                return [
                    'etapa1' => (string) ($fila->obs01 ?? ''),
                    'etapa2' => (string) ($fila->obs02 ?? ''),
                ];
            }
        }

        if (Schema::hasTable('infoxobse')) {
            $query = DB::table('infoxobse')->where('idMatricula', $idMatricula);
            if (Schema::hasColumn('infoxobse', 'idMaterias')) {
                $query->where('idMaterias', $idMateria);
            }
            $fila = $query->first(['etapa1', 'etapa2']);
            if ($fila !== null) {
                return [
                    'etapa1' => (string) ($fila->etapa1 ?? ''),
                    'etapa2' => (string) ($fila->etapa2 ?? ''),
                ];
            }
        }

        return $vacío;
    }

    /**
     * @return array<int, string>
     */
    private static function indicadoresPorMateria(int $idMateria): array
    {
        if (! CalificacionesInicialIndicadoresCatalogo::tablaDisponible()) {
            return [1 => '', 2 => ''];
        }

        return CalificacionesInicialIndicadoresDatos::textosPorEtapa($idMateria);
    }

    private static function turnoDesdeCurso(?Curso $curso): string
    {
        if ($curso === null) {
            return '';
        }

        if (! $curso->relationLoaded('turnoClase') && (int) ($curso->idTurnoClase ?? 0) > 0) {
            $curso->load('turnoClase');
        }

        $nombreTurno = trim((string) ($curso->turnoClase?->nombre ?? ''));
        if ($nombreTurno !== '') {
            return $nombreTurno;
        }

        $cursec = (string) ($curso->cursec ?? '');
        if (mb_strlen($cursec) >= 9) {
            $seccion = mb_substr($cursec, 8, 1);

            return $seccion === '4' ? 'Mañana' : 'Tarde';
        }

        return '';
    }
}
