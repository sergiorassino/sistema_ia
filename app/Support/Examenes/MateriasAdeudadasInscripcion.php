<?php

namespace App\Support\Examenes;

use Illuminate\Support\Facades\DB;

final class MateriasAdeudadasInscripcion
{
    /**
     * Materias adeudadas del alumno (`apro = 1`) con datos para inscripción a examen.
     *
     * @return list<array{
     *     id: int,
     *     materia: string,
     *     curso: string,
     *     ano_lectivo: int|string,
     *     condicion: string,
     *     inscri: int
     * }>
     */
    public static function filas(int $idLegajos, int $idNivel): array
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return [];
        }

        $raw = DB::table('calificaciones as c')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->join('terlec as t', 't.id', '=', 'c.idTerlec')
            ->where('c.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->where('c.apro', 1)
            ->orderByDesc('t.ano')
            ->orderBy('m.materia')
            ->select([
                'c.id',
                'c.condAdeuda',
                'c.inscri',
                'm.materia',
                't.ano as ano_lectivo',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ])
            ->get();

        $out = [];
        foreach ($raw as $r) {
            $materia = trim((string) ($r->materia ?? ''));
            if ($materia === '') {
                continue;
            }

            $out[] = [
                'id' => (int) $r->id,
                'materia' => $materia,
                'curso' => MateriasAdeudadasCargaManual::cursoLabelDesdeFila($r),
                'ano_lectivo' => $r->ano_lectivo ?? '',
                'condicion' => strtoupper(trim((string) ($r->condAdeuda ?? ''))),
                'inscri' => (int) ($r->inscri ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return 'ok'|'no_encontrada'|'condicion_invalida'
     */
    public static function actualizarCondicion(
        int $idCalificacion,
        int $idLegajos,
        int $idNivel,
        string $condicion,
    ): string {
        $condNorm = MateriasAdeudadasFiltros::normalizeCondicion($condicion);
        if ($condNorm === null) {
            return 'condicion_invalida';
        }

        if (! self::filaAdeudadaDelAlumno($idCalificacion, $idLegajos, $idNivel)) {
            return 'no_encontrada';
        }

        DB::table('calificaciones')
            ->where('id', $idCalificacion)
            ->update(['condAdeuda' => $condNorm]);

        return 'ok';
    }

    /**
     * @return 'ok'|'no_encontrada'
     */
    public static function actualizarInscripcion(
        int $idCalificacion,
        int $idLegajos,
        int $idNivel,
        bool $inscripto,
    ): string {
        if (! self::filaAdeudadaDelAlumno($idCalificacion, $idLegajos, $idNivel)) {
            return 'no_encontrada';
        }

        DB::table('calificaciones')
            ->where('id', $idCalificacion)
            ->update(['inscri' => $inscripto ? 1 : 0]);

        return 'ok';
    }

    private static function filaAdeudadaDelAlumno(int $idCalificacion, int $idLegajos, int $idNivel): bool
    {
        if ($idCalificacion < 1 || $idLegajos < 1 || $idNivel < 1) {
            return false;
        }

        return DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.id', $idCalificacion)
            ->where('c.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->where('c.apro', 1)
            ->exists();
    }
}
