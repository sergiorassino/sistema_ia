<?php

namespace App\Support\Examenes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MateriasAdeudadasExporter
{
    /**
     * @return list<array{
     *     id:int,
     *     idLegajos:int,
     *     apellido:string,
     *     nombre:string,
     *     ano_lectivo:int|string,
     *     curso:string,
     *     materia:string,
     *     condicion:string,
     *     inscripto:string,
     *     idTerlec:int,
     *     idCursos:int,
     *     idMaterias:int,
     *     idCurPlan:int,
     *     idMatPlan:int,
     *     materia_plan:string,
     *     curso_plan:string,
     *     matplan_orden:int,
     *     curplan_orden_ref:int,
     *     curso_orden:int,
     *     materia_orden:int,
     *     curso_c:string,
     *     curso_s:string,
     *     curso_cursec:string
     * }>
     */
    /**
     * @param  string  $alumnos  MateriasAdeudadasFiltros::ALUMNOS_*
     */
    public static function filas(
        int $idNivel,
        ?string $condicion = null,
        ?string $inscri = null,
        string $alumnos = MateriasAdeudadasFiltros::ALUMNOS_REGULARES_CICLO,
        ?int $idTerlecCiclo = null,
    ): array {
        $q = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->leftJoin('matplan as mp', function ($join) {
                $join->whereRaw(
                    'mp.id = IF(COALESCE(m.idMatPlan, 0) > 0, m.idMatPlan, NULLIF(COALESCE(c.idMatPlan, 0), 0))'
                );
            })
            ->leftJoin('curplan as cp', function ($join) {
                $join->whereRaw(
                    'cp.id = COALESCE(NULLIF(cu.idCurPlan, 0), NULLIF(m.idCurPlan, 0), mp.idCurPlan)'
                );
            })
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->join('terlec as t', 't.id', '=', 'c.idTerlec')
            ->where('c.apro', 1)
            ->where('cu.idNivel', $idNivel)
            ->select([
                'c.id',
                'c.idLegajos',
                'l.apellido',
                'l.nombre',
                't.ano as ano_lectivo',
                'cu.cursec',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'cu.idCurPlan',
                'm.idCurPlan as materia_idCurPlan',
                'cu.orden as curso_orden',
                'm.ord as materia_orden',
                'mp.id as matplan_id',
                'mp.matPlanMateria',
                'mp.ord as matplan_orden',
                'mp.idCurPlan as matplan_idCurPlan',
                DB::raw(
                    '(SELECT MIN(c2.orden) FROM cursos c2 WHERE c2.idNivel = cu.idNivel'
                    .' AND c2.idCurPlan = COALESCE(NULLIF(cu.idCurPlan, 0), NULLIF(m.idCurPlan, 0), mp.idCurPlan))'
                    .' AS curplan_orden_ref'
                ),
                'm.materia',
                'c.condAdeuda',
                'c.inscri',
                'c.idTerlec',
                'c.idCursos',
                'c.idMaterias',
            ]);

        $ambitoAlumnos = MateriasAdeudadasFiltros::normalizeAlumnos($alumnos);
        $idTerlec = $idTerlecCiclo !== null ? (int) $idTerlecCiclo : 0;
        if ($ambitoAlumnos === MateriasAdeudadasFiltros::ALUMNOS_REGULARES_CICLO) {
            if ($idTerlec < 1) {
                return [];
            }

            $q->whereExists(function ($sub) use ($idTerlec, $idNivel) {
                $sub->select(DB::raw(1))
                    ->from('matricula as mat')
                    ->whereColumn('mat.idLegajos', 'c.idLegajos')
                    ->where('mat.idTerlec', $idTerlec)
                    ->where('mat.idNivel', $idNivel)
                    ->where('mat.idCondiciones', 1);
            });
        }

        $condNorm = MateriasAdeudadasFiltros::normalizeCondicion($condicion);
        if ($condNorm !== null) {
            $q->where('c.condAdeuda', $condNorm);
        }

        $inscriNorm = MateriasAdeudadasFiltros::normalizeInscri($inscri);
        if ($inscriNorm === MateriasAdeudadasFiltros::INSCRI_SI) {
            $q->where('c.inscri', 1);
        } elseif ($inscriNorm === MateriasAdeudadasFiltros::INSCRI_NO) {
            $q->where('c.inscri', 2);
        }

        $raw = $q
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->orderByDesc('t.ano')
            ->orderBy('m.materia')
            ->get();

        $out = [];
        foreach ($raw as $r) {
            $idMatPlan = self::idMatPlanDesdeFila($r);
            $idCurPlan = self::idCurPlanDesdeFila($r, $idMatPlan);
            $materiaPlan = trim((string) ($r->matPlanMateria ?? ''));
            $cursoPlan = trim((string) ($r->curPlanCurso ?? ''));

            $out[] = [
                'id' => (int) $r->id,
                'idLegajos' => (int) $r->idLegajos,
                'apellido' => trim((string) ($r->apellido ?? '')),
                'nombre' => trim((string) ($r->nombre ?? '')),
                'ano_lectivo' => $r->ano_lectivo ?? '',
                'curso' => self::cursoLabelDesdeFila($r),
                'materia' => trim((string) ($r->materia ?? '')),
                'condicion' => trim((string) ($r->condAdeuda ?? '')),
                'inscripto' => MateriasAdeudadasFiltros::etiquetaInscri((int) ($r->inscri ?? 0)),
                'idTerlec' => (int) $r->idTerlec,
                'idCursos' => (int) $r->idCursos,
                'idMaterias' => (int) $r->idMaterias,
                'idCurPlan' => $idCurPlan,
                'idMatPlan' => $idMatPlan,
                'materia_plan' => $materiaPlan !== '' ? $materiaPlan : trim((string) ($r->materia ?? '')),
                'curso_plan' => $cursoPlan,
                'matplan_orden' => (int) ($r->matplan_orden ?? 0),
                'curplan_orden_ref' => (int) ($r->curplan_orden_ref ?? 0),
                'curso_orden' => (int) ($r->curso_orden ?? 0),
                'materia_orden' => (int) ($r->materia_orden ?? 0),
                'curso_c' => trim((string) ($r->c ?? '')),
                'curso_s' => trim((string) ($r->s ?? '')),
                'curso_cursec' => trim((string) ($r->cursec ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{titulo:string, filas:list<array<string, mixed>>}>
     */
    public static function agrupar(array $filas, string $agrupar): array
    {
        $modo = MateriasAdeudadasFiltros::normalizeAgrupar($agrupar);

        /** @var Collection<int, array<string, mixed>> $coll */
        $coll = collect($filas);

        if ($modo === MateriasAdeudadasFiltros::AGRUPAR_MATERIA_CURSO) {
            return $coll
                ->groupBy(fn (array $f) => self::claveAgrupacionMateriaCurso($f))
                ->map(function (Collection $grupo): array {
                    $first = $grupo->first();
                    $titulo = self::tituloBloqueMateriaCurso($first);

                    $ordenadas = $grupo
                        ->sortBy([
                            ['apellido', 'asc'],
                            ['nombre', 'asc'],
                            ['ano_lectivo', 'desc'],
                        ])
                        ->values()
                        ->all();

                    return [
                        'titulo' => $titulo,
                        'filas' => $ordenadas,
                        '_sort' => self::claveOrdenMateriaCurso($first),
                    ];
                })
                ->sortBy(fn (array $bloque) => $bloque['_sort']['materia_plan'], SORT_NATURAL | SORT_FLAG_CASE)
                ->sortBy(fn (array $bloque) => $bloque['_sort']['matplan_orden'])
                ->sortBy(fn (array $bloque) => $bloque['_sort']['curplan_orden_ref'])
                ->map(fn (array $bloque) => [
                    'titulo' => $bloque['titulo'],
                    'filas' => $bloque['filas'],
                ])
                ->values()
                ->all();
        }

        return $coll
            ->groupBy('idLegajos')
            ->map(function (Collection $grupo): array {
                $first = $grupo->first();
                $titulo = trim((string) ($first['apellido'] ?? ''))
                    .', '.trim((string) ($first['nombre'] ?? ''));

                $ordenadas = $grupo
                    ->sortBy([
                        ['ano_lectivo', 'desc'],
                        ['materia', 'asc'],
                        ['curso', 'asc'],
                    ])
                    ->values()
                    ->all();

                return ['titulo' => $titulo, 'filas' => $ordenadas];
            })
            ->sortBy('titulo', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Agrupa por materia y curso modelo (matplan / curplan), sin separar por año lectivo.
     */
    private static function claveAgrupacionMateriaCurso(array $fila): string
    {
        $idMatPlan = (int) ($fila['idMatPlan'] ?? 0);
        if ($idMatPlan > 0) {
            return 'mp:'.$idMatPlan;
        }

        $idCurPlan = (int) ($fila['idCurPlan'] ?? 0);
        if ($idCurPlan > 0) {
            $materia = mb_strtolower(trim((string) ($fila['materia_plan'] ?? $fila['materia'] ?? '')));

            return 'cp:'.$idCurPlan.'|'.$materia;
        }

        return 'leg:'.$fila['idTerlec'].'|'.$fila['idMaterias'].'|'.$fila['idCursos'];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function tituloBloqueMateriaCurso(array $fila): string
    {
        $materia = trim((string) ($fila['materia_plan'] ?? $fila['materia'] ?? ''));
        $cursoPlan = trim((string) ($fila['curso_plan'] ?? ''));
        $curso = $cursoPlan !== '' ? $cursoPlan : trim((string) ($fila['curso'] ?? ''));

        return $materia.' — '.$curso;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{
     *     curplan_orden_ref:int,
     *     matplan_orden:int,
     *     materia_plan:string
     * }
     */
    private static function claveOrdenMateriaCurso(array $fila): array
    {
        return [
            'curplan_orden_ref' => (int) ($fila['curplan_orden_ref'] ?? $fila['curso_orden'] ?? 0),
            'matplan_orden' => (int) ($fila['matplan_orden'] ?? $fila['materia_orden'] ?? 0),
            'materia_plan' => (string) ($fila['materia_plan'] ?? $fila['materia'] ?? ''),
        ];
    }

    private static function idMatPlanDesdeFila(object $r): int
    {
        $desdeMateria = (int) ($r->matplan_id ?? 0);
        if ($desdeMateria > 0) {
            return $desdeMateria;
        }

        return 0;
    }

    private static function idCurPlanDesdeFila(object $r, int $idMatPlan): int
    {
        if ($idMatPlan > 0) {
            $desdeMatplan = (int) ($r->matplan_idCurPlan ?? 0);
            if ($desdeMatplan > 0) {
                return $desdeMatplan;
            }
        }

        foreach ([$r->idCurPlan ?? 0, $r->materia_idCurPlan ?? 0] as $candidato) {
            $id = (int) $candidato;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    public static function cursoLabelDesdeFila(object $r): string
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

        return 'Curso';
    }
}
