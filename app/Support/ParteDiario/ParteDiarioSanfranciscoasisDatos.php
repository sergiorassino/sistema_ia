<?php

namespace App\Support\ParteDiario;

use App\Models\Curso;
use App\Support\HorariosProfesores;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos del parte diario Legal (San Francisco de Asís): alumnos regulares + firmas por hora del día.
 */
final class ParteDiarioSanfranciscoasisDatos
{
    public const HORAS_MARCADO = 10;

    /**
     * @param  list<Curso>  $cursos
     * @return list<array{
     *   cursoLabel: string,
     *   fechaTexto: string,
     *   alumnos: list<array{nro:int, legajo:string, nombre:string}>,
     *   filasFirma: list<array{etiqueta:string, espacio:string}>
     * }>
     */
    public static function paginas(array $cursos, Carbon $fecha, ?int $turnoElegido = null): array
    {
        $dia = (int) $fecha->dayOfWeekIso;
        if ($dia < 1 || $dia > 7) {
            $dia = 1;
        }
        $fechaTexto = $fecha->format('d/m/Y');
        $unSoloCurso = count($cursos) === 1;
        $paginas = [];

        foreach ($cursos as $curso) {
            if (! $curso instanceof Curso) {
                continue;
            }

            $cursoId = (int) $curso->Id;
            if ($cursoId <= 0) {
                continue;
            }

            $turnos = HorariosProfesores::turnosParaImpresionCurso($curso);
            if ($unSoloCurso && $turnoElegido !== null && $turnoElegido > 0 && in_array($turnoElegido, $turnos, true)) {
                $idTurnoClase = $turnoElegido;
            } else {
                $idTurnoClase = (int) ($turnos[0] ?? 1);
            }
            if ($idTurnoClase <= 0) {
                $idTurnoClase = 1;
            }

            $paginas[] = [
                'cursoLabel' => $curso->nombreParaListado(),
                'fechaTexto' => $fechaTexto,
                'alumnos' => self::alumnosRegulares($cursoId),
                'filasFirma' => self::filasFirma($cursoId, $dia, $idTurnoClase),
            ];
        }

        return $paginas;
    }

    /**
     * Solo regulares (`matricula.idCondiciones = 1`), como el legacy ScriptCase.
     *
     * @return list<array{nro:int, legajo:string, nombre:string}>
     */
    public static function alumnosRegulares(int $idCurso): array
    {
        if ($idCurso <= 0) {
            return [];
        }

        $rows = DB::table('matricula as m')
            ->join('legajos as l', 'l.id', '=', 'm.idLegajos')
            ->where('m.idCursos', $idCurso)
            ->where('m.idCondiciones', 1)
            ->orderBy('l.apellido')
            ->orderBy('l.nombre')
            ->get(['l.legajo', 'l.apellido', 'l.nombre']);

        $out = [];
        $nro = 0;
        foreach ($rows as $row) {
            $nro++;
            $apellido = trim((string) ($row->apellido ?? ''));
            $nombre = trim((string) ($row->nombre ?? ''));
            $out[] = [
                'nro' => $nro,
                'legajo' => trim((string) ($row->legajo ?? '')),
                'nombre' => trim($apellido.' '.$nombre),
            ];
        }

        return $out;
    }

    /**
     * Hasta {@see HORAS_MARCADO} filas de materia/docente del día (vía grilla de horarios).
     *
     * @return list<array{etiqueta:string, espacio:string}>
     */
    public static function filasFirma(int $idCurso, int $diaSemana1a7, int $idTurnoClase): array
    {
        $filas = HorariosProfesores::filasParteDiarioCursoDia($idCurso, $diaSemana1a7, $idTurnoClase);
        $out = [];

        for ($h = 1; $h <= self::HORAS_MARCADO; $h++) {
            $match = null;
            foreach ($filas as $f) {
                if ((int) ($f['hora'] ?? 0) === $h) {
                    $match = $f;
                    break;
                }
            }

            if ($match !== null) {
                $etiqueta = trim((string) ($match['etiquetaReloj'] ?? ''));
                if ($etiqueta === '') {
                    $etiqueta = $h.'º HORA';
                }
                $out[] = [
                    'etiqueta' => $etiqueta,
                    'espacio' => trim((string) ($match['espacio'] ?? '')),
                ];
            } else {
                $out[] = [
                    'etiqueta' => $h.'º HORA',
                    'espacio' => '',
                ];
            }
        }

        return $out;
    }
}
