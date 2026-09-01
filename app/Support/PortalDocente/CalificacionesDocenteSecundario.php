<?php

namespace App\Support\PortalDocente;

use Illuminate\Support\Facades\DB;

/**
 * Calificaciones secundario en el Menú de Docentes (asignación ppc + alcance).
 */
final class CalificacionesDocenteSecundario
{
    public static function nivelEsSecundario(): bool
    {
        return str_contains(mb_strtolower((string) schoolCtx()->nivelNombre()), 'secundari');
    }

    public static function abortSiNoEsSecundario(): void
    {
        abort_unless(self::nivelEsSecundario(), 404);
    }

    public static function profesorTieneMateria(int $idProfesor, int $idMateria, int $idCurso): bool
    {
        if ($idProfesor < 1 || $idMateria < 1 || $idCurso < 1) {
            return false;
        }

        $ctx = schoolCtx();

        return DB::table('ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.id', $idMateria)
            ->where('m.idCursos', $idCurso)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->exists();
    }

    public static function abortSiProfesorSinMateria(int $idMateria, int $idCurso): void
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        abort_unless(
            self::profesorTieneMateria($idProfesor, $idMateria, $idCurso),
            404,
        );
    }

    /**
     * True si el docente tiene ppc en alguna materia del curso donde el estudiante está matriculado
     * en el ciclo/nivel de sesión.
     */
    public static function profesorTieneAlumnoEnCursosAsignados(int $idProfesor, int $idLegajo): bool
    {
        if ($idProfesor < 1 || $idLegajo < 1) {
            return false;
        }

        $ctx = schoolCtx();

        return DB::table('matricula as m')
            ->join('materias as mat', function ($join) {
                $join->on('mat.idCursos', '=', 'm.idCursos')
                    ->on('mat.idTerlec', '=', 'm.idTerlec');
            })
            ->join('ppc', 'ppc.idMateria', '=', 'mat.id')
            ->where('m.idLegajos', $idLegajo)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->where('mat.idNivel', (int) $ctx->idNivel)
            ->where('mat.idTerlec', (int) $ctx->idTerlec)
            ->where('ppc.idProfesor', $idProfesor)
            ->exists();
    }

    /**
     * Materias a cargo del docente según ppc (ciclo y nivel de sesión).
     *
     * @return list<object{
     *     idMateria: int,
     *     materia: string,
     *     abrev: string|null,
     *     idCurso: int,
     *     cursoLabel: string,
     *     anoLectivo: int|null
     * }>
     */
    public static function materiasAsignadas(int $idProfesor): array
    {
        if ($idProfesor < 1) {
            return [];
        }

        $ctx = schoolCtx();

        $rows = DB::table('ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->leftJoin('terlec as t', 't.id', '=', 'm.idTerlec')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->orderByRaw('COALESCE(c.orden, 9999) asc')
            ->orderBy('c.Id')
            ->orderBy('m.ord')
            ->orderBy('m.id')
            ->get([
                'm.id as idMateria',
                'm.materia',
                'm.abrev',
                'm.idCursos as idCurso',
                'c.cursec',
                't.ano as anoLectivo',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $sec = trim((string) ($r->cursec ?? ''));
            $label = $sec !== '' ? $sec : ('Curso '.(int) $r->idCurso);

            $out[] = (object) [
                'idMateria' => (int) $r->idMateria,
                'materia' => trim((string) ($r->materia ?? '')),
                'abrev' => $r->abrev !== null ? trim((string) $r->abrev) : null,
                'idCurso' => (int) $r->idCurso,
                'cursoLabel' => $label !== '' ? $label : ('Curso '.(int) $r->idCurso),
                'anoLectivo' => $r->anoLectivo !== null ? (int) $r->anoLectivo : null,
            ];
        }

        return $out;
    }
}
