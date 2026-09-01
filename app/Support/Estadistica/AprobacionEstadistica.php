<?php

namespace App\Support\Estadistica;

use App\Support\OrdenAlfabeticoEstudiante;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de aprobación por materia: durante el año (núcleos + JIS), diciembre (dic) y febrero (feb).
 */
final class AprobacionEstadistica
{
    private const NOTA_MINIMA = 7;

    /** @var list<list<string>> */
    private const BLOQUES_IC = [
        ['ic01', 'ic02', 'ic03'],
        ['ic04', 'ic05', 'ic06'],
        ['ic07', 'ic08', 'ic09'],
        ['ic10', 'ic11', 'ic12'],
        ['ic13', 'ic14', 'ic15'],
        ['ic16', 'ic17', 'ic18'],
        ['ic19', 'ic20', 'ic21'],
        ['ic22', 'ic23', 'ic24'],
        ['ic25', 'ic26'],
        ['ic27', 'ic28'],
    ];

    /** @var list<string> */
    private const CAMPOS_IC = [
        'ic01', 'ic02', 'ic03', 'ic04', 'ic05', 'ic06',
        'ic07', 'ic08', 'ic09', 'ic10', 'ic11', 'ic12',
        'ic13', 'ic14', 'ic15', 'ic16', 'ic17', 'ic18',
        'ic19', 'ic20', 'ic21', 'ic22', 'ic23', 'ic24',
        'ic25', 'ic26', 'ic27', 'ic28',
    ];

    public static function notaAprobada(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        $n = is_numeric($valor) ? (float) $valor : null;

        return $n !== null && $n >= self::NOTA_MINIMA;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function materiaSinNingunaNota(array $row): bool
    {
        foreach (self::CAMPOS_IC as $c) {
            $v = $row[$c] ?? '';
            if ($v !== '' && $v !== null) {
                return false;
            }
        }

        $dic = $row['dic'] ?? '';
        $feb = $row['feb'] ?? '';

        return ($dic === '' || $dic === null) && ($feb === '' || $feb === null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{durante_anio: bool, diciembre: bool, febrero: bool}
     */
    public static function estadoAprobacionPorMateria(array $row): array
    {
        $tieneAlgunaNota = false;
        $duranteAnio = true;

        foreach (self::BLOQUES_IC as $campos) {
            $tieneNotaEnBloque = false;
            $algunoAprobadoEnBloque = false;

            foreach ($campos as $c) {
                $v = $row[$c] ?? '';
                if ($v === '' || $v === null) {
                    continue;
                }

                $tieneAlgunaNota = true;
                $tieneNotaEnBloque = true;

                if (self::notaAprobada($v)) {
                    $algunoAprobadoEnBloque = true;
                }
            }

            if ($tieneNotaEnBloque && ! $algunoAprobadoEnBloque) {
                $duranteAnio = false;
            }
        }

        if (! $tieneAlgunaNota) {
            $duranteAnio = false;
        }

        return [
            'durante_anio' => $duranteAnio,
            'diciembre' => self::notaAprobada($row['dic'] ?? ''),
            'febrero' => self::notaAprobada($row['feb'] ?? ''),
        ];
    }

    /**
     * Vía efectiva de aprobación de una materia (prioridad: año → dic → feb → pendiente).
     *
     * @param  array<string, mixed>  $row
     */
    public static function viaAprobacionPorMateria(array $row): string
    {
        $estado = self::estadoAprobacionPorMateria($row);

        if ($estado['durante_anio']) {
            return 'anio';
        }
        if ($estado['diciembre']) {
            return 'dic';
        }
        if ($estado['febrero']) {
            return 'feb';
        }

        return 'pendiente';
    }

    /**
     * Clasificación de promoción anual según el desglose de vías por materia.
     *
     * @param  array{anio: int, dic: int, feb: int, pendiente: int}  $vias
     */
    public static function clasificarPromocionPorVias(array $vias): string
    {
        if (($vias['pendiente'] ?? 0) > 0) {
            return 'no_promovido';
        }

        $total = (int) ($vias['anio'] ?? 0) + (int) ($vias['dic'] ?? 0) + (int) ($vias['feb'] ?? 0);
        if ($total === 0) {
            return 'no_promovido';
        }

        if (($vias['dic'] ?? 0) === 0 && ($vias['feb'] ?? 0) === 0) {
            return 'promovido_anio';
        }

        if (($vias['feb'] ?? 0) > 0) {
            return 'promovido_feb';
        }

        return 'promovido_dic';
    }

    /** @return array{total_estudiantes: int, promovidos_anio: int, promovidos_dic: int, promovidos_feb: int, no_promovidos: int} */
    public static function contadorPromocionVacio(): array
    {
        return [
            'total_estudiantes' => 0,
            'promovidos_anio' => 0,
            'promovidos_dic' => 0,
            'promovidos_feb' => 0,
            'no_promovidos' => 0,
        ];
    }

    /**
     * @param  array{total_estudiantes: int, promovidos_anio: int, promovidos_dic: int, promovidos_feb: int, no_promovidos: int}  $bucket
     */
    public static function acumularPromocionResumen(array &$bucket, string $promocion): void
    {
        $bucket['total_estudiantes']++;
        match ($promocion) {
            'promovido_anio' => $bucket['promovidos_anio']++,
            'promovido_dic' => $bucket['promovidos_dic']++,
            'promovido_feb' => $bucket['promovidos_feb']++,
            default => $bucket['no_promovidos']++,
        };
    }

    /**
     * Resumen + desglose por materia/curso en un solo recorrido de la BD.
     *
     * @return array{resumen: array{total: int, aprobados_durante_anio: int, aprobados_diciembre: int, aprobados_febrero: int, pendientes: int}, por_materia_curso: list<array<string, mixed>>}
     */
    public function reportePorMateria(
        int $idTerlec,
        ?int $idMaterias = null,
        ?int $idCursos = null,
        ?int $idNivel = null,
    ): array {
        $resumen = self::contadorResumenVacio();
        $agrupado = [];

        foreach ($this->cursorFilasMateriaCurso($idTerlec, null, $idMaterias, $idCursos, $idNivel) as $row) {
            $estado = self::estadoAprobacionPorMateria($row);
            self::acumularResumen($resumen, $estado);

            $key = ($row['idMaterias'] ?? 0).'_'.($row['idCursos'] ?? 0);
            if (! isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'materia' => $row['materia'] ?? '',
                    'curso' => $row['curso'] ?? '',
                    'idMaterias' => (int) ($row['idMaterias'] ?? 0),
                    'idCursos' => (int) ($row['idCursos'] ?? 0),
                    ...self::contadorDetalleVacio(),
                ];
            }

            self::acumularDetalle($agrupado[$key], $estado);
        }

        $porMateriaCurso = array_values($agrupado);
        self::ordenarPorMateriaCurso($porMateriaCurso);

        return [
            'resumen' => $resumen,
            'por_materia_curso' => $porMateriaCurso,
        ];
    }

    /**
     * Resumen + desglose por estudiante en un solo recorrido de la BD.
     *
     * @return array{
     *     resumen: array{total: int, aprobados_durante_anio: int, aprobados_diciembre: int, aprobados_febrero: int, pendientes: int},
     *     resumen_promocion: array{total_estudiantes: int, promovidos_anio: int, promovidos_dic: int, promovidos_feb: int, no_promovidos: int},
     *     por_estudiante: list<array<string, mixed>>
     * }
     */
    public function reportePorEstudiante(
        int $idTerlec,
        ?int $idCursos = null,
        ?int $idLegajos = null,
    ): array {
        $resumen = self::contadorResumenVacio();
        $resumenPromocion = self::contadorPromocionVacio();
        $agrupado = [];

        foreach ($this->cursorFilasEstudiante($idTerlec, $idCursos, $idLegajos) as $row) {
            $estado = self::estadoAprobacionPorMateria($row);
            self::acumularResumen($resumen, $estado);

            $idLeg = (int) ($row['idLegajos'] ?? 0);
            if (! isset($agrupado[$idLeg])) {
                $agrupado[$idLeg] = [
                    'idLegajos' => $idLeg,
                    'apellido' => trim($row['apellido'] ?? ''),
                    'nombre' => trim($row['nombre'] ?? ''),
                    'curso' => trim($row['curso'] ?? ''),
                    ...self::contadorDetalleVacio(),
                    'tiene_tea' => false,
                    'sin_nota' => 0,
                    'vias_materias' => ['anio' => 0, 'dic' => 0, 'feb' => 0, 'pendiente' => 0],
                ];
            }

            if ((int) ($row['tea'] ?? 0) === 1) {
                $agrupado[$idLeg]['tiene_tea'] = true;
            }
            if (self::materiaSinNingunaNota($row)) {
                $agrupado[$idLeg]['sin_nota']++;
            }

            self::acumularDetalle($agrupado[$idLeg], $estado);

            $via = self::viaAprobacionPorMateria($row);
            $agrupado[$idLeg]['vias_materias'][$via]++;
        }

        foreach ($agrupado as &$estudiante) {
            $estudiante['promocion'] = self::clasificarPromocionPorVias($estudiante['vias_materias']);
            self::acumularPromocionResumen($resumenPromocion, $estudiante['promocion']);
            unset($estudiante['vias_materias']);
        }
        unset($estudiante);

        $porEstudiante = array_values($agrupado);
        self::ordenarPorEstudiante($porEstudiante);

        return [
            'resumen' => $resumen,
            'resumen_promocion' => $resumenPromocion,
            'por_estudiante' => $porEstudiante,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function estadisticasPorDocente(int $idTerlec, ?int $idProfesor = null): array
    {
        $query = DB::table('calificaciones as c')
            ->join('matricula as mat', function ($join) {
                $join->on('mat.id', '=', 'c.idMatricula')
                    ->on('mat.idTerlec', '=', 'c.idTerlec')
                    ->where('mat.idCondiciones', '=', 1);
            })
            ->join('materias as m', 'm.id', '=', 'c.idMaterias')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->join('ppc', 'ppc.idMateria', '=', 'c.idMaterias')
            ->join('profesores as p', 'p.id', '=', 'ppc.idProfesor')
            ->where('c.idTerlec', $idTerlec)
            ->where('mat.idNivel', 3)
            ->where('m.idNivel', 3)
            ->where('m.idTerlec', $idTerlec)
            ->select($this->columnasNotas('c', [
                'c.idMaterias', 'c.idCursos',
                'm.materia', 'cu.cursec as curso', 'cu.orden as curso_orden',
                'm.ord as materia_orden',
                'p.id as idProfesor', 'p.apellido as prof_apellido', 'p.nombre as prof_nombre',
            ]));

        if ($idProfesor !== null && $idProfesor > 0) {
            $query->where('ppc.idProfesor', $idProfesor);
        }

        $agrupado = [];
        foreach ($query->cursor() as $row) {
            $arr = (array) $row;
            $key = ($arr['idProfesor'] ?? 0).'_'.($arr['idMaterias'] ?? 0);
            if (! isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'idProfesor' => (int) ($arr['idProfesor'] ?? 0),
                    'apellido' => $arr['prof_apellido'] ?? '',
                    'nombre' => $arr['prof_nombre'] ?? '',
                    'idMaterias' => (int) ($arr['idMaterias'] ?? 0),
                    'materia' => $arr['materia'] ?? '',
                    'curso' => $arr['curso'] ?? '',
                    'curso_orden' => (int) ($arr['curso_orden'] ?? 0),
                    'materia_orden' => (int) ($arr['materia_orden'] ?? 0),
                    ...self::contadorDetalleVacio(),
                ];
            }

            self::acumularDetalle($agrupado[$key], self::estadoAprobacionPorMateria($arr));
        }

        $filas = array_values($agrupado);
        usort($filas, static function (array $a, array $b): int {
            return [$a['apellido'], $a['nombre'], $a['curso_orden'], $a['curso'], $a['materia_orden'], $a['materia']]
                <=> [$b['apellido'], $b['nombre'], $b['curso_orden'], $b['curso'], $b['materia_orden'], $b['materia']];
        });

        foreach ($filas as &$fila) {
            unset($fila['curso_orden'], $fila['materia_orden']);
        }
        unset($fila);

        return $filas;
    }

    /** @return array{total: int, aprobados_durante_anio: int, aprobados_diciembre: int, aprobados_febrero: int, pendientes: int} */
    public function estadisticas(
        int $idTerlec,
        ?int $idLegajos = null,
        ?int $idMaterias = null,
        ?int $idCursos = null,
        ?int $idNivel = null,
    ): array {
        return $this->reportePorMateria($idTerlec, $idMaterias, $idCursos, $idNivel)['resumen'];
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function cursorFilasMateriaCurso(
        int $idTerlec,
        ?int $idLegajos,
        ?int $idMaterias,
        ?int $idCursos,
        ?int $idNivel,
    ): \Generator {
        $query = DB::table('calificaciones as c')
            ->join('matricula as mat', function ($join) {
                $join->on('mat.id', '=', 'c.idMatricula')
                    ->on('mat.idTerlec', '=', 'c.idTerlec')
                    ->where('mat.idCondiciones', '=', 1);
            })
            ->join('materias as m', 'm.id', '=', 'c.idMaterias')
            ->join('cursos as cu', 'cu.Id', '=', 'c.idCursos')
            ->where('c.idTerlec', $idTerlec)
            ->where('mat.idNivel', 3)
            ->where('m.idNivel', 3)
            ->select($this->columnasNotas('c', [
                'c.idMaterias', 'c.idCursos',
                'm.materia', 'cu.cursec as curso',
            ]));

        $this->aplicarFiltrosComunes($query, $idLegajos, $idMaterias, $idCursos, $idNivel);

        foreach ($query->cursor() as $row) {
            yield (array) $row;
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function cursorFilasEstudiante(int $idTerlec, ?int $idCursos, ?int $idLegajos): \Generator
    {
        $query = DB::table('calificaciones as c')
            ->join('matricula as mat', function ($join) {
                $join->on('mat.id', '=', 'c.idMatricula')
                    ->on('mat.idTerlec', '=', 'c.idTerlec')
                    ->where('mat.idCondiciones', '=', 1);
            })
            ->join('materias as m', function ($join) {
                $join->on('m.id', '=', 'c.idMaterias')->where('m.idNivel', '=', 3);
            })
            ->join('legajos as l', 'l.id', '=', 'mat.idLegajos')
            ->join('cursos as cu', function ($join) {
                $join->on('cu.Id', '=', 'mat.idCursos')->on('cu.idTerlec', '=', 'mat.idTerlec');
            })
            ->where('c.idTerlec', $idTerlec)
            ->where('mat.idNivel', 3)
            ->select($this->columnasNotas('c', [
                'c.tea',
                'mat.idLegajos', 'l.apellido', 'l.nombre', 'cu.cursec as curso',
            ]));

        if ($idCursos !== null && $idCursos > 0) {
            $query->where('mat.idCursos', $idCursos);
        }
        if ($idLegajos !== null && $idLegajos > 0) {
            $query->where('mat.idLegajos', $idLegajos);
        }

        foreach ($query->cursor() as $row) {
            yield (array) $row;
        }
    }

    private function aplicarFiltrosComunes(
        Builder $query,
        ?int $idLegajos,
        ?int $idMaterias,
        ?int $idCursos,
        ?int $idNivel,
    ): void {
        if ($idLegajos !== null && $idLegajos > 0) {
            $query->where('c.idLegajos', $idLegajos);
        }
        if ($idMaterias !== null && $idMaterias > 0) {
            $query->where('c.idMaterias', $idMaterias);
        }
        if ($idCursos !== null && $idCursos > 0) {
            $query->where('c.idCursos', $idCursos);
        }
        if ($idNivel !== null && $idNivel > 0) {
            $query->where('m.idNivel', $idNivel);
        }
    }

    /**
     * @param  list<string>  $extra
     * @return list<string|\Illuminate\Contracts\Database\Query\Expression>
     */
    private function columnasNotas(string $alias, array $extra = []): array
    {
        $cols = [];
        foreach (self::CAMPOS_IC as $c) {
            $cols[] = "{$alias}.{$c}";
        }

        return array_merge($cols, ["{$alias}.dic", "{$alias}.feb"], $extra);
    }

    /** @return array{total: int, aprobados_durante_anio: int, aprobados_diciembre: int, aprobados_febrero: int, pendientes: int} */
    private static function contadorResumenVacio(): array
    {
        return [
            'total' => 0,
            'aprobados_durante_anio' => 0,
            'aprobados_diciembre' => 0,
            'aprobados_febrero' => 0,
            'pendientes' => 0,
        ];
    }

    /** @return array{total: int, durante_anio: int, diciembre: int, febrero: int, pendientes: int} */
    private static function contadorDetalleVacio(): array
    {
        return [
            'total' => 0,
            'durante_anio' => 0,
            'diciembre' => 0,
            'febrero' => 0,
            'pendientes' => 0,
        ];
    }

    /**
     * @param  array{total: int, aprobados_durante_anio: int, aprobados_diciembre: int, aprobados_febrero: int, pendientes: int}  $bucket
     * @param  array{durante_anio: bool, diciembre: bool, febrero: bool}  $estado
     */
    private static function acumularResumen(array &$bucket, array $estado): void
    {
        $bucket['total']++;
        if ($estado['durante_anio']) {
            $bucket['aprobados_durante_anio']++;
        }
        if ($estado['diciembre']) {
            $bucket['aprobados_diciembre']++;
        }
        if ($estado['febrero']) {
            $bucket['aprobados_febrero']++;
        }
        if (! $estado['durante_anio'] && ! $estado['diciembre'] && ! $estado['febrero']) {
            $bucket['pendientes']++;
        }
    }

    /**
     * @param  array{total: int, durante_anio: int, diciembre: int, febrero: int, pendientes: int}  $bucket
     * @param  array{durante_anio: bool, diciembre: bool, febrero: bool}  $estado
     */
    private static function acumularDetalle(array &$bucket, array $estado): void
    {
        $bucket['total']++;
        if ($estado['durante_anio']) {
            $bucket['durante_anio']++;
        }
        if ($estado['diciembre']) {
            $bucket['diciembre']++;
        }
        if ($estado['febrero']) {
            $bucket['febrero']++;
        }
        if (! $estado['durante_anio'] && ! $estado['diciembre'] && ! $estado['febrero']) {
            $bucket['pendientes']++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private static function ordenarPorMateriaCurso(array &$filas): void
    {
        usort($filas, static fn (array $a, array $b): int => [$a['curso'] ?? '', $a['materia'] ?? '']
            <=> [$b['curso'] ?? '', $b['materia'] ?? '']);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private static function ordenarPorEstudiante(array &$filas): void
    {
        usort($filas, static fn (array $a, array $b): int => OrdenAlfabeticoEstudiante::comparar(
            (string) ($a['apellido'] ?? ''),
            (string) ($a['nombre'] ?? ''),
            (string) ($b['apellido'] ?? ''),
            (string) ($b['nombre'] ?? ''),
        ));
    }
}
