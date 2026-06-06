<?php

namespace App\Support\Examenes;

use App\Support\Listados\ListadoCursoCondicionFiltro;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de tercer materia: calificaciones adeudadas (apro = 1, condAdeuda TM).
 * El listado del módulo exige además matrícula regular en el ciclo lectivo activo.
 */
final class TercerMateriaGestor
{
    /** @var list<string> */
    public const CAMPOS_TM = ['tm1', 'tm2', 'tm3', 'tm4', 'tm5', 'tm6', 'tmNota'];

    /**
     * @return list<array{
     *     id: int,
     *     idLegajos: int,
     *     estudiante: string,
     *     apellido: string,
     *     nombre: string,
     *     ano_lectivo: int|string,
     *     curso: string,
     *     materia: string,
     *     tm1: string,
     *     tm2: string,
     *     tm3: string,
     *     tm4: string,
     *     tm5: string,
     *     tm6: string,
     *     tmNota: string,
     *     curso_actual: string,
     *     profesor_actual: string,
     *     idTerlec: int,
     *     idCursos: int,
     *     idMaterias: int,
     *     idMatPlan: int
     * }>
     */
    public static function filas(int $idNivel, int $idTerlecActual, ?int $idLegajo = null): array
    {
        if ($idNivel < 1) {
            return [];
        }

        $query = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('materias as m', function ($join): void {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->leftJoin('matplan as mp', function ($join): void {
                $join->whereRaw(
                    'mp.id = IF(COALESCE(m.idMatPlan, 0) > 0, m.idMatPlan, NULLIF(COALESCE(c.idMatPlan, 0), 0))'
                );
            })
            ->leftJoin('curplan as cp', function ($join): void {
                $join->whereRaw(
                    'cp.id = COALESCE(NULLIF(cu.idCurPlan, 0), NULLIF(m.idCurPlan, 0), mp.idCurPlan)'
                );
            })
            ->leftJoin('turnos_clase as tc', 'tc.id', '=', 'cu.idTurnoClase')
            ->join('terlec as t', 't.id', '=', 'c.idTerlec')
            ->where('c.apro', 1)
            ->where('c.condAdeuda', 'TM')
            ->where('cu.idNivel', $idNivel);

        $soloListadoModulo = $idLegajo === null || $idLegajo < 1;
        self::joinMatriculaCicloActual($query, $idNivel, $idTerlecActual, $soloListadoModulo);
        $query->leftJoin('cursos as cu_act', 'cu_act.Id', '=', 'mat_act.idCursos');

        if (! $soloListadoModulo) {
            $query->where('c.idLegajos', $idLegajo);
        }

        $raw = $query->select([
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
                'cu.idTurnoClase',
                'm.materia',
                'c.tm1',
                'c.tm2',
                'c.tm3',
                'c.tm4',
                'c.tm5',
                'c.tm6',
                'c.tmNota',
                'cu_act.cursec as curso_actual_cursec',
                'mat_act.idCursos as id_curso_actual',
                'c.idTerlec',
                'c.idCursos',
                'c.idMaterias',
                DB::raw('COALESCE(NULLIF(m.idMatPlan, 0), NULLIF(c.idMatPlan, 0), 0) AS idMatPlan'),
                DB::raw(
                    'COALESCE(NULLIF(cu.idCurPlan, 0), NULLIF(m.idCurPlan, 0), NULLIF(mp.idCurPlan, 0), 0) AS idCurPlan'
                ),
            ]);

        if (! $soloListadoModulo) {
            $raw = $query
                ->orderByDesc('t.ano')
                ->orderBy('m.materia')
                ->get();
        } else {
            $raw = $query
                ->orderBy('l.apellido')
                ->orderBy('l.nombre')
                ->orderByDesc('t.ano')
                ->orderBy('m.materia')
                ->get();
        }

        $paresCurPlanMatPlan = [];
        foreach ($raw as $r) {
            $idCurPlan = (int) ($r->idCurPlan ?? 0);
            $idMatPlan = (int) ($r->idMatPlan ?? 0);
            if ($idMatPlan > 0) {
                $clave = self::claveProfesorMateriaAdeudada(
                    $idCurPlan,
                    $idMatPlan,
                    $r->c ?? null,
                    $r->s ?? null,
                    (int) ($r->idTurnoClase ?? 0),
                );
                $paresCurPlanMatPlan[$clave] = [
                    'idCurPlan' => $idCurPlan,
                    'idMatPlan' => $idMatPlan,
                    'curso_c' => trim((string) ($r->c ?? '')),
                    'curso_s' => trim((string) ($r->s ?? '')),
                    'idTurnoClase' => (int) ($r->idTurnoClase ?? 0),
                ];
            }
        }

        $profesoresPorCurPlanMatPlan = self::profesoresPorCurPlanYMatPlan(
            $idNivel,
            $idTerlecActual,
            array_values($paresCurPlanMatPlan),
        );

        $out = [];
        foreach ($raw as $r) {
            $apellido = trim((string) ($r->apellido ?? ''));
            $nombre = trim((string) ($r->nombre ?? ''));
            $idMatPlan = (int) ($r->idMatPlan ?? 0);
            $idCurPlan = (int) ($r->idCurPlan ?? 0);
            $claveProfesor = $idMatPlan > 0
                ? self::claveProfesorMateriaAdeudada(
                    $idCurPlan,
                    $idMatPlan,
                    $r->c ?? null,
                    $r->s ?? null,
                    (int) ($r->idTurnoClase ?? 0),
                )
                : '';

            $out[] = [
                'id' => (int) $r->id,
                'idLegajos' => (int) $r->idLegajos,
                'estudiante' => trim($apellido.', '.$nombre, ', '),
                'apellido' => $apellido,
                'nombre' => $nombre,
                'ano_lectivo' => $r->ano_lectivo ?? '',
                'curso' => MateriasAdeudadasExporter::cursoLabelDesdeFila($r),
                'materia' => trim((string) ($r->materia ?? '')),
                'tm1' => self::valorTm($r->tm1 ?? null),
                'tm2' => self::valorTm($r->tm2 ?? null),
                'tm3' => self::valorTm($r->tm3 ?? null),
                'tm4' => self::valorTm($r->tm4 ?? null),
                'tm5' => self::valorTm($r->tm5 ?? null),
                'tm6' => self::valorTm($r->tm6 ?? null),
                'tmNota' => self::valorTm($r->tmNota ?? null),
                'curso_actual' => trim((string) ($r->curso_actual_cursec ?? '')),
                'profesor_actual' => $claveProfesor !== ''
                    ? ($profesoresPorCurPlanMatPlan[$claveProfesor] ?? '')
                    : '',
                'idTerlec' => (int) $r->idTerlec,
                'idCursos' => (int) $r->idCursos,
                'idMaterias' => (int) $r->idMaterias,
                'idMatPlan' => $idMatPlan,
            ];
        }

        return $out;
    }

    /**
     * Registros TM de un legajo (todos los ciclos adeudados), para boletín / consulta de calificaciones.
     *
     * @return list<array{
     *     id: int,
     *     materia: string,
     *     curso: string,
     *     ano_lectivo: int|string,
     *     tm1: string,
     *     tm2: string,
     *     tm3: string,
     *     tm4: string,
     *     tm5: string,
     *     tm6: string,
     *     tmNota: string,
     *     linea: string
     * }>
     */
    public static function filasParaLegajo(int $idLegajo, int $idNivel, int $idTerlecActual): array
    {
        if ($idLegajo < 1 || $idNivel < 1) {
            return [];
        }

        $out = [];
        foreach (self::filas($idNivel, $idTerlecActual, $idLegajo) as $fila) {
            $fila['nombre_boletin'] = self::nombreMateriaBoletin($fila);
            $fila['linea'] = self::lineaListadoPdf($fila);
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * Materia + curso en mayúsculas para el recuadro de tercer materia del boletín (ej. HISTORIA TERCERO D).
     *
     * @param  array{materia?: string, curso?: string}  $fila
     */
    public static function nombreMateriaBoletin(array $fila): string
    {
        $materia = mb_strtoupper(trim((string) ($fila['materia'] ?? '')), 'UTF-8');
        $curso = mb_strtoupper(trim((string) ($fila['curso'] ?? '')), 'UTF-8');

        if ($materia === '' && $curso === '') {
            return '';
        }
        if ($curso === '') {
            return $materia;
        }
        if ($materia === '') {
            return $curso;
        }

        return $materia.' '.$curso;
    }

    /**
     * Texto compacto para el pie del boletín / consulta de calificaciones.
     *
     * @param  array{materia: string, curso: string, ano_lectivo: int|string, tm1?: string, tm2?: string, tm3?: string, tm4?: string, tm5?: string, tm6?: string, tmNota?: string}  $fila
     */
    public static function lineaListadoPdf(array $fila): string
    {
        $materia = mb_strtoupper(trim((string) ($fila['materia'] ?? '')), 'UTF-8');
        $curso = trim((string) ($fila['curso'] ?? ''));
        $ano = trim((string) ($fila['ano_lectivo'] ?? ''));

        $sufijoCurso = $curso !== '' ? ' ('.$curso.')' : '';
        $sufijoAno = $ano !== '' ? ($sufijoCurso !== '' ? ' — '.$ano : ' ('.$ano.')') : '';
        $base = $materia.$sufijoCurso.$sufijoAno;

        $detalles = [];
        foreach (['tm1' => 'TM1', 'tm2' => 'TM2', 'tm3' => 'TM3', 'tm4' => 'TM4', 'tm5' => 'TM5', 'tm6' => 'TM6'] as $campo => $etiqueta) {
            $v = trim((string) ($fila[$campo] ?? ''));
            if ($v !== '') {
                $detalles[] = $etiqueta.': '.$v;
            }
        }
        $nota = trim((string) ($fila['tmNota'] ?? ''));
        if ($nota !== '') {
            $detalles[] = 'Nota: '.$nota;
        }

        if ($detalles === []) {
            return $base;
        }

        return $base.' — '.implode(' · ', $detalles);
    }

    /**
     * @return array{
     *     ok: true,
     *     fila: array<string, mixed>
     * }|array{
     *     ok: false,
     *     error: string
     * }
     */
    public static function actualizarCamposTm(int $idCalificacion, int $idNivel, int $idTerlecActual, array $campos): array
    {
        $fila = self::calificacionTm($idCalificacion, $idNivel, $idTerlecActual);
        if ($fila === null) {
            return ['ok' => false, 'error' => 'Registro no encontrado o sin condición TM.'];
        }

        $update = [];
        foreach (self::CAMPOS_TM as $campo) {
            if (! array_key_exists($campo, $campos)) {
                continue;
            }
            $valor = trim((string) $campos[$campo]);
            if (mb_strlen($valor) > 20) {
                return ['ok' => false, 'error' => 'El valor de '.$campo.' no puede superar 20 caracteres.'];
            }
            $update[$campo] = $valor === '' ? null : $valor;
        }

        if ($update === []) {
            return ['ok' => false, 'error' => 'No hay campos para actualizar.'];
        }

        DB::table('calificaciones')
            ->where('id', $idCalificacion)
            ->update($update);

        $refrescada = self::calificacionTm($idCalificacion, $idNivel, $idTerlecActual);

        return [
            'ok' => true,
            'fila' => $refrescada ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function calificacionTm(int $idCalificacion, int $idNivel, int $idTerlecActual): ?array
    {
        $query = DB::table('calificaciones as c')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.id', $idCalificacion)
            ->where('c.apro', 1)
            ->where('c.condAdeuda', 'TM')
            ->where('cu.idNivel', $idNivel);

        self::joinMatriculaCicloActual($query, $idNivel, $idTerlecActual, true);

        $r = $query->first([
                'c.id',
                'c.idLegajos',
                'c.idTerlec',
                'c.idCursos',
                'c.idMaterias',
                'c.tm1',
                'c.tm2',
                'c.tm3',
                'c.tm4',
                'c.tm5',
                'c.tm6',
                'c.tmNota',
            ]);

        if ($r === null) {
            return null;
        }

        return [
            'id' => (int) $r->id,
            'idLegajos' => (int) $r->idLegajos,
            'idTerlec' => (int) $r->idTerlec,
            'idCursos' => (int) $r->idCursos,
            'idMaterias' => (int) $r->idMaterias,
            'tm1' => self::valorTm($r->tm1 ?? null),
            'tm2' => self::valorTm($r->tm2 ?? null),
            'tm3' => self::valorTm($r->tm3 ?? null),
            'tm4' => self::valorTm($r->tm4 ?? null),
            'tm5' => self::valorTm($r->tm5 ?? null),
            'tm6' => self::valorTm($r->tm6 ?? null),
            'tmNota' => self::valorTm($r->tmNota ?? null),
        ];
    }

    /**
     * Datos para el acta de compromiso (acta acuerdo).
     *
     * @return array{
     *     apenom: string,
     *     dni: string,
     *     nombreTercerMateria: string,
     *     nombreCursoTercerMateria: string,
     *     cursoActual: string
     * }|null
     */
    public static function datosActaCompromiso(int $idCalificacion, int $idNivel, int $idTerlecActual): ?array
    {
        $query = DB::table('calificaciones as c')
            ->join('legajos as l', 'l.id', '=', 'c.idLegajos')
            ->join('materias as m', function ($join): void {
                $join->on('m.id', '=', 'c.idMaterias')
                    ->on('m.idTerlec', '=', 'c.idTerlec');
            })
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.id', $idCalificacion)
            ->where('c.apro', 1)
            ->where('c.condAdeuda', 'TM')
            ->where('cu.idNivel', $idNivel);

        self::joinMatriculaCicloActual($query, $idNivel, $idTerlecActual, true);
        $query->leftJoin('cursos as cu_act', 'cu_act.Id', '=', 'mat_act.idCursos');

        $r = $query->first([
                'c.idLegajos',
                'l.apellido',
                'l.nombre',
                'l.dni',
                'm.materia',
                'cu.cursec',
                'cu_act.cursec as curso_actual_cursec',
            ]);

        if ($r === null) {
            return null;
        }

        $cursoActual = trim((string) ($r->curso_actual_cursec ?? ''));

        $apellido = trim((string) ($r->apellido ?? ''));
        $nombre = trim((string) ($r->nombre ?? ''));

        return [
            'apenom' => trim($apellido.' '.$nombre),
            'dni' => trim((string) ($r->dni ?? '')),
            'nombreTercerMateria' => trim((string) ($r->materia ?? '')),
            'nombreCursoTercerMateria' => trim((string) ($r->cursec ?? '')),
            'cursoActual' => $cursoActual,
        ];
    }

    /**
     * Matrícula del ciclo lectivo activo: inner join si debe ser regular (listado del módulo).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function joinMatriculaCicloActual(
        $query,
        int $idNivel,
        int $idTerlecActual,
        bool $soloRegularesActivos,
    ): void {
        if ($idTerlecActual < 1 || $idNivel < 1) {
            if ($soloRegularesActivos) {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        $idsRegulares = ListadoCursoCondicionFiltro::idCondicionesParaQuery(
            ListadoCursoCondicionFiltro::REGULARES
        );

        if ($soloRegularesActivos) {
            $query->join('matricula as mat_act', function ($join) use ($idTerlecActual, $idNivel, $idsRegulares): void {
                $join->on('mat_act.idLegajos', '=', 'c.idLegajos')
                    ->where('mat_act.idTerlec', '=', $idTerlecActual)
                    ->where('mat_act.idNivel', '=', $idNivel)
                    ->whereIn('mat_act.idCondiciones', $idsRegulares)
                    ->whereNull('mat_act.fechaBaja');
            });

            return;
        }

        $query->leftJoin('matricula as mat_act', function ($join) use ($idTerlecActual, $idNivel): void {
            $join->on('mat_act.idLegajos', '=', 'c.idLegajos')
                ->where('mat_act.idTerlec', '=', $idTerlecActual)
                ->where('mat_act.idNivel', '=', $idNivel);
        });
    }

    /**
     * Docente asignado en ppc para la materia adeudada (matplan) en el ciclo actual,
     * en la misma división del curso modelo (curplan + c/s + turno) donde adeudó.
     *
     * @param  list<array{idCurPlan: int, idMatPlan: int, curso_c: string, curso_s: string, idTurnoClase: int}>  $pares
     * @return array<string, string> clave {@see claveProfesorMateriaAdeudada()} => nombre
     */
    private static function profesoresPorCurPlanYMatPlan(int $idNivel, int $idTerlecActual, array $pares): array
    {
        if ($idTerlecActual < 1 || $pares === []) {
            return [];
        }

        $q = DB::table('materias as m')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->join('matplan as mp', 'mp.id', '=', 'm.idMatPlan')
            ->join('ppc', 'ppc.idMateria', '=', 'm.id')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlecActual)
            ->where('m.idMatPlan', '>', 0)
            ->where(function ($w) use ($pares): void {
                foreach ($pares as $par) {
                    $w->orWhere(function ($q2) use ($par): void {
                        $idMatPlan = (int) $par['idMatPlan'];
                        $idCurPlan = (int) $par['idCurPlan'];
                        $q2->where('m.idMatPlan', $idMatPlan);
                        if ($idCurPlan > 0) {
                            $q2->where(function ($q3) use ($idCurPlan): void {
                                $q3->where('mp.idCurPlan', $idCurPlan)
                                    ->orWhere('m.idCurPlan', $idCurPlan)
                                    ->orWhere('cu.idCurPlan', $idCurPlan);
                            });
                        }
                        $q2->whereRaw('TRIM(COALESCE(cu.c, "")) = ?', [(string) $par['curso_c']]);
                        $q2->whereRaw('TRIM(COALESCE(cu.s, "")) = ?', [(string) $par['curso_s']]);
                        $idTurno = (int) ($par['idTurnoClase'] ?? 0);
                        if ($idTurno > 0) {
                            $q2->where('cu.idTurnoClase', $idTurno);
                        }
                    });
                }
            })
            ->orderBy('m.id')
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get([
                'm.id as idMateria',
                'mp.idCurPlan as matplan_idCurPlan',
                'm.idMatPlan',
                'cu.c as curso_c',
                'cu.s as curso_s',
                'cu.idTurnoClase',
                'p.apellido',
                'p.nombre',
            ]);

        $out = [];
        foreach ($q as $r) {
            $idMatPlanFila = (int) $r->idMatPlan;
            $idCurPlanMatplan = (int) ($r->matplan_idCurPlan ?? 0);
            $nombre = trim(trim((string) ($r->apellido ?? '')).' '.trim((string) ($r->nombre ?? '')));
            if ($nombre === '') {
                continue;
            }
            foreach ($pares as $par) {
                if ((int) $par['idMatPlan'] !== $idMatPlanFila) {
                    continue;
                }
                $idCurPlanPar = (int) $par['idCurPlan'];
                if ($idCurPlanPar > 0 && $idCurPlanMatplan > 0 && $idCurPlanPar !== $idCurPlanMatplan) {
                    continue;
                }
                if (trim((string) $par['curso_c']) !== trim((string) ($r->curso_c ?? ''))) {
                    continue;
                }
                if (trim((string) $par['curso_s']) !== trim((string) ($r->curso_s ?? ''))) {
                    continue;
                }
                $turnoPar = (int) ($par['idTurnoClase'] ?? 0);
                $turnoFila = (int) ($r->idTurnoClase ?? 0);
                if ($turnoPar > 0 && $turnoFila > 0 && $turnoPar !== $turnoFila) {
                    continue;
                }
                $clave = self::claveProfesorMateriaAdeudada(
                    $idCurPlanPar,
                    $idMatPlanFila,
                    $par['curso_c'],
                    $par['curso_s'],
                    $turnoPar,
                );
                if (! isset($out[$clave])) {
                    $out[$clave] = $nombre;
                }
            }
        }

        return $out;
    }

    private static function claveProfesorMateriaAdeudada(
        int $idCurPlan,
        int $idMatPlan,
        mixed $cursoC,
        mixed $cursoS,
        int $idTurnoClase,
    ): string {
        return $idCurPlan.':'.$idMatPlan.':'
            .trim((string) $cursoC).':'
            .trim((string) $cursoS).':'
            .$idTurnoClase;
    }

    private static function valorTm(mixed $v): string
    {
        if ($v === null) {
            return '';
        }

        return trim((string) $v);
    }
}
