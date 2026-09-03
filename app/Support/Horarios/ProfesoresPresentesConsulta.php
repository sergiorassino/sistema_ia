<?php

namespace App\Support\Horarios;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Docentes con hora cátedra en un día y franja horaria, para cursos/secciones elegidos.
 *
 * Cruza `horarios26` (grilla), `reloj` (hora reloj del módulo) y `ppc` (asignación vigente).
 */
final class ProfesoresPresentesConsulta
{
    /**
     * @param  list<int>  $cursoIds
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     dia: int,
     *     diaLabel: string,
     *     horaInicio: string,
     *     horaFin: string,
     *     cursosResumen: string,
     *     cantidadDocentes: int,
     *     filas: list<array{
     *         idProfesor: int,
     *         docente: string,
     *         curso: string,
     *         horario: string
     *     }>
     * }
     */
    public static function consultar(int $dia, string $horaInicio, string $horaFin, array $cursoIds): array
    {
        $vacio = self::resultadoVacio($dia, $horaInicio, $horaFin);

        $dia = (int) $dia;
        if ($dia < 1 || $dia > 7) {
            $vacio['error'] = 'Elija un día de la semana válido.';

            return $vacio;
        }

        $inicioMin = self::minutosDesdeHora($horaInicio);
        $finMin = self::minutosDesdeHora($horaFin);
        if ($inicioMin === null || $finMin === null) {
            $vacio['error'] = 'Indique horario de inicio y de fin (formato hh:mm).';

            return $vacio;
        }
        if ($finMin <= $inicioMin) {
            $vacio['error'] = 'El horario de fin debe ser posterior al de inicio.';

            return $vacio;
        }

        $horaInicio = self::formatearMinutos($inicioMin);
        $horaFin = self::formatearMinutos($finMin);
        $vacio['horaInicio'] = $horaInicio;
        $vacio['horaFin'] = $horaFin;
        $vacio['dia'] = $dia;
        $vacio['diaLabel'] = HorariosProfesores::DIAS[$dia] ?? '';

        $cursoIds = array_values(array_unique(array_filter(
            array_map('intval', $cursoIds),
            fn (int $id) => $id > 0
        )));
        if ($cursoIds === []) {
            $vacio['error'] = 'Elija al menos un curso o sección.';

            return $vacio;
        }

        if (! Schema::hasTable('horarios26') || ! Schema::hasTable('reloj') || ! Schema::hasTable('ppc')) {
            $vacio['error'] = 'Faltan tablas de horarios (horarios26, reloj o ppc) en este colegio.';

            return $vacio;
        }

        $ctx = schoolCtx();
        $idNivel = (int) $ctx->idNivel;
        $idTerlec = (int) $ctx->idTerlec;
        if ($idNivel <= 0 || $idTerlec <= 0) {
            $vacio['error'] = 'No hay nivel o ciclo lectivo activo en la sesión.';

            return $vacio;
        }

        $cursos = Curso::query()
            ->with(['curplan', 'turnoClase'])
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->whereIn('Id', $cursoIds)
            ->orderBy('orden')
            ->orderBy('cursec')
            ->get(['Id', 'cursec', 'orden', 'idCurPlan', 'idTurnoClase', 'c', 's']);

        if ($cursos->isEmpty()) {
            $vacio['error'] = 'Los cursos elegidos no pertenecen al nivel y ciclo activos.';

            return $vacio;
        }

        $idsPermitidos = $cursos->map(fn (Curso $c) => (int) $c->Id)->all();
        $labelPorCurso = [];
        foreach ($cursos as $curso) {
            $labelPorCurso[(int) $curso->Id] = $curso->nombreParaListado();
        }
        $cursosResumen = implode(', ', array_values($labelPorCurso));
        $vacio['cursosResumen'] = $cursosResumen;

        $slotsPorTurno = self::slotsQueSolapanFranja($cursos, $inicioMin, $finMin);
        if ($slotsPorTurno === []) {
            $vacio['ok'] = true;
            $vacio['error'] = null;

            return $vacio;
        }

        $codigosDia = HorariosProfesores::legacyCodigosDia($dia);
        if ($codigosDia === []) {
            $vacio['error'] = 'No se pudo interpretar el día de la semana.';

            return $vacio;
        }

        $cols = [
            'h.idProfesores as idProfesores',
            'h.idMaterias as idMaterias',
            'h.idCursos as idCursos',
            'h.idHora as idHora',
            'h.idDia as idDia',
            'm.materia as materia',
            'm.idCursos as materiaIdCursos',
        ];
        if (HorariosProfesores::horarios26UsaIdTurnoClase()) {
            $cols[] = 'h.idTurnoClase as horarioIdTurnoClase';
        }

        $q = DB::table('horarios26 as h')
            ->join('materias as m', 'm.id', '=', 'h.idMaterias')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where(function ($w) use ($idsPermitidos) {
                $w->whereIn('h.idCursos', $idsPermitidos)
                    ->orWhere(function ($z) use ($idsPermitidos) {
                        $z->where(function ($y) {
                            $y->whereNull('h.idCursos')->orWhere('h.idCursos', 0);
                        })->whereIn('m.idCursos', $idsPermitidos);
                    });
            });

        $q->where(function ($w) use ($codigosDia) {
            foreach ($codigosDia as $codigo) {
                $w->orWhereRaw('LOWER(TRIM(h.idDia)) = ?', [mb_strtolower(trim((string) $codigo))]);
            }
        });

        $filasH26 = $q->get($cols);
        if ($filasH26->isEmpty()) {
            $vacio['ok'] = true;

            return $vacio;
        }

        $idsMateria = $filasH26
            ->map(fn ($r) => (int) ($r->idMaterias ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $ppcPorMateria = self::ppcPorMateria($idsMateria, $idNivel, $idTerlec);

        /** @var array<int, array{idProfesor: int, docente: string, intervalos: list<array{0: int, 1: int}>, cursos: array<int, string>}> $porDocente */
        $porDocente = [];
        $vistosSlot = [];

        foreach ($filasH26 as $row) {
            $idCursoFila = (int) ($row->idCursos ?? 0);
            if ($idCursoFila <= 0) {
                $idCursoFila = (int) ($row->materiaIdCursos ?? 0);
            }
            if ($idCursoFila <= 0 || ! isset($labelPorCurso[$idCursoFila])) {
                continue;
            }

            $interp = HorariosProfesores::interpretarFilaHorarios26($row, $idCursoFila);
            $slot = (int) ($interp['slot'] ?? 0);
            $idTurno = (int) ($interp['idTurnoClase'] ?? 0);
            if ($slot < 1 || $idTurno < 1) {
                continue;
            }

            $relojTxt = $slotsPorTurno[$idTurno][$slot] ?? null;
            if ($relojTxt === null) {
                continue;
            }
            $rango = self::parsearRangoReloj($relojTxt);
            if ($rango === null) {
                continue;
            }

            $idMateria = (int) ($row->idMaterias ?? 0);
            $asignados = $ppcPorMateria[$idMateria] ?? [];
            if ($asignados === []) {
                continue;
            }

            $idH26 = (int) ($row->idProfesores ?? 0);
            $elegidos = $asignados;
            if ($idH26 > 0) {
                $soloCargado = array_values(array_filter(
                    $asignados,
                    fn (array $p) => (int) $p['id'] === $idH26
                ));
                if ($soloCargado !== []) {
                    $elegidos = $soloCargado;
                }
            }

            foreach ($elegidos as $prof) {
                $idProf = (int) $prof['id'];
                if (! isset($porDocente[$idProf])) {
                    $porDocente[$idProf] = [
                        'idProfesor' => $idProf,
                        'docente' => (string) $prof['label'],
                        'intervalos' => [],
                        'cursos' => [],
                    ];
                }
                $porDocente[$idProf]['cursos'][$idCursoFila] = $labelPorCurso[$idCursoFila];

                $claveSlot = $idProf.'|'.$idTurno.'|'.$slot;
                if (isset($vistosSlot[$claveSlot])) {
                    continue;
                }
                $vistosSlot[$claveSlot] = true;
                $porDocente[$idProf]['intervalos'][] = $rango;
            }
        }

        $filas = [];
        foreach ($porDocente as $doc) {
            $fusionados = self::fusionarIntervalos($doc['intervalos']);
            $filas[] = [
                'idProfesor' => $doc['idProfesor'],
                'docente' => $doc['docente'],
                'curso' => self::formatearCursosDocente($doc['cursos'], $labelPorCurso),
                'horario' => self::formatearHorarioPresente($doc['intervalos']),
                'ordenInicio' => (int) ($fusionados[0][0] ?? 0),
                'ordenFin' => (int) ($fusionados[0][1] ?? 0),
            ];
        }

        usort($filas, function (array $a, array $b): int {
            if ($a['ordenInicio'] !== $b['ordenInicio']) {
                return $a['ordenInicio'] <=> $b['ordenInicio'];
            }
            if ($a['ordenFin'] !== $b['ordenFin']) {
                return $a['ordenFin'] <=> $b['ordenFin'];
            }

            return strcasecmp($a['docente'], $b['docente']);
        });

        $filas = array_map(static fn (array $f): array => [
            'idProfesor' => $f['idProfesor'],
            'docente' => $f['docente'],
            'curso' => $f['curso'],
            'horario' => $f['horario'],
        ], $filas);

        return [
            'ok' => true,
            'error' => null,
            'dia' => $dia,
            'diaLabel' => HorariosProfesores::DIAS[$dia] ?? '',
            'horaInicio' => $horaInicio,
            'horaFin' => $horaFin,
            'cursosResumen' => $cursosResumen,
            'cantidadDocentes' => count($filas),
            'filas' => $filas,
        ];
    }

    public static function minutosDesdeHora(string $hora): ?int
    {
        $hora = trim($hora);
        if ($hora === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $hora, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return ($h * 60) + $min;
    }

    public static function formatearMinutos(int $minutos): string
    {
        $minutos = max(0, min(24 * 60 - 1, $minutos));

        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /**
     * Interpreta textos tipo «08:00-08:40» o «8:00 a 8:40».
     *
     * @return array{0: int, 1: int}|null  minutos desde medianoche [inicio, fin)
     */
    public static function parsearRangoReloj(string $texto): ?array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        if (! preg_match_all('/\b(\d{1,2})[:\.](\d{2})\b/', $texto, $matches, PREG_SET_ORDER)) {
            return null;
        }
        if (count($matches) < 2) {
            return null;
        }
        $h1 = (int) $matches[0][1];
        $min1 = (int) $matches[0][2];
        $h2 = (int) $matches[1][1];
        $min2 = (int) $matches[1][2];
        if ($h1 > 23 || $h2 > 23 || $min1 > 59 || $min2 > 59) {
            return null;
        }
        $inicio = ($h1 * 60) + $min1;
        $fin = ($h2 * 60) + $min2;
        if ($fin <= $inicio) {
            return null;
        }

        return [$inicio, $fin];
    }

    /**
     * Une módulos consecutivos o solapados en tramos de presencia.
     *
     * @param  list<array{0: int, 1: int}>  $intervalos
     * @return list<array{0: int, 1: int}>
     */
    public static function fusionarIntervalos(array $intervalos): array
    {
        if ($intervalos === []) {
            return [];
        }

        usort($intervalos, fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $out = [];
        $cur = $intervalos[0];
        for ($i = 1, $n = count($intervalos); $i < $n; $i++) {
            $sig = $intervalos[$i];
            if ($sig[0] <= $cur[1]) {
                $cur[1] = max($cur[1], $sig[1]);

                continue;
            }
            $out[] = $cur;
            $cur = $sig;
        }
        $out[] = $cur;

        return $out;
    }

    /**
     * Etiquetas de curso del docente, en el mismo orden que el selector (orden / cursec).
     *
     * @param  array<int, string>  $cursosDocente
     * @param  array<int, string>  $labelPorCurso
     */
    public static function formatearCursosDocente(array $cursosDocente, array $labelPorCurso): string
    {
        $labels = [];
        foreach ($labelPorCurso as $idCurso => $label) {
            if (isset($cursosDocente[(int) $idCurso])) {
                $txt = trim((string) $label);
                if ($txt !== '') {
                    $labels[] = $txt;
                }
            }
        }

        return $labels !== [] ? implode(' · ', $labels) : '—';
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervalos
     */
    public static function formatearHorarioPresente(array $intervalos): string
    {
        $tramos = [];
        foreach (self::fusionarIntervalos($intervalos) as $rango) {
            $tramos[] = self::formatearMinutos($rango[0]).' a '.self::formatearMinutos($rango[1]);
        }

        return $tramos !== [] ? implode(' · ', $tramos) : '—';
    }

    /**
     * @param  Collection<int, Curso>  $cursos
     * @return array<int, array<int, string>>  idTurnoClase => slot 1..10 => texto reloj
     */
    private static function slotsQueSolapanFranja(Collection $cursos, int $inicioMin, int $finMin): array
    {
        $turnos = [];
        foreach ($cursos as $curso) {
            foreach (HorariosProfesores::turnosParaImpresionCurso($curso) as $tid) {
                $turnos[(int) $tid] = true;
            }
        }

        $out = [];
        foreach (array_keys($turnos) as $idTurno) {
            $reloj = HorariosProfesores::relojPorTurnoClase((int) $idTurno);
            for ($slot = 1; $slot <= HorariosProfesores::HORAS_POR_TURNO; $slot++) {
                $txt = trim((string) ($reloj[$slot] ?? ''));
                $rango = self::parsearRangoReloj($txt);
                if ($rango === null) {
                    continue;
                }
                if ($rango[0] < $finMin && $rango[1] > $inicioMin) {
                    $out[(int) $idTurno][$slot] = $txt;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $idsMateria
     * @return array<int, list<array{id: int, label: string}>>
     */
    private static function ppcPorMateria(array $idsMateria, int $idNivel, int $idTerlec): array
    {
        if ($idsMateria === []) {
            return [];
        }

        $rows = DB::table('ppc as ppc')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->join('materias as m', 'm.id', '=', 'ppc.idMateria')
            ->whereIn('ppc.idMateria', $idsMateria)
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->orderBy('p.apellido')
            ->orderBy('p.nombre')
            ->get(['ppc.idMateria', 'p.id as idProfesor', 'p.apellido', 'p.nombre']);

        $out = [];
        foreach ($rows as $r) {
            $idMat = (int) ($r->idMateria ?? 0);
            $idProf = (int) ($r->idProfesor ?? 0);
            if ($idMat <= 0 || $idProf <= 0) {
                continue;
            }
            $label = trim(((string) ($r->apellido ?? '')).', '.((string) ($r->nombre ?? '')), ' ,');
            if ($label === '') {
                continue;
            }
            $out[$idMat] ??= [];
            foreach ($out[$idMat] as $ya) {
                if ((int) $ya['id'] === $idProf) {
                    continue 2;
                }
            }
            $out[$idMat][] = ['id' => $idProf, 'label' => $label];
        }

        return $out;
    }

    /**
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     dia: int,
     *     diaLabel: string,
     *     horaInicio: string,
     *     horaFin: string,
     *     cursosResumen: string,
     *     cantidadDocentes: int,
     *     filas: list<array{idProfesor: int, docente: string, curso: string, horario: string}>
     * }
     */
    private static function resultadoVacio(int $dia, string $horaInicio, string $horaFin): array
    {
        return [
            'ok' => false,
            'error' => null,
            'dia' => $dia,
            'diaLabel' => HorariosProfesores::DIAS[$dia] ?? '',
            'horaInicio' => $horaInicio,
            'horaFin' => $horaFin,
            'cursosResumen' => '',
            'cantidadDocentes' => 0,
            'filas' => [],
        ];
    }
}
