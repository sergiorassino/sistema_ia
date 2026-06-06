<?php

namespace App\Support\MatrizAnaliticos;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y mapeo de filas de calificaciones para PDFs del analítico (frente y reverso).
 */
final class AnaliticoCalificacionesDatos
{
    /**
     * @param  array<int, string>  $cursosTitulos  clave = cursos.c, valor = título del bloque
     * @return list<array{titulo: string, filas: list<array{
     *     materia: string,
     *     calif_num: string,
     *     calif_letras: string,
     *     cond: string,
     *     mes: string,
     *     ano: string,
     *     escuapro: string,
     *     modo: string
     * }>}>
     */
    public static function bloquesPorCursos(int $idLegajos, int $idNivel, array $cursosTitulos): array
    {
        if ($idLegajos < 1 || $idNivel < 1 || $cursosTitulos === []) {
            return [];
        }

        $nombresMaterias = DB::table('nombresmaterias')
            ->where('idLegajos', $idLegajos)
            ->pluck('nombreMateria', 'idMaterias');

        $bloques = [];
        foreach ($cursosTitulos as $c => $titulo) {
            $bloques[] = [
                'titulo' => $titulo,
                'filas' => self::filasAnioCurso($idLegajos, $idNivel, (int) $c, $nombresMaterias),
            ];
        }

        return $bloques;
    }

    /**
     * @return array{apellido: string, nombre: string, dni: string}|null
     */
    public static function legajoIdentificacion(int $idLegajos): ?array
    {
        if ($idLegajos < 1) {
            return null;
        }

        $legajo = DB::table('legajos')
            ->where('id', $idLegajos)
            ->first(['apellido', 'nombre', 'dni']);

        if ($legajo === null) {
            return null;
        }

        return [
            'apellido' => trim((string) ($legajo->apellido ?? '')),
            'nombre' => trim((string) ($legajo->nombre ?? '')),
            'dni' => trim((string) ($legajo->dni ?? '')),
        ];
    }

    /** @return array{dia: string, mes: string, anio: string} */
    public static function partesFechaEspanol(mixed $fecha): array
    {
        $vacio = ['dia' => '', 'mes' => '', 'anio' => ''];
        if ($fecha === null || $fecha === '') {
            return $vacio;
        }

        try {
            $carbon = $fecha instanceof \DateTimeInterface
                ? Carbon::instance($fecha)
                : Carbon::parse((string) $fecha);
        } catch (\Throwable) {
            return $vacio;
        }

        if ($carbon->year < 1) {
            return $vacio;
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return [
            'dia' => (string) $carbon->day,
            'mes' => $meses[(int) $carbon->month] ?? '',
            'anio' => (string) $carbon->year,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, mixed>  $nombresMaterias
     * @return list<array{
     *     materia: string,
     *     calif_num: string,
     *     calif_letras: string,
     *     cond: string,
     *     mes: string,
     *     ano: string,
     *     escuapro: string,
     *     modo: string
     * }>
     */
    public static function filasAnioCurso(int $idLegajos, int $idNivel, int $c, $nombresMaterias): array
    {
        $rows = DB::table('calificaciones as c')
            ->join('materias as ma', 'ma.id', '=', 'c.idMaterias')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->join('matricula as m', 'm.id', '=', 'c.idMatricula')
            ->where('c.idLegajos', $idLegajos)
            ->where('c.ord', '<', 16)
            ->where('cu.c', $c)
            ->where('cu.idNivel', $idNivel)
            ->where('m.idCondiciones', '<>', 7)
            ->orderBy('c.idTerlec')
            ->orderBy('c.idCursos')
            ->orderBy('c.ord')
            ->get([
                'c.calif',
                'c.mes',
                'c.ano',
                'c.cond',
                'c.escuapro',
                'ma.materia',
                'ma.id as idMaterias',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $idMaterias = (int) ($row->idMaterias ?? 0);
            $materia = trim((string) ($row->materia ?? ''));
            if ($idMaterias > 0 && isset($nombresMaterias[$idMaterias])) {
                $override = trim((string) $nombresMaterias[$idMaterias]);
                if ($override !== '') {
                    $materia = $override;
                }
            }

            $calif = trim((string) ($row->calif ?? ''));
            $out[] = self::mapearFila(
                $materia,
                $calif,
                trim((string) ($row->cond ?? '')),
                self::mostrarMes($row->mes ?? null),
                self::mostrarAno($row->ano ?? null),
                trim((string) ($row->escuapro ?? '')),
            );
        }

        return $out;
    }

    /**
     * @return array{
     *     materia: string,
     *     calif_num: string,
     *     calif_letras: string,
     *     cond: string,
     *     mes: string,
     *     ano: string,
     *     escuapro: string,
     *     modo: string
     * }
     */
    public static function mapearFila(
        string $materia,
        string $calif,
        string $cond,
        string $mes,
        string $ano,
        string $escuapro,
    ): array {
        $c = mb_strtolower($calif);
        $letras = CalificacionEnLetras::resolver($calif);

        if ($c === '') {
            return [
                'materia' => $materia,
                'calif_num' => '----',
                'calif_letras' => '------------',
                'cond' => '----',
                'mes' => '----',
                'ano' => '----',
                'escuapro' => '------------------------------',
                'modo' => 'vacio',
            ];
        }

        if ($c === 'excep') {
            return [
                'materia' => $materia,
                'calif_num' => '----',
                'calif_letras' => $letras,
                'cond' => '----',
                'mes' => $mes,
                'ano' => $ano,
                'escuapro' => $escuapro,
                'modo' => 'excep',
            ];
        }

        if ($c === 'apequ' || $c === 'aprob' || $c === 'a-ams' || $c === 'a-as') {
            return [
                'materia' => $materia,
                'calif_num' => '----',
                'calif_letras' => $letras,
                'cond' => $cond,
                'mes' => $mes,
                'ano' => $ano,
                'escuapro' => $escuapro,
                'modo' => $c,
            ];
        }

        if ($c === 'elimi' || $c === 'adeud') {
            return [
                'materia' => $materia,
                'calif_num' => '----',
                'calif_letras' => $letras,
                'cond' => '----',
                'mes' => '----',
                'ano' => '----',
                'escuapro' => '------------------------------',
                'modo' => $c,
            ];
        }

        return [
            'materia' => $materia,
            'calif_num' => $calif,
            'calif_letras' => $letras,
            'cond' => $cond,
            'mes' => $mes,
            'ano' => $ano,
            'escuapro' => $escuapro,
            'modo' => 'nota',
        ];
    }

    private static function mostrarMes(mixed $mes): string
    {
        if ($mes === null || $mes === '') {
            return '----';
        }

        $n = (int) $mes;

        return ($n >= 1 && $n <= 12) ? (string) $n : '----';
    }

    private static function mostrarAno(mixed $ano): string
    {
        if ($ano === null || $ano === '') {
            return '----';
        }

        $n = (int) $ano;

        return ($n >= 1900 && $n <= 2100) ? (string) $n : '----';
    }
}
