<?php

namespace App\Support\Examenes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Permiso de examen por alumno: PDF con materias adeudadas inscriptas a examen (apro = 1, inscri = 1).
 */
final class PermisoExamen
{
    public const FILAS_POR_PERMISO = 15;

    /**
     * Alumnos del nivel con al menos una materia adeudada inscripta a examen.
     *
     * @return Collection<int, object{
     *     idLegajos: int,
     *     apellido: string,
     *     nombre: string,
     *     dni: string,
     *     cantidadMaterias: int
     * }>
     */
    public static function terminoBusqueda(?string $buscar): string
    {
        $t = trim((string) $buscar);

        return mb_strlen($t) >= 2 ? $t : '';
    }

    /**
     * Filtra por apellido, nombre o DNI (mín. 2 caracteres; sin acento en comparación).
     *
     * @param  Collection<int, object>  $estudiantes
     * @return Collection<int, object>
     */
    public static function filtrarEstudiantes(Collection $estudiantes, ?string $buscar): Collection
    {
        $termino = mb_strtolower(self::terminoBusqueda($buscar));
        if ($termino === '') {
            return $estudiantes;
        }

        return $estudiantes
            ->filter(function (object $est) use ($termino): bool {
                foreach (['apellido', 'nombre', 'dni'] as $campo) {
                    if (str_contains(mb_strtolower((string) ($est->{$campo} ?? '')), $termino)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    public static function estudiantes(int $idNivel): Collection
    {
        if ($idNivel < 1) {
            return collect();
        }

        return DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.apro', 1)
            ->where('c.inscri', 1)
            ->where('cu.idNivel', $idNivel)
            ->groupBy('l.id', 'l.apellido', 'l.nombre', 'l.dni')
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.apellido'))
            ->orderByRaw(\App\Support\OrdenAlfabeticoEstudiante::sql('l.nombre'))
            ->orderBy('l.id')
            ->get([
                'l.id as idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                DB::raw('COUNT(c.id) as cantidadMaterias'),
            ])
            ->map(function (object $r): object {
                return (object) [
                    'idLegajos' => (int) $r->idLegajos,
                    'apellido' => trim((string) ($r->apellido ?? '')),
                    'nombre' => trim((string) ($r->nombre ?? '')),
                    'dni' => trim((string) ($r->dni ?? '')),
                    'cantidadMaterias' => (int) ($r->cantidadMaterias ?? 0),
                ];
            });
    }

    /**
     * @param  list<int>  $idsSolicitados
     * @return list<int> IDs permitidos, en orden alfabético del listado
     */
    public static function resolverIdsAlumnos(string $idsCsv, Collection $permitidos): array
    {
        $parsed = collect(explode(',', $idsCsv))
            ->map(fn ($v) => (int) trim((string) $v))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($parsed->isEmpty()) {
            return [];
        }

        return self::filtrarIdsPermitidos($parsed->all(), $permitidos);
    }

    /**
     * @param  list<int>  $idsSolicitados
     * @return list<int>
     */
    public static function filtrarIdsPermitidos(array $idsSolicitados, Collection $permitidos): array
    {
        $parsed = collect($idsSolicitados)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($parsed->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($permitidos as $est) {
            $id = (int) $est->idLegajos;
            if ($parsed->contains($id) && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        if (count($out) > 500) {
            return [];
        }

        return $out;
    }

    /**
     * Materias adeudadas inscriptas a examen de un alumno (consulta por legajo, como el sistema anterior).
     *
     * @return list<array{materia: string, curso: string, plan: string, condicion: string}>
     */
    public static function materiasAlumno(int $idNivel, int $idLegajos): array
    {
        if ($idNivel < 1 || $idLegajos < 1) {
            return [];
        }

        $raw = DB::table('calificaciones as c')
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
            ->leftJoin('planes as pl', 'pl.id', '=', 'cp.idPlan')
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->join('terlec as t', 't.id', '=', 'c.idTerlec')
            ->where('c.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->where('c.apro', 1)
            ->where('c.inscri', 1)
            ->orderByDesc('t.ano')
            ->orderBy('mp.ord')
            ->orderBy('m.ord')
            ->orderBy('m.materia')
            ->get([
                'm.materia',
                'mp.matPlanMateria',
                'cu.cursec',
                'cp.curPlanCurso',
                'pl.abrev as plan_abrev',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'c.condAdeuda',
            ]);

        $out = [];
        foreach ($raw as $r) {
            $fila = self::filaMateriaDesdeRegistro($r);
            if ($fila !== null) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{materia: string, curso: string, plan: string, condicion: string}>  $materias
     * @return list<array{nro: int, materia: string, curso: string, plan: string, condicion: string}>
     */
    public static function filasTablaPermiso(array $materias): array
    {
        $filas = [];
        $nro = 0;
        foreach (array_slice($materias, 0, self::FILAS_POR_PERMISO) as $m) {
            $nro++;
            $filas[] = [
                'nro' => $nro,
                'materia' => $m['materia'],
                'curso' => $m['curso'],
                'plan' => $m['plan'],
                'condicion' => $m['condicion'],
            ];
        }

        while (count($filas) < self::FILAS_POR_PERMISO) {
            $filas[] = [
                'nro' => count($filas) + 1,
                'materia' => '',
                'curso' => '',
                'plan' => '',
                'condicion' => '',
            ];
        }

        return $filas;
    }

    /**
     * @return array{materia: string, curso: string, plan: string, condicion: string}|null
     */
    private static function filaMateriaDesdeRegistro(object $r): ?array
    {
        $materiaPlan = trim((string) ($r->matPlanMateria ?? ''));
        $materia = $materiaPlan !== ''
            ? $materiaPlan
            : trim((string) ($r->materia ?? ''));
        if ($materia === '') {
            return null;
        }

        $cursoPlan = trim((string) ($r->curPlanCurso ?? ''));
        $curso = $cursoPlan !== '' ? $cursoPlan : self::cursoLabelDesdeFila($r);

        return [
            'materia' => mb_strtoupper($materia, 'UTF-8'),
            'curso' => mb_strtoupper($curso, 'UTF-8'),
            'plan' => mb_strtoupper(trim((string) ($r->plan_abrev ?? '')), 'UTF-8'),
            'condicion' => strtoupper(trim((string) ($r->condAdeuda ?? ''))),
        ];
    }

    private static function cursoLabelDesdeFila(object $r): string
    {
        $sec = trim((string) ($r->cursec ?? ''));
        if ($sec !== '') {
            return $sec;
        }

        $extras = collect([$r->turnoClaseNombre ?? '', $r->c ?? '', $r->s ?? ''])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        return $extras->isNotEmpty() ? $extras->implode(' ') : '—';
    }

    public static function etiquetaTurnoExamen(int $idTurno, ?int $anoTerlec): string
    {
        $nombreTurno = MateriasAdeudadasPreparacion::etiquetaTurno($idTurno);
        $ano = $anoTerlec ?? null;

        return $ano !== null && $ano > 0
            ? 'Turno de '.$nombreTurno.' '.$ano
            : 'Turno de '.$nombreTurno;
    }
}
