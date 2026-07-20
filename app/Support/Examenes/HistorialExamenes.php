<?php

namespace App\Support\Examenes;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class HistorialExamenes
{
    /**
     * Registros de examen del alumno agrupados por materia (orden plan de estudios)
     * y, dentro de cada materia, por fecha descendente.
     *
     * @return list<array{
     *     clave: string,
     *     materia: string,
     *     curso: string,
     *     rendiciones: list<array{
     *         id: int,
     *         idCalificacion: int,
     *         fecha: string,
     *         fecha_iso: string,
     *         nota: string,
     *         condicion: string,
     *         libro: string,
     *         folio: string,
     *         curso: string,
     *         ano_lectivo: int|string
     *     }>
     * }>
     */
    public static function porMateria(int $idLegajos, int $idNivel): array
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return [];
        }

        $raw = DB::table('notasexamen as n')
            ->join('calificaciones as c', 'c.id', '=', 'n.idCalificaciones')
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
            ->where('n.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->select([
                'n.id',
                'n.idCalificaciones',
                'n.nota',
                'n.fecha',
                'n.condExamen',
                'n.libro',
                'n.folio',
                'm.materia',
                'm.idMatPlan as materia_idMatPlan',
                'm.idCurPlan as materia_idCurPlan',
                'c.idMatPlan as calif_idMatPlan',
                'c.idTerlec',
                'c.idMaterias',
                'c.idCursos',
                't.ano as ano_lectivo',
                'cu.cursec',
                'cu.idCurPlan',
                'cu.orden as curso_orden',
                'm.ord as materia_orden',
                'cp.curPlanCurso',
                'tc.nombre as turnoClaseNombre',
                'cu.c',
                'cu.s',
                'mp.id as matplan_id',
                'mp.matPlanMateria',
                'mp.ord as matplan_orden',
                'mp.idCurPlan as matplan_idCurPlan',
                DB::raw(
                    '(SELECT MIN(c2.orden) FROM cursos c2 WHERE c2.idNivel = cu.idNivel'
                    .' AND c2.idCurPlan = COALESCE(NULLIF(cu.idCurPlan, 0), NULLIF(m.idCurPlan, 0), mp.idCurPlan))'
                    .' AS curplan_orden_ref'
                ),
            ])
            ->get();

        $grupos = [];
        foreach ($raw as $r) {
            $idMatPlan = self::idMatPlanDesdeFila($r);
            $idCurPlan = self::idCurPlanDesdeFila($r, $idMatPlan);
            $materiaPlan = trim((string) ($r->matPlanMateria ?? ''));
            $materia = trim((string) ($r->materia ?? ''));
            $materiaEtiqueta = $materiaPlan !== '' ? $materiaPlan : ($materia !== '' ? $materia : '—');

            $filaPlan = [
                'idMatPlan' => $idMatPlan,
                'idCurPlan' => $idCurPlan,
                'idTerlec' => (int) $r->idTerlec,
                'idMaterias' => (int) $r->idMaterias,
                'idCursos' => (int) $r->idCursos,
                'materia_plan' => $materiaEtiqueta,
                'materia' => $materia,
                'matplan_orden' => (int) ($r->matplan_orden ?? 0),
                'curplan_orden_ref' => (int) ($r->curplan_orden_ref ?? 0),
                'curso_orden' => (int) ($r->curso_orden ?? 0),
                'materia_orden' => (int) ($r->materia_orden ?? 0),
                'curso_plan' => trim((string) ($r->curPlanCurso ?? '')),
                'curso' => MateriasAdeudadasCargaManual::cursoLabelDesdeFila($r),
            ];

            $clave = self::claveAgrupacionMateria($filaPlan);

            if (! isset($grupos[$clave])) {
                $cursoPlan = $filaPlan['curso_plan'];
                $curso = $cursoPlan !== '' ? $cursoPlan : $filaPlan['curso'];

                $grupos[$clave] = [
                    'clave' => $clave,
                    'materia' => $materiaEtiqueta,
                    'curso' => $curso,
                    'rendiciones' => [],
                    '_sort' => self::claveOrdenPlan($filaPlan),
                ];
            }

            $fechaIso = self::fechaAString($r->fecha ?? null);
            $grupos[$clave]['rendiciones'][] = [
                'id' => (int) $r->id,
                'idCalificacion' => (int) $r->idCalificaciones,
                'fecha' => self::fechaParaMostrar($fechaIso),
                'fecha_iso' => $fechaIso,
                'nota' => trim((string) ($r->nota ?? '')),
                'condicion' => strtoupper(trim((string) ($r->condExamen ?? ''))),
                'libro' => trim((string) ($r->libro ?? '')),
                'folio' => trim((string) ($r->folio ?? '')),
                'curso' => $filaPlan['curso'],
                'ano_lectivo' => $r->ano_lectivo ?? '',
            ];
        }

        $bloques = array_values($grupos);

        foreach ($bloques as &$bloque) {
            usort($bloque['rendiciones'], static function (array $a, array $b): int {
                $cmpFecha = strcmp($b['fecha_iso'], $a['fecha_iso']);
                if ($cmpFecha !== 0) {
                    return $cmpFecha;
                }

                return $b['id'] <=> $a['id'];
            });
        }
        unset($bloque);

        usort($bloques, static function (array $a, array $b): int {
            $sa = $a['_sort'];
            $sb = $b['_sort'];

            if ($sa['curplan_orden_ref'] !== $sb['curplan_orden_ref']) {
                return $sa['curplan_orden_ref'] <=> $sb['curplan_orden_ref'];
            }
            if ($sa['matplan_orden'] !== $sb['matplan_orden']) {
                return $sa['matplan_orden'] <=> $sb['matplan_orden'];
            }

            return strnatcasecmp($sa['materia_plan'], $sb['materia_plan']);
        });

        foreach ($bloques as &$bloque) {
            unset($bloque['_sort']);
        }
        unset($bloque);

        return $bloques;
    }

    /**
     * @return array{
     *     id: int,
     *     idCalificacion: int,
     *     materia: string,
     *     fecha_iso: string,
     *     nota: string,
     *     condicion: string,
     *     libro: string,
     *     folio: string
     * }|null
     */
    public static function registro(int $idNota, int $idLegajos, int $idNivel): ?array
    {
        if ($idNota < 1 || $idLegajos < 1 || $idNivel < 1) {
            return null;
        }

        $r = DB::table('notasexamen as n')
            ->join('calificaciones as c', 'c.id', '=', 'n.idCalificaciones')
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('n.id', $idNota)
            ->where('n.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->select([
                'n.id',
                'n.idCalificaciones',
                'n.nota',
                'n.fecha',
                'n.condExamen',
                'n.libro',
                'n.folio',
                'm.materia',
            ])
            ->first();

        if ($r === null) {
            return null;
        }

        $fechaIso = self::fechaAString($r->fecha ?? null);

        return [
            'id' => (int) $r->id,
            'idCalificacion' => (int) $r->idCalificaciones,
            'materia' => trim((string) ($r->materia ?? '')),
            'fecha_iso' => $fechaIso,
            'nota' => trim((string) ($r->nota ?? '')),
            'condicion' => strtoupper(trim((string) ($r->condExamen ?? ''))),
            'libro' => trim((string) ($r->libro ?? '')),
            'folio' => trim((string) ($r->folio ?? '')),
        ];
    }

    /**
     * @param array{
     *     fecha: string,
     *     nota: string,
     *     condExamen?: string|null,
     *     libro?: string|null,
     *     folio?: string|null
     * } $datos
     *
     * @return 'ok'|'ok_aprobada'|'no_encontrada'|'condicion_invalida'
     */
    public static function actualizar(int $idNota, int $idLegajos, int $idNivel, array $datos): string
    {
        $registro = self::registro($idNota, $idLegajos, $idNivel);
        if ($registro === null) {
            return 'no_encontrada';
        }

        $cond = trim((string) ($datos['condExamen'] ?? ''));
        if ($cond !== '') {
            $condNorm = MateriasAdeudadasFiltros::normalizeCondicion($cond);
            if ($condNorm === null) {
                return 'condicion_invalida';
            }
            $cond = $condNorm;
        }

        $fecha = trim((string) ($datos['fecha'] ?? ''));
        if ($fecha === '' || ! self::esFechaValida($fecha)) {
            return 'no_encontrada';
        }

        $nota = trim((string) ($datos['nota'] ?? ''));
        if ($nota === '') {
            return 'no_encontrada';
        }

        $libro = self::truncarOpcional($datos['libro'] ?? null, 10);
        $folio = self::truncarOpcional($datos['folio'] ?? null, 10);
        $aprobada = false;

        DB::transaction(function () use (
            $idNota,
            $idLegajos,
            $idNivel,
            $registro,
            $fecha,
            $nota,
            $cond,
            $libro,
            $folio,
            &$aprobada,
        ): void {
            DB::table('notasexamen')
                ->where('id', $idNota)
                ->where('idLegajos', $idLegajos)
                ->update([
                    'fecha' => $fecha,
                    'nota' => mb_substr($nota, 0, 10),
                    'condExamen' => $cond !== '' ? mb_substr($cond, 0, 2) : null,
                    'libro' => $libro,
                    'folio' => $folio,
                ]);

            $aprobada = MateriasAdeudadasNotasExamen::aprobarSiNotaSuficiente(
                (int) $registro['idCalificacion'],
                $idLegajos,
                $idNivel,
                $nota,
                $fecha,
            );
        });

        return $aprobada ? 'ok_aprobada' : 'ok';
    }

    /**
     * @return 'ok'|'no_encontrada'
     */
    public static function eliminar(int $idNota, int $idLegajos, int $idNivel): string
    {
        if (self::registro($idNota, $idLegajos, $idNivel) === null) {
            return 'no_encontrada';
        }

        DB::table('notasexamen')
            ->where('id', $idNota)
            ->where('idLegajos', $idLegajos)
            ->delete();

        return 'ok';
    }

    public static function totalRegistros(int $idLegajos, int $idNivel): int
    {
        if ($idLegajos < 1 || $idNivel < 1) {
            return 0;
        }

        return (int) DB::table('notasexamen as n')
            ->join('calificaciones as c', 'c.id', '=', 'n.idCalificaciones')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('n.idLegajos', $idLegajos)
            ->where('cu.idNivel', $idNivel)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function claveAgrupacionMateria(array $fila): string
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
     * @return array{curplan_orden_ref: int, matplan_orden: int, materia_plan: string}
     */
    private static function claveOrdenPlan(array $fila): array
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

    private static function esFechaValida(string $fecha): bool
    {
        try {
            Carbon::createFromFormat('Y-m-d', $fecha);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function fechaAString(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        $s = trim((string) $valor);
        if ($s === '') {
            return '';
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function fechaParaMostrar(string $fechaIso): string
    {
        if ($fechaIso === '') {
            return '—';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $fechaIso)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private static function truncarOpcional(mixed $valor, int $max): ?string
    {
        $s = trim((string) ($valor ?? ''));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
