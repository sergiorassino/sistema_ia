<?php

namespace App\Support\CalificacionesInicial;

use App\Models\Matricula;
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
