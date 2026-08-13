<?php

namespace App\Support\MatriculaWeb;

use App\Models\Curso;
use App\Support\Cuotas\GeneracionMasivaCuotasConsulta;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alumnos regulares del ciclo activo para edición de bloqmatr / bloqadmi.
 */
final class BloqueosMatriculaConsulta
{
    public const POR_PAGINA = 50;

    /**
     * @return Collection<int, array{id: int, etiqueta: string}>
     */
    public static function opcionesCurso(): Collection
    {
        return GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->map(fn (Curso $curso): array => [
                'id' => (int) $curso->Id,
                'etiqueta' => GeneracionMasivaCuotasConsulta::etiquetaCursoConNivel($curso),
            ])
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     idMatricula: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     curso: string,
     *     bloqmatr: bool,
     *     bloqadmi: bool
     * }>
     */
    public static function paginar(int $idCurso = 0, int $porPagina = self::POR_PAGINA): LengthAwarePaginator
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        if ($idTerlec < 1) {
            return self::paginadorVacio($porPagina);
        }

        $idCurso = self::validarIdCurso($idCurso);

        $query = self::queryBase($idTerlec, $idCurso);

        return $query
            ->paginate(max(10, min(100, $porPagina)))
            ->through(static function (object $row): array {
                return [
                    'idMatricula' => (int) $row->idMatricula,
                    'apellido' => trim((string) ($row->apellido ?? '')),
                    'nombre' => trim((string) ($row->nombre ?? '')),
                    'dni' => trim((string) ($row->dni ?? '')),
                    'curso' => self::cursoLabelDesdeFila($row),
                    'bloqmatr' => (bool) ($row->bloqmatr ?? false),
                    'bloqadmi' => (bool) ($row->bloqadmi ?? false),
                ];
            });
    }

    /**
     * IDs de matrícula del listado actual (mismo filtro de curso que la grilla).
     *
     * @return Collection<int, int>
     */
    public static function idsDelListado(int $idCurso = 0): Collection
    {
        $idTerlec = (int) schoolCtx()->idTerlec;
        if ($idTerlec < 1) {
            return collect();
        }

        $idCurso = self::validarIdCurso($idCurso);

        return self::queryBase($idTerlec, $idCurso)
            ->pluck('idMatricula')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
    }

    public static function matriculaEnAlcance(int $idMatricula): ?object
    {
        if ($idMatricula < 1) {
            return null;
        }

        $idTerlec = (int) schoolCtx()->idTerlec;
        if ($idTerlec < 1) {
            return null;
        }

        return self::queryBase($idTerlec, null)
            ->where('m.id', $idMatricula)
            ->select([
                'm.id as idMatricula',
                'm.bloqmatr',
                'm.bloqadmi',
            ])
            ->first();
    }

    private static function queryBase(int $idTerlec, ?int $idCurso): Builder
    {
        $query = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->join('condiciones as c', 'c.id', '=', 'm.idCondiciones')
            ->leftJoin('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->leftJoin('curplan as cp', 'cp.id', '=', 'cu.idCurPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->where('m.idTerlec', $idTerlec)
            ->where('m.idCondiciones', 1)
            ->where('c.proteg', '!=', 99)
            ->where(function (Builder $q): void {
                $q->whereNull('m.fechaBaja')
                    ->orWhere('m.fechaBaja', '0000-00-00')
                    ->orWhere('m.fechaBaja', '');
            });

        SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'm.idNivel');

        if ($idCurso !== null && $idCurso > 0) {
            $query->where('m.idCursos', $idCurso);
        }

        return $query
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->orderBy('m.id')
            ->select([
                'm.id as idMatricula',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'm.bloqmatr',
                'm.bloqadmi',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
            ]);
    }

    private static function validarIdCurso(int $idCurso): int
    {
        if ($idCurso < 1) {
            return 0;
        }

        $permitidos = GeneracionMasivaCuotasConsulta::cursosEnContexto()
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return $permitidos->has($idCurso) ? $idCurso : 0;
    }

    /** @return LengthAwarePaginator<int, never> */
    private static function paginadorVacio(int $porPagina): LengthAwarePaginator
    {
        return DB::table('matricula')->whereRaw('1 = 0')->paginate(max(10, min(100, $porPagina)));
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $sec = trim((string) ($r->cursec ?? ''));
        if ($sec !== '') {
            return $sec;
        }

        $nombrePlan = trim((string) ($r->curPlanCurso ?? ''));
        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($nombrePlan !== '') {
            return $extras->isNotEmpty()
                ? $nombrePlan.' · '.$extras->implode(' · ')
                : $nombrePlan;
        }

        if ($extras->isNotEmpty()) {
            return $extras->implode(' · ');
        }

        return '';
    }
}
