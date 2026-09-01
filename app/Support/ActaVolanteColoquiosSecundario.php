<?php

namespace App\Support;

use App\Models\Curso;
use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actas volantes de coloquio (diciembre / febrero) — una hoja por materia con alumnos elegibles.
 */
final class ActaVolanteColoquiosSecundario
{
    /** Filas de la grilla (alumnos + vacías), como el diseño legacy en FPDF. */
    public const FILAS_POR_ACTA = 40;

    /**
     * Materias del curso que tienen al menos un alumno elegible para el período de coloquio.
     *
     * @return Collection<int, object{id: int, materia: string|null, ord: mixed}>
     */
    public static function materiasConAlumnosElegibles(int $cursoId, string $periodo): Collection
    {
        $periodo = CalificacionesColoquioSecundario::normalizarPeriodo($periodo);
        $ids = self::idsMateriasConAlumnosElegibles($cursoId, $periodo);
        if ($ids === []) {
            return collect();
        }

        $ctx = schoolCtx();

        return DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', (int) $ctx->idTerlec)
            ->where('idCursos', $cursoId)
            ->whereIn('id', $ids)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'materia', 'ord']);
    }

    /**
     * @param  Collection<int, object{id: int}>  $materiasPermitidas  Orden de impresión
     * @return list<int>
     */
    public static function resolverIdsMaterias(string $materiasParam, Collection $materiasPermitidas): array
    {
        $allowedById = $materiasPermitidas->keyBy(fn (object $m) => (int) $m->id);

        $parsed = collect(explode(',', $materiasParam))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $out = [];
        foreach ($parsed as $id) {
            if ($allowedById->has($id) && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        if (count($out) > 200) {
            return [];
        }

        $ordenados = [];
        foreach ($materiasPermitidas as $m) {
            $id = (int) $m->id;
            if (in_array($id, $out, true)) {
                $ordenados[] = $id;
            }
        }

        return $ordenados;
    }

    /**
     * @param  list<int>  $materiaIds  IDs de `materias.id` en el orden deseado de impresión
     * @return array{
     *     condicionLabel: string,
     *     periodo: string,
     *     ano: int|null,
     *     actas: list<array{
     *         cursoLabel: string,
     *         materiaLabel: string,
     *         filas: list<array{nro: int, dni: string, nombre: string}>
     *     }>
     * }
     */
    public static function build(string $periodo, int $cursoId, array $materiaIds): array
    {
        $periodo = CalificacionesColoquioSecundario::normalizarPeriodo($periodo);
        $ctx = schoolCtx();

        if ($cursoId <= 0 || $materiaIds === []) {
            return [
                'condicionLabel' => CalificacionesColoquioSecundario::tituloCondicionColoquio($periodo),
                'periodo' => $periodo,
                'ano' => $ctx->terlecAno(),
                'actas' => [],
            ];
        }

        $materiasPermitidas = self::materiasConAlumnosElegibles($cursoId, $periodo);
        $materiaIds = self::resolverIdsMaterias(implode(',', $materiaIds), $materiasPermitidas);
        if ($materiaIds === []) {
            return [
                'condicionLabel' => CalificacionesColoquioSecundario::tituloCondicionColoquio($periodo),
                'periodo' => $periodo,
                'ano' => $ctx->terlecAno(),
                'actas' => [],
            ];
        }

        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );
        $idTerlec = (int) $ctx->idTerlec;
        $actas = [];

        $curso = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->first(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if (! $curso) {
            return [
                'condicionLabel' => CalificacionesColoquioSecundario::tituloCondicionColoquio($periodo),
                'periodo' => $periodo,
                'ano' => $ctx->terlecAno(),
                'actas' => [],
            ];
        }

        $cursoLabel = $curso->nombreParaListado();
        $teaPorLegajo = self::legajosConTeaEnCurso($cursoId, $idTerlec);
        $materiaIdsSet = array_flip($materiaIds);

        $materias = DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $cursoId)
            ->whereIn('id', $materiaIds)
            ->orderBy('ord')
            ->orderBy('id')
            ->get(['id', 'materia']);

        if ($materias->isEmpty()) {
            return [
                'condicionLabel' => CalificacionesColoquioSecundario::tituloCondicionColoquio($periodo),
                'periodo' => $periodo,
                'ano' => $ctx->terlecAno(),
                'actas' => [],
            ];
        }

        $califs = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('matricula as m', function ($join) use ($cursoId, $idTerlec, $ctx, $idsCondicionesRegulares) {
                $join->on('m.idLegajos', '=', 'l.id')
                    ->where('m.idCursos', $cursoId)
                    ->where('m.idTerlec', $idTerlec)
                    ->where('m.idNivel', (int) $ctx->idNivel)
                    ->whereIn('m.idCondiciones', $idsCondicionesRegulares)
                    ->whereNull('m.fechaBaja');
            })
            ->where('c.idTerlec', $idTerlec)
            ->where('c.idCursos', $cursoId)
            ->whereIn('c.idMaterias', $materiaIds)
            ->orderByRaw('COALESCE(c.ord, 9999) asc')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->get([
                'c.idMaterias',
                'c.idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'c.dic',
                'c.tea',
                'c.ic01', 'c.ic02', 'c.ic03', 'c.ic04', 'c.ic05', 'c.ic06', 'c.ic07', 'c.ic08', 'c.ic09', 'c.ic10',
                'c.ic11', 'c.ic12', 'c.ic13', 'c.ic14', 'c.ic15', 'c.ic16', 'c.ic17', 'c.ic18', 'c.ic19', 'c.ic20',
                'c.ic21', 'c.ic22', 'c.ic23', 'c.ic24', 'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
            ]);

        $porMateria = [];
        foreach ($califs as $r) {
            $idMateria = (int) $r->idMaterias;
            if (! isset($materiaIdsSet[$idMateria])) {
                continue;
            }

            $idLegajo = (int) $r->idLegajos;
            $rowModulos = self::rowModulosDesdeCalificacion($r);
            $dic = (string) ($r->dic ?? '');

            if (! CalificacionesColoquioSecundario::apareceEnListadoColoquio(
                $periodo,
                $rowModulos,
                $dic,
                isset($teaPorLegajo[$idLegajo]),
            )) {
                continue;
            }

            $nombre = mb_strtoupper(trim(((string) $r->apellido).' '.((string) $r->nombre)), 'UTF-8');
            $porMateria[$idMateria][] = [
                'dni' => trim((string) ($r->dni ?? '')),
                'nombre' => $nombre,
            ];
        }

        foreach ($materias as $materia) {
            $idMateria = (int) $materia->id;
            $alumnos = $porMateria[$idMateria] ?? [];
            if ($alumnos === []) {
                continue;
            }

            $filas = [];
            $nro = 0;
            foreach ($alumnos as $alumno) {
                $nro++;
                $filas[] = [
                    'nro' => $nro,
                    'dni' => $alumno['dni'],
                    'nombre' => $alumno['nombre'],
                ];
            }

            $actas[] = [
                'cursoLabel' => $cursoLabel,
                'materiaLabel' => mb_strtoupper(trim((string) ($materia->materia ?? '')), 'UTF-8'),
                'filas' => $filas,
            ];
        }

        return [
            'condicionLabel' => CalificacionesColoquioSecundario::tituloCondicionColoquio($periodo),
            'periodo' => $periodo,
            'ano' => $ctx->terlecAno(),
            'actas' => $actas,
        ];
    }

    /**
     * @return list<int>
     */
    public static function idsMateriasConAlumnosElegibles(int $cursoId, string $periodo): array
    {
        $periodo = CalificacionesColoquioSecundario::normalizarPeriodo($periodo);
        $ctx = schoolCtx();

        if ($cursoId <= 0) {
            return [];
        }

        $cursoOk = Curso::query()
            ->where('idNivel', $ctx->idNivel)
            ->where('idTerlec', $ctx->idTerlec)
            ->where('Id', $cursoId)
            ->exists();

        if (! $cursoOk) {
            return [];
        }

        $idTerlec = (int) $ctx->idTerlec;
        $idsCondicionesRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );
        $teaPorLegajo = self::legajosConTeaEnCurso($cursoId, $idTerlec);

        $califs = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('matricula as m', function ($join) use ($cursoId, $idTerlec, $ctx, $idsCondicionesRegulares) {
                $join->on('m.idLegajos', '=', 'l.id')
                    ->where('m.idCursos', $cursoId)
                    ->where('m.idTerlec', $idTerlec)
                    ->where('m.idNivel', (int) $ctx->idNivel)
                    ->whereIn('m.idCondiciones', $idsCondicionesRegulares)
                    ->whereNull('m.fechaBaja');
            })
            ->where('c.idTerlec', $idTerlec)
            ->where('c.idCursos', $cursoId)
            ->get([
                'c.idMaterias',
                'c.idLegajos',
                'c.dic',
                'c.tea',
                'c.ic01', 'c.ic02', 'c.ic03', 'c.ic04', 'c.ic05', 'c.ic06', 'c.ic07', 'c.ic08', 'c.ic09', 'c.ic10',
                'c.ic11', 'c.ic12', 'c.ic13', 'c.ic14', 'c.ic15', 'c.ic16', 'c.ic17', 'c.ic18', 'c.ic19', 'c.ic20',
                'c.ic21', 'c.ic22', 'c.ic23', 'c.ic24', 'c.ic25', 'c.ic26', 'c.ic27', 'c.ic28',
            ]);

        $materiasConAlumnos = [];
        foreach ($califs as $r) {
            $idMateria = (int) $r->idMaterias;
            if (isset($materiasConAlumnos[$idMateria])) {
                continue;
            }

            $idLegajo = (int) $r->idLegajos;
            $rowModulos = self::rowModulosDesdeCalificacion($r);

            if (CalificacionesColoquioSecundario::apareceEnListadoColoquio(
                $periodo,
                $rowModulos,
                (string) ($r->dic ?? ''),
                isset($teaPorLegajo[$idLegajo]),
            )) {
                $materiasConAlumnos[$idMateria] = true;
            }
        }

        $ids = array_keys($materiasConAlumnos);
        if ($ids === []) {
            return [];
        }

        return DB::table('materias')
            ->where('idNivel', (int) $ctx->idNivel)
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $cursoId)
            ->whereIn('id', $ids)
            ->orderBy('ord')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, true>
     */
    private static function legajosConTeaEnCurso(int $cursoId, int $idTerlec): array
    {
        $ids = DB::table('calificaciones')
            ->where('idTerlec', $idTerlec)
            ->where('idCursos', $cursoId)
            ->where('tea', 1)
            ->distinct()
            ->pluck('idLegajos');

        $map = [];
        foreach ($ids as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowModulosDesdeCalificacion(object $r): array
    {
        $out = ['tea' => (int) ($r->tea ?? 0)];
        foreach ([
            'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06', 'ic07', 'ic08', 'ic09', 'ic10',
            'ic11', 'ic12', 'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18', 'ic19', 'ic20',
            'ic21', 'ic22', 'ic23', 'ic24', 'ic25', 'ic26', 'ic27', 'ic28',
        ] as $c) {
            $out[$c] = (string) ($r->{$c} ?? '');
        }

        return $out;
    }
}
