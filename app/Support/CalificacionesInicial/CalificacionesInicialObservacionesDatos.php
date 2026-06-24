<?php

namespace App\Support\CalificacionesInicial;

use App\Models\Curso;
use App\Models\Matricula;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura y persistencia de observaciones por alumno y espacio curricular (nivel inicial).
 *
 * Las observaciones se guardan en `calificaciones.obs01` / `obs02` (etapas 1 y 2), una fila por matrícula × ord de materia.
 */
final class CalificacionesInicialObservacionesDatos
{
    public const MAX_CARACTERES = 1500;

    /** @return list<int> */
    public static function etapasCarga(): array
    {
        $disponibles = CalificacionesInicialIndicadoresCatalogo::etapasDisponibles();
        $etapas = [];
        foreach ([1, 2] as $n) {
            if (in_array($n, $disponibles, true)) {
                $etapas[] = $n;
            }
        }

        return $etapas !== [] ? $etapas : [1, 2];
    }

    public static function abortSiColumnasInexistentes(): void
    {
        abort_unless(
            Schema::hasTable('calificaciones')
                && Schema::hasColumn('calificaciones', 'obs01')
                && Schema::hasColumn('calificaciones', 'obs02'),
            503,
            'La tabla calificaciones no tiene las columnas obs01/obs02 necesarias para observaciones de nivel inicial.'
        );
    }

    public static function campoObsPorEtapa(int $etapa): string
    {
        return $etapa === 2 ? 'obs02' : 'obs01';
    }

    /** @return list<string> */
    public static function camposObservacion(): array
    {
        $campos = [];
        foreach (self::etapasCarga() as $etapa) {
            $campos[] = self::campoObsPorEtapa($etapa);
        }

        return $campos;
    }

    public static function esCampoObservacion(string $campo): bool
    {
        return in_array($campo, self::camposObservacion(), true);
    }

    /**
     * @return object{id: int, idCursos: int, ord: int, materia: string, abrev: ?string, cursoLabel: string}|null
     */
    public static function materiaEnContexto(int $idMateria, int $idNivel, int $idTerlec): ?object
    {
        return CalificacionesInicialIndicadoresDatos::materiaEnContexto($idMateria, $idNivel, $idTerlec);
    }

    public static function matriculaEnCursoDeMateria(int $idMatricula, int $idCursoMateria): ?Matricula
    {
        $ctx = schoolCtx();
        if (! $ctx->isValid()) {
            return null;
        }

        return Matricula::query()
            ->with('legajo')
            ->where('id', $idMatricula)
            ->where('idCursos', $idCursoMateria)
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->whereNull('fechaBaja')
            ->first();
    }

    /**
     * @return array{
     *     idCalificacion: ?int,
     *     observaciones: array<int, string>,
     *     indicadores: array<int, list<array{ord: int, indicador: string}>>,
     *     alumnoLinea: string,
     *     materiaNombre: string,
     *     cursoLabel: string
     * }
     */
    public static function cargarFormulario(Matricula $matricula, object $materia): array
    {
        self::abortSiColumnasInexistentes();

        $idMatricula = (int) $matricula->id;
        $ord = (int) $materia->ord;
        $idMateria = (int) $materia->id;

        $fila = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id', 'obs01', 'obs02']);

        $observaciones = [];
        foreach (self::etapasCarga() as $etapa) {
            $col = self::campoObsPorEtapa($etapa);
            $observaciones[$etapa] = $fila !== null ? (string) ($fila->{$col} ?? '') : '';
        }

        $indicadores = [];
        if (CalificacionesInicialIndicadoresCatalogo::tablaDisponible()) {
            $porEtapa = CalificacionesInicialIndicadoresDatos::filasPorEtapa($idMateria);
            foreach (self::etapasCarga() as $etapa) {
                $lista = [];
                foreach ($porEtapa[$etapa] ?? [] as $filaInd) {
                    $texto = trim((string) ($filaInd['indicador'] ?? ''));
                    if ($texto !== '') {
                        $lista[] = [
                            'ord' => (int) ($filaInd['ord'] ?? 0),
                            'indicador' => $texto,
                        ];
                    }
                }
                $indicadores[$etapa] = $lista;
            }
        } else {
            foreach (self::etapasCarga() as $etapa) {
                $indicadores[$etapa] = [];
            }
        }

        $legajo = $matricula->legajo;
        $alumnoLinea = trim(((string) ($legajo?->apellido ?? '')).' '.((string) ($legajo?->nombre ?? '')));

        return [
            'idCalificacion' => $fila !== null ? (int) $fila->id : null,
            'observaciones' => $observaciones,
            'indicadores' => $indicadores,
            'alumnoLinea' => $alumnoLinea !== '' ? $alumnoLinea : '—',
            'materiaNombre' => (string) $materia->materia,
            'cursoLabel' => (string) $materia->cursoLabel,
        ];
    }

    /**
     * Grilla por espacio curricular: matrículas regulares del curso con obs01/obs02 de la materia.
     *
     * @return array{
     *     ord: int,
     *     materiaLabel: string,
     *     cursoLabel: string,
     *     etapas: list<int>,
     *     filas: list<array{
     *         idMatricula: int,
     *         idCalificacion: ?int,
     *         alumno: string,
     *         observaciones: array<int, string>
     *     }>
     * }
     */
    public static function cargarGrillaMateria(int $cursoId, int $materiaId): array
    {
        self::abortSiColumnasInexistentes();

        $ctx = schoolCtx();
        $etapas = self::etapasCarga();
        $campos = self::camposObservacion();

        $curso = Curso::query()
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->first(['Id', 'cursec', 'c', 's', 'orden', 'idTurnoClase']);

        if ($curso === null) {
            return [
                'ord' => 0,
                'materiaLabel' => '—',
                'cursoLabel' => '—',
                'etapas' => $etapas,
                'filas' => [],
            ];
        }

        $materia = self::materiaEnContexto($materiaId, (int) $ctx->idNivel, (int) $ctx->idTerlec);
        if ($materia === null || (int) $materia->idCursos !== $cursoId) {
            return [
                'ord' => 0,
                'materiaLabel' => '—',
                'cursoLabel' => $curso->nombreParaListado(),
                'etapas' => $etapas,
                'filas' => [],
            ];
        }

        $ord = (int) $materia->ord;
        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES,
        );

        $matriculas = Matricula::query()
            ->with('legajo')
            ->join('legajos as l', 'l.id', '=', 'matricula.idLegajos')
            ->where('matricula.idCursos', $cursoId)
            ->where('matricula.idNivel', (int) $ctx->idNivel)
            ->where('matricula.idTerlec', (int) $ctx->idTerlec)
            ->whereIn('matricula.idCondiciones', $idsCondiciones)
            ->whereNull('matricula.fechaBaja')
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->select('matricula.*')
            ->get();

        $idsMatricula = $matriculas->map(fn (Matricula $m) => (int) $m->id)->all();
        $porMatricula = [];

        if ($idsMatricula !== []) {
            $columnasSelect = array_merge(['id', 'idMatricula'], $campos);
            $filasCalif = DB::table('calificaciones')
                ->whereIn('idMatricula', $idsMatricula)
                ->where('ord', $ord)
                ->get($columnasSelect);

            foreach ($filasCalif as $fila) {
                $porMatricula[(int) $fila->idMatricula] = $fila;
            }
        }

        $filas = [];
        foreach ($matriculas as $mat) {
            $idMatricula = (int) $mat->id;
            $fila = $porMatricula[$idMatricula] ?? null;
            $observaciones = [];
            foreach ($etapas as $etapa) {
                $col = self::campoObsPorEtapa($etapa);
                $observaciones[$etapa] = $fila !== null ? (string) ($fila->{$col} ?? '') : '';
            }

            $legajo = $mat->legajo;
            $filas[] = [
                'idMatricula' => $idMatricula,
                'idCalificacion' => $fila !== null ? (int) $fila->id : null,
                'alumno' => trim(((string) ($legajo?->apellido ?? '')).', '.((string) ($legajo?->nombre ?? ''))),
                'observaciones' => $observaciones,
            ];
        }

        return [
            'ord' => $ord,
            'materiaLabel' => trim((string) ($materia->materia ?? '')) !== '' ? (string) $materia->materia : '—',
            'cursoLabel' => $curso->nombreParaListado(),
            'etapas' => $etapas,
            'filas' => $filas,
        ];
    }

    public static function guardarCelda(
        Matricula $matricula,
        object $materia,
        string $campo,
        string $valor,
    ): void {
        self::abortSiColumnasInexistentes();

        if (! self::esCampoObservacion($campo)) {
            abort(400);
        }

        $idMatricula = (int) $matricula->id;
        $ord = (int) $materia->ord;

        $existente = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id']);

        if ($existente !== null) {
            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->where('idMatricula', $idMatricula)
                ->update([$campo => $valor]);

            return;
        }

        $payload = [];
        foreach (self::camposObservacion() as $col) {
            $payload[$col] = $col === $campo ? $valor : '';
        }

        DB::table('calificaciones')->insert(array_merge([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => (int) $materia->id,
            'ord' => $ord,
        ], $payload));
    }

    /**
     * @param  array<int, string>  $observacionesPorEtapa
     */
    public static function guardar(Matricula $matricula, object $materia, array $observacionesPorEtapa): void
    {
        self::abortSiColumnasInexistentes();

        $idMatricula = (int) $matricula->id;
        $ord = (int) $materia->ord;
        $payload = [];

        foreach (self::etapasCarga() as $etapa) {
            $col = self::campoObsPorEtapa($etapa);
            $payload[$col] = (string) ($observacionesPorEtapa[$etapa] ?? '');
        }

        $existente = DB::table('calificaciones')
            ->where('idMatricula', $idMatricula)
            ->where('ord', $ord)
            ->first(['id']);

        if ($existente !== null) {
            DB::table('calificaciones')
                ->where('id', (int) $existente->id)
                ->where('idMatricula', $idMatricula)
                ->update($payload);

            return;
        }

        DB::table('calificaciones')->insert(array_merge([
            'idMatricula' => $idMatricula,
            'idLegajos' => (int) $matricula->idLegajos,
            'idTerlec' => (int) $matricula->idTerlec,
            'idCursos' => (int) $matricula->idCursos,
            'idMaterias' => (int) $materia->id,
            'ord' => $ord,
        ], $payload));
    }
}
