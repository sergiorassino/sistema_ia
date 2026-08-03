<?php

namespace App\Support\MatrizAnaliticos;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y mapeo de filas de calificaciones para PDFs del analítico (frente y reverso).
 *
 * Origen de renglones: materias del curso/año lectivo de cada matrícula histórica del alumno
 * (no el plan modelo del año actual). Las notas se cruzan con calificaciones si existen.
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

        $nombresMaterias = LibroMatrizAnalitico::overridesNombreMateriaPorLegajo($idLegajos);

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
     * Renglones de un año pedagógico (`cursos.c`): materias de las matrículas históricas
     * de ese año, con calificación si hay fila en `calificaciones`.
     *
     * @param  array<int, string>  $nombresMaterias
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
        if ($idLegajos < 1 || $idNivel < 1 || $c < 1) {
            return [];
        }

        $matriculas = DB::table('matricula as m')
            ->join('cursos as cu', 'cu.Id', '=', 'm.idCursos')
            ->join('terlec as t', 't.id', '=', 'm.idTerlec')
            ->where('m.idLegajos', $idLegajos)
            ->where('m.idNivel', $idNivel)
            ->where('cu.c', $c)
            ->where('cu.idNivel', $idNivel)
            ->orderBy('t.ano')
            ->orderBy('m.id')
            ->get([
                'm.id as idMatricula',
                'm.idTerlec',
                'm.idCursos',
            ]);

        if ($matriculas->isEmpty()) {
            return [];
        }

        $out = [];
        $idsMatricula = $matriculas->pluck('idMatricula')->map(fn ($id) => (int) $id)->all();
        $califs = self::calificacionesPorMatriculas($idLegajos, $idsMatricula);

        foreach ($matriculas as $mat) {
            $idMatricula = (int) $mat->idMatricula;
            $idTerlec = (int) $mat->idTerlec;
            $idCursos = (int) $mat->idCursos;

            $materias = DB::table('materias as ma')
                ->where('ma.idCursos', $idCursos)
                ->where('ma.idTerlec', $idTerlec)
                ->orderBy('ma.ord')
                ->orderBy('ma.id')
                ->get(['ma.id', 'ma.materia', 'ma.ord']);

            foreach ($materias as $ma) {
                $idMaterias = (int) ($ma->id ?? 0);
                $materia = trim((string) ($ma->materia ?? ''));
                if ($materia === '') {
                    continue;
                }

                if ($idMaterias > 0 && array_key_exists($idMaterias, $nombresMaterias)) {
                    // Vacío permitido: oculta el nombre de materias en el PDF del analítico.
                    $materia = trim((string) $nombresMaterias[$idMaterias]);
                }

                $cal = $califs[$idMatricula][$idMaterias] ?? null;
                $out[] = self::mapearFila(
                    $materia,
                    trim((string) ($cal->calif ?? '')),
                    trim((string) ($cal->cond ?? '')),
                    self::mostrarMes($cal->mes ?? null),
                    self::mostrarAno($cal->ano ?? null),
                    trim((string) ($cal->escuapro ?? '')),
                );
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $idsMatricula
     * @return array<int, array<int, object>>
     */
    private static function calificacionesPorMatriculas(int $idLegajos, array $idsMatricula): array
    {
        if ($idsMatricula === []) {
            return [];
        }

        $rows = DB::table('calificaciones')
            ->where('idLegajos', $idLegajos)
            ->whereIn('idMatricula', $idsMatricula)
            ->where('ord', '<', 16)
            ->orderBy('id')
            ->get(['id', 'idMatricula', 'idMaterias', 'calif', 'mes', 'ano', 'cond', 'escuapro']);

        $out = [];
        foreach ($rows as $row) {
            $idMatricula = (int) ($row->idMatricula ?? 0);
            $idMaterias = (int) ($row->idMaterias ?? 0);
            if ($idMatricula < 1 || $idMaterias < 1) {
                continue;
            }
            // Si hubiera duplicados, conservar el primero (orden por id).
            if (isset($out[$idMatricula][$idMaterias])) {
                continue;
            }
            $out[$idMatricula][$idMaterias] = $row;
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
                'cond' => self::etiquetaCondicionImpreso($cond),
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
            'cond' => self::etiquetaCondicionImpreso($cond),
            'mes' => $mes,
            'ano' => $ano,
            'escuapro' => $escuapro,
            'modo' => 'nota',
        ];
    }

    /** Etiqueta de condición para el PDF (Pase / Analítico). */
    private static function etiquetaCondicionImpreso(string $cond): string
    {
        $codigo = strtoupper(trim($cond));

        return match ($codigo) {
            'PR' => 'Prev.',
            default => $cond,
        };
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
