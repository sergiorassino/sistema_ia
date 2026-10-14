<?php

namespace App\Support\Horarios;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Docentes con materia y curso asignados (ppc) y horario de asistencia de lunes a viernes.
 *
 * Cruza `horarios26`, `reloj` y `ppc` del nivel y ciclo de `schoolCtx()`.
 */
final class ListadoProfesoresHorariosConsulta
{
    /** Lunes a viernes. */
    public const DIAS = [1, 2, 3, 4, 5];

    /**
     * @return array<int, string>
     */
    public static function etiquetasDias(): array
    {
        $out = [];
        foreach (self::DIAS as $dia) {
            $out[$dia] = HorariosProfesores::DIAS[$dia] ?? ('Día '.$dia);
        }

        return $out;
    }

    /**
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     cantidadDocentes: int,
     *     filas: list<array{docente: string, dias: array<int, string>}>
     * }
     */
    public static function consultar(): array
    {
        $vacio = self::resultadoVacio();

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
            ->where('idNivel', $idNivel)
            ->where('idTerlec', $idTerlec)
            ->get(['Id', 'idTurnoClase']);

        /** @var array<int, int> $turnoFkPorCurso */
        $turnoFkPorCurso = [];
        /** @var array<int, true> $cursosDelContexto */
        $cursosDelContexto = [];
        foreach ($cursos as $curso) {
            $idCurso = (int) $curso->Id;
            if ($idCurso <= 0) {
                continue;
            }
            $cursosDelContexto[$idCurso] = true;
            $turnoFkPorCurso[$idCurso] = (int) ($curso->idTurnoClase ?? 0);
        }

        if ($cursosDelContexto === []) {
            $vacio['ok'] = true;

            return $vacio;
        }

        $idsCursos = array_keys($cursosDelContexto);
        $cols = [
            'h.idProfesores as idProfesores',
            'h.idMaterias as idMaterias',
            'h.idCursos as idCursos',
            'h.idHora as idHora',
            'h.idDia as idDia',
            'm.idCursos as materiaIdCursos',
        ];
        if (HorariosProfesores::horarios26UsaIdTurnoClase()) {
            $cols[] = 'h.idTurnoClase as horarioIdTurnoClase';
        }

        $codigosDia = [];
        foreach (self::DIAS as $dia) {
            foreach (HorariosProfesores::legacyCodigosDia($dia) as $codigo) {
                $codigosDia[mb_strtolower(trim((string) $codigo))] = true;
            }
        }

        $q = DB::table('horarios26 as h')
            ->join('materias as m', 'm.id', '=', 'h.idMaterias')
            ->where('m.idNivel', $idNivel)
            ->where('m.idTerlec', $idTerlec)
            ->where(function ($w) use ($idsCursos) {
                $w->whereIn('h.idCursos', $idsCursos)
                    ->orWhere(function ($z) use ($idsCursos) {
                        $z->where(function ($y) {
                            $y->whereNull('h.idCursos')->orWhere('h.idCursos', 0);
                        })->whereIn('m.idCursos', $idsCursos);
                    });
            });

        $q->where(function ($w) use ($codigosDia) {
            foreach (array_keys($codigosDia) as $codigo) {
                $w->orWhereRaw('LOWER(TRIM(h.idDia)) = ?', [$codigo]);
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

        /**
         * @var array<int, array{
         *     apellido: string,
         *     nombre: string,
         *     docente: string,
         *     intervalos: array<int, list<array{0: int, 1: int}>>
         * }> $porDocente
         */
        $porDocente = [];
        $vistosSlot = [];
        /** @var array<int, array<int, string>> $relojCache */
        $relojCache = [];

        foreach ($filasH26 as $row) {
            $dia = HorariosProfesores::diaFromLegacy((string) ($row->idDia ?? ''));
            if (! in_array($dia, self::DIAS, true)) {
                continue;
            }

            $idCursoFila = (int) ($row->idCursos ?? 0);
            if ($idCursoFila <= 0) {
                $idCursoFila = (int) ($row->materiaIdCursos ?? 0);
            }
            if ($idCursoFila <= 0 || ! isset($cursosDelContexto[$idCursoFila])) {
                continue;
            }

            $interp = self::interpretarFila($row, $idCursoFila, $turnoFkPorCurso);
            $slot = (int) ($interp['slot'] ?? 0);
            $idTurno = (int) ($interp['idTurnoClase'] ?? 0);
            if ($slot < 1 || $idTurno < 1) {
                continue;
            }

            if (! isset($relojCache[$idTurno])) {
                $relojCache[$idTurno] = HorariosProfesores::relojPorTurnoClase($idTurno, $idNivel);
            }
            $relojTxt = trim((string) ($relojCache[$idTurno][$slot] ?? ''));
            $rango = $relojTxt === '' ? null : ProfesoresPresentesConsulta::parsearRangoReloj($relojTxt);
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
                        'apellido' => (string) $prof['apellido'],
                        'nombre' => (string) $prof['nombre'],
                        'docente' => (string) $prof['label'],
                        'intervalos' => [],
                    ];
                }

                $claveSlot = $idProf.'|'.$dia.'|'.$idTurno.'|'.$slot;
                if (isset($vistosSlot[$claveSlot])) {
                    continue;
                }
                $vistosSlot[$claveSlot] = true;
                $porDocente[$idProf]['intervalos'][$dia][] = $rango;
            }
        }

        $filas = [];
        foreach ($porDocente as $doc) {
            $dias = [];
            $tieneAlguno = false;
            foreach (self::DIAS as $dia) {
                $texto = ProfesoresPresentesConsulta::formatearHorarioPresente($doc['intervalos'][$dia] ?? []);
                if ($texto !== '—') {
                    $tieneAlguno = true;
                }
                $dias[$dia] = $texto;
            }
            if (! $tieneAlguno) {
                continue;
            }
            $filas[] = [
                'apellido' => $doc['apellido'],
                'nombre' => $doc['nombre'],
                'docente' => $doc['docente'],
                'dias' => $dias,
            ];
        }

        usort($filas, function (array $a, array $b): int {
            return OrdenAlfabeticoEstudiante::comparar(
                $a['apellido'],
                $a['nombre'],
                $b['apellido'],
                $b['nombre'],
            );
        });

        $filas = array_map(static fn (array $f): array => [
            'docente' => $f['docente'],
            'dias' => $f['dias'],
        ], $filas);

        return [
            'ok' => true,
            'error' => null,
            'cantidadDocentes' => count($filas),
            'filas' => $filas,
        ];
    }

    /**
     * Misma lectura que {@see HorariosProfesores::interpretarFilaHorarios26()}, con el turno del curso ya cargado.
     *
     * @param  array<int, int>  $turnoFkPorCurso
     * @return array{slot: int, idTurnoClase: int}
     */
    private static function interpretarFila(object $row, int $idCurso, array $turnoFkPorCurso): array
    {
        $idHora = (int) ($row->idHora ?? 0);
        if (HorariosProfesores::horarios26UsaIdTurnoClase()) {
            $idTc = (int) ($row->horarioIdTurnoClase ?? 0);
            if ($idTc > 0 && $idHora >= 1 && $idHora <= HorariosProfesores::HORAS_POR_TURNO) {
                return ['slot' => $idHora, 'idTurnoClase' => $idTc];
            }
        }

        $slot = HorariosProfesores::slotDesdeIdHoraLegacy($idHora);
        if ($slot < 1) {
            return ['slot' => 0, 'idTurnoClase' => 0];
        }

        $bloque = HorariosProfesores::indiceBloqueDesdeIdHoraLegacy($idHora);
        $catalogo = HorariosProfesores::catalogoTurnosClase()->values();
        $idTurnoClase = (int) ($catalogo->get($bloque)?->id ?? $catalogo->first()?->id ?? 1);

        if ($idHora >= 1 && $idHora <= HorariosProfesores::HORAS_POR_TURNO && $idCurso > 0) {
            $fk = (int) ($turnoFkPorCurso[$idCurso] ?? 0);
            if ($fk > 0) {
                $idTurnoClase = HorariosProfesores::idTurnoClaseParaCurso($fk);
            }
        }

        return ['slot' => $slot, 'idTurnoClase' => $idTurnoClase];
    }

    /**
     * @param  list<int>  $idsMateria
     * @return array<int, list<array{id: int, label: string, apellido: string, nombre: string}>>
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
            ->get(['ppc.idMateria', 'p.id as idProfesor', 'p.apellido', 'p.nombre']);

        $out = [];
        foreach ($rows as $r) {
            $idMat = (int) ($r->idMateria ?? 0);
            $idProf = (int) ($r->idProfesor ?? 0);
            if ($idMat <= 0 || $idProf <= 0) {
                continue;
            }
            $apellido = trim((string) ($r->apellido ?? ''));
            $nombre = trim((string) ($r->nombre ?? ''));
            $label = trim($apellido.', '.$nombre, ' ,');
            if ($label === '') {
                continue;
            }
            $out[$idMat] ??= [];
            foreach ($out[$idMat] as $ya) {
                if ((int) $ya['id'] === $idProf) {
                    continue 2;
                }
            }
            $out[$idMat][] = [
                'id' => $idProf,
                'label' => $label,
                'apellido' => $apellido,
                'nombre' => $nombre,
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     cantidadDocentes: int,
     *     filas: list<array{docente: string, dias: array<int, string>}>
     * }
     */
    private static function resultadoVacio(): array
    {
        return [
            'ok' => false,
            'error' => null,
            'cantidadDocentes' => 0,
            'filas' => [],
        ];
    }
}
