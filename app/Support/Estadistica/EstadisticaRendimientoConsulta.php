<?php

namespace App\Support\Estadistica;

use App\Models\Terlec;
use App\Support\NivelSistema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EstadisticaRendimientoConsulta
{
    /**
     * @return Collection<int, array{id: int, cursec: string}>
     */
    public static function cursos(int $idTerlec): Collection
    {
        return DB::table('cursos')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', NivelSistema::SECUNDARIO)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id as id', 'cursec'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'cursec' => (string) ($r->cursec ?? '')]);
    }

    /**
     * @return Collection<int, array{idMaterias: int, idCursos: int, label: string}>
     */
    public static function materiasCursos(int $idTerlec): Collection
    {
        return DB::table('materias as m')
            ->join('cursos as c', function ($join) use ($idTerlec) {
                $join->on('c.Id', '=', 'm.idCursos')
                    ->where('c.idTerlec', '=', $idTerlec)
                    ->where('c.idNivel', '=', NivelSistema::SECUNDARIO);
            })
            ->where('m.idTerlec', $idTerlec)
            ->orderBy('c.orden')
            ->orderBy('c.cursec')
            ->orderBy('m.ord')
            ->orderBy('m.materia')
            ->get(['m.id as idMaterias', 'c.Id as idCursos', 'm.materia', 'c.cursec'])
            ->map(fn ($r) => [
                'idMaterias' => (int) $r->idMaterias,
                'idCursos' => (int) $r->idCursos,
                'label' => trim(($r->cursec ?? '').' — '.($r->materia ?? '')),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    public static function alumnos(int $idTerlec): Collection
    {
        return DB::table('legajos as l')
            ->join('matricula as mat', function ($join) use ($idTerlec) {
                $join->on('mat.idLegajos', '=', 'l.id')
                    ->where('mat.idTerlec', '=', $idTerlec)
                    ->where('mat.idNivel', '=', NivelSistema::SECUNDARIO)
                    ->where('mat.idCondiciones', '=', 1);
            })
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get(['l.id', 'l.apellido', 'l.nombre'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'label' => trim(($r->apellido ?? '').', '.($r->nombre ?? '')),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    public static function profesores(int $idTerlec): Collection
    {
        return DB::table('profesores as p')
            ->join('ppc', 'ppc.idProfesor', '=', 'p.id')
            ->join('materias as m', function ($join) use ($idTerlec) {
                $join->on('m.id', '=', 'ppc.idMateria')
                    ->where('m.idTerlec', '=', $idTerlec)
                    ->where('m.idNivel', '=', NivelSistema::SECUNDARIO);
            })
            ->distinct()
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.apellido', 'p.nombre'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'label' => trim(($r->apellido ?? '').', '.($r->nombre ?? '')),
            ]);
    }

    /**
     * @param  list<int>  $idsLegajos
     * @return array<int, int> idLegajos => cantidad inasistencias
     */
    public static function inasistenciasPorLegajo(int $idTerlec, ?int $idCursos, array $idsLegajos): array
    {
        if ($idsLegajos === []) {
            return [];
        }

        $query = DB::table('matricula as mat')
            ->leftJoin('inasistencias as i', function ($join) {
                $join->on('i.idMatricula', '=', 'mat.id')->where('i.tipo', '<>', 5);
            })
            ->where('mat.idTerlec', $idTerlec)
            ->where('mat.idNivel', NivelSistema::SECUNDARIO)
            ->where('mat.idCondiciones', 1)
            ->whereIn('mat.idLegajos', $idsLegajos)
            ->groupBy('mat.idLegajos')
            ->selectRaw('mat.idLegajos, COUNT(i.id) as inas');

        if ($idCursos !== null && $idCursos > 0) {
            $query->where('mat.idCursos', $idCursos);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[(int) $row->idLegajos] = (int) $row->inas;
        }

        return $out;
    }

    /**
     * @param  list<int>  $idsLegajos
     * @return array<int, bool> idLegajos => tiene previas
     */
    public static function tienePreviasPorLegajo(int $idTerlec, array $idsLegajos): array
    {
        if ($idsLegajos === []) {
            return [];
        }

        $ids = DB::table('calificaciones as cal')
            ->join('matricula as mat', function ($join) {
                $join->on('mat.id', '=', 'cal.idMatricula')->on('mat.idTerlec', '=', 'cal.idTerlec');
            })
            ->where('cal.apro', 1)
            ->where('cal.idTerlec', '<>', $idTerlec)
            ->whereIn('mat.idLegajos', $idsLegajos)
            ->distinct()
            ->pluck('mat.idLegajos')
            ->map(fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($idsLegajos as $idLeg) {
            $out[$idLeg] = in_array($idLeg, $ids, true);
        }

        return $out;
    }

    /**
     * @param  list<int>  $idsLegajos
     * @return array<int, int> idLegajos => idMatricula
     */
    public static function matriculaPorLegajo(int $idTerlec, array $idsLegajos): array
    {
        if ($idsLegajos === []) {
            return [];
        }

        return DB::table('matricula')
            ->where('idTerlec', $idTerlec)
            ->where('idNivel', NivelSistema::SECUNDARIO)
            ->where('idCondiciones', 1)
            ->whereIn('idLegajos', $idsLegajos)
            ->pluck('id', 'idLegajos')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function anoLabel(int $idTerlec): string
    {
        $ano = Terlec::query()->where('id', $idTerlec)->value('ano');

        return $ano !== null ? (string) $ano : '';
    }

    /**
     * @param  list<array{total: int, durante_anio: int, diciembre: int, febrero: int, pendientes?: int}>  $filas
     * @return array{labels: list<string>, durante: list<float>, diciembre: list<float>, febrero: list<float>, pendientes: list<float>}
     */
    public static function porcentajesApilados(array $filas, callable $labelFn): array
    {
        $labels = [];
        $durante = [];
        $diciembre = [];
        $febrero = [];
        $pendientes = [];

        foreach ($filas as $r) {
            $labels[] = $labelFn($r);
            $tot = (int) ($r['total'] ?? 0);
            if ($tot > 0) {
                $durante[] = round($r['durante_anio'] / $tot * 100, 1);
                $diciembre[] = round($r['diciembre'] / $tot * 100, 1);
                $febrero[] = round($r['febrero'] / $tot * 100, 1);
                $pendientes[] = round(($r['pendientes'] ?? 0) / $tot * 100, 1);
            } else {
                $durante[] = 0;
                $diciembre[] = 0;
                $febrero[] = 0;
                $pendientes[] = 0;
            }
        }

        return compact('labels', 'durante', 'diciembre', 'febrero', 'pendientes');
    }

    /**
     * @param  array{total: int, aprobados_durante_anio: int, aprobados_diciembre: int, aprobados_febrero: int, pendientes?: int}  $resumen
     * @return list<float>
     */
    public static function porcentajesResumen(array $resumen): array
    {
        $tot = (int) ($resumen['total'] ?? 0);
        if ($tot <= 0) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return [
            round($resumen['aprobados_durante_anio'] / $tot * 100, 1),
            round($resumen['aprobados_diciembre'] / $tot * 100, 1),
            round($resumen['aprobados_febrero'] / $tot * 100, 1),
            round(($resumen['pendientes'] ?? 0) / $tot * 100, 1),
        ];
    }
}
