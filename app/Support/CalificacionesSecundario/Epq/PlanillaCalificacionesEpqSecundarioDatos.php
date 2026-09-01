<?php

namespace App\Support\CalificacionesSecundario\Epq;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use App\Support\PortalDocente\PortalDocenteContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos para la planilla de calificaciones secundario EPQ (una hoja PDF por materia).
 */
final class PlanillaCalificacionesEpqSecundarioDatos
{
    /**
     * @return array{insti: string, ano: int}
     */
    public static function contextoPdf(): array
    {
        $ctx = schoolCtx();
        $header = schoolPdfHeaderData();

        return [
            'insti' => (string) ($header['insti'] ?? ''),
            'ano' => (int) ($ctx->terlecAno() ?? now()->year),
        ];
    }

    /**
     * Materias del nivel/ciclo para el selector (opcionalmente solo asignadas en portal docente).
     *
     * @return Collection<int, object{id: int, materia: string, ord: int, idCursos: int, cursec: string}>
     */
    public static function materiasDisponibles(bool $soloAsignadasPortal = false): Collection
    {
        $ctx = schoolCtx();

        $query = DB::table('materias as m')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->orderByRaw('COALESCE(c.orden, 9999) asc')
            ->orderBy('c.Id', 'asc')
            ->orderBy('m.ord', 'asc')
            ->orderBy('m.id', 'asc')
            ->select([
                'm.id',
                'm.materia',
                'm.ord',
                'm.idCursos',
                'c.cursec',
            ]);

        if ($soloAsignadasPortal) {
            $ids = self::idsMateriasAsignadasPortal();
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('m.id', $ids);
        } elseif (PortalDocenteContext::esActivo()) {
            $ids = self::idsMateriasAsignadasPortal();
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('m.id', $ids);
        }

        return $query->get()->map(fn ($r) => (object) [
            'id' => (int) $r->id,
            'materia' => trim((string) ($r->materia ?? '')),
            'ord' => (int) ($r->ord ?? 0),
            'idCursos' => (int) ($r->idCursos ?? 0),
            'cursec' => trim((string) ($r->cursec ?? '')),
        ]);
    }

    /**
     * Materias de un curso para el selector de planilla (orden ascendente por `ord`).
     *
     * @return Collection<int, object{id: int, materia: string, ord: int, idCursos: int, cursec: string}>
     */
    public static function materiasDelCurso(int $idCurso, bool $soloAsignadasPortal = false): Collection
    {
        if ($idCurso < 1) {
            return collect();
        }

        $ctx = schoolCtx();

        $query = DB::table('materias as m')
            ->join('cursos as c', 'c.Id', '=', 'm.idCursos')
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->where('m.idCursos', $idCurso)
            ->orderBy('m.ord', 'asc')
            ->orderBy('m.id', 'asc')
            ->select([
                'm.id',
                'm.materia',
                'm.ord',
                'm.idCursos',
                'c.cursec',
            ]);

        if ($soloAsignadasPortal) {
            $ids = self::idsMateriasAsignadasPortal();
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('m.id', $ids);
        }

        return $query->get()->map(fn ($r) => (object) [
            'id' => (int) $r->id,
            'materia' => trim((string) ($r->materia ?? '')),
            'ord' => (int) ($r->ord ?? 0),
            'idCursos' => (int) ($r->idCursos ?? 0),
            'cursec' => trim((string) ($r->cursec ?? '')),
        ]);
    }

    /**
     * @param  list<int>  $idMaterias  IDs en orden de impresión
     * @return list<array<string, mixed>>
     */
    public static function buildHojas(array $idMaterias): array
    {
        if ($idMaterias === []) {
            return [];
        }

        $soloPortal = request()->routeIs('portalDocente.*');
        $permitidas = self::materiasDisponibles($soloPortal)
            ->keyBy(fn ($m) => (int) $m->id);

        $hojas = [];
        foreach ($idMaterias as $idMateria) {
            $meta = $permitidas->get($idMateria);
            if ($meta === null) {
                continue;
            }

            $hojas[] = [
                'idMateria' => $idMateria,
                'materia' => (string) $meta->materia,
                'curso' => (string) $meta->cursec,
                'profesores' => self::lineaProfesores($idMateria),
                'alumnos' => self::alumnosConNotas($idMateria),
            ];
        }

        return $hojas;
    }

    /**
     * @param  list<int>  $idMaterias
     * @param  Collection<int, object{id: int}>  $materiasPermitidas
     * @return list<int>
     */
    public static function ordenarIdsMaterias(array $idMaterias, Collection $materiasPermitidas): array
    {
        $set = array_flip($idMaterias);
        $ordenados = [];
        foreach ($materiasPermitidas as $m) {
            $id = (int) $m->id;
            if (isset($set[$id]) && ! in_array($id, $ordenados, true)) {
                $ordenados[] = $id;
            }
        }

        return $ordenados;
    }

    /**
     * @param  list<int>  $allowedIds
     * @return list<int>
     */
    public static function resolverIdsMaterias(string $param, array $allowedIds): array
    {
        $allowed = array_flip($allowedIds);
        $out = [];
        foreach (explode(',', $param) as $parte) {
            $id = (int) trim($parte);
            if ($id > 0 && isset($allowed[$id]) && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        if (count($out) > 200) {
            return [];
        }

        return $out;
    }

    /** @return list<int> */
    private static function idsMateriasAsignadasPortal(): array
    {
        $idProfesor = (int) (schoolCtx()->idProfesor ?? 0);
        if ($idProfesor < 1) {
            return [];
        }

        $ctx = schoolCtx();

        return DB::table('ppc')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->where('ppc.idProfesor', $idProfesor)
            ->where('m.idNivel', (int) $ctx->idNivel)
            ->where('m.idTerlec', (int) $ctx->idTerlec)
            ->pluck('m.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function lineaProfesores(int $idMateria): string
    {
        $filas = DB::table('ppc')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->where('ppc.idMateria', $idMateria)
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['p.apellido', 'p.nombre']);

        if ($filas->isEmpty()) {
            return 'Prof: ';
        }

        $partes = $filas->map(function ($r): string {
            $ap = trim((string) ($r->apellido ?? ''));
            $no = trim((string) ($r->nombre ?? ''));

            return trim($ap.' '.$no);
        })->filter(fn ($s) => $s !== '')->all();

        return 'Prof: '.implode(' - ', $partes).($partes !== [] ? ' - ' : '');
    }

    /**
     * Alumnos con fila en `calificaciones` para la materia (legacy INNER JOIN, idCondiciones &lt; 5).
     *
     * @return list<array{nro: int, nombre: string, ic07: string, ic14: string, ic21: string, ic28: string, ic31: string, ic32: string, ic33: string, ic34: string, dic: string, feb: string}>
     */
    private static function alumnosConNotas(int $idMateria): array
    {
        $idsCondiciones = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::TODOS,
        );
        $campos = CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA;

        $filas = DB::table('calificaciones as cal')
            ->join('legajos as l', 'l.id', '=', 'cal.idLegajos')
            ->join('matricula as mat', 'mat.id', '=', 'cal.idMatricula')
            ->where('cal.idMaterias', $idMateria)
            ->whereIn('mat.idCondiciones', $idsCondiciones)
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->get(array_merge(['l.apellido', 'l.nombre'], $campos));

        $alumnos = [];
        $nro = 0;
        foreach ($filas as $r) {
            $nro++;
            $apellido = trim((string) ($r->apellido ?? ''));
            $nombre = trim((string) ($r->nombre ?? ''));

            $item = [
                'nro' => $nro,
                'nombre' => trim($apellido.' '.$nombre),
            ];
            foreach ($campos as $campo) {
                $item[$campo] = trim((string) ($r->{$campo} ?? ''));
            }
            $alumnos[] = $item;
        }

        return $alumnos;
    }
}
