<?php

namespace App\Support\PortalDocente;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Facades\DB;

/**
 * Cuaderno de seguimiento áulico — Menú de Docentes (asignación ppc + alcance).
 */
final class CuadernoSeguimientoAulicoDocente
{
    public static function nivelEsSecundario(): bool
    {
        return CalificacionesDocenteSecundario::nivelEsSecundario();
    }

    public static function abortSiNoEsSecundario(): void
    {
        CalificacionesDocenteSecundario::abortSiNoEsSecundario();
    }

    public static function abortSiNoHabilitadoEnTenant(): void
    {
        abort_unless(tenantPortalDocenteCuadernoSeguimientoAulico(), 404);
    }

    public static function abortSiProfesorSinMateria(int $idMateria, int $idCurso): void
    {
        CalificacionesDocenteSecundario::abortSiProfesorSinMateria($idMateria, $idCurso);
    }

    /**
     * Materias a cargo del docente (ppc) con año lectivo de la materia.
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

    /** @return list<int> */
    public static function idsCondicionesRegulares(): array
    {
        return ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );
    }

    /**
     * Alumnos regulares matriculados en el curso del contexto de sesión.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function alumnosDelCurso(int $idCurso): \Illuminate\Support\Collection
    {
        return DB::table('matricula')
            ->join('legajos', 'legajos.id', '=', 'matricula.idLegajos')
            ->where('matricula.idNivel', schoolCtx()->idNivel)
            ->where('matricula.idTerlec', schoolCtx()->idTerlec)
            ->where('matricula.idCursos', $idCurso)
            ->whereIn('matricula.idCondiciones', self::idsCondicionesRegulares())
            ->orderBy('legajos.apellido')
            ->orderBy('legajos.nombre')
            ->get([
                'matricula.id',
                'matricula.idLegajos',
                'legajos.apellido',
                'legajos.nombre',
                'legajos.dni',
            ]);
    }
}
