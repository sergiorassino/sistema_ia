<?php

namespace App\Support\InasistenciasDocentes;

use App\Support\InasistenciasDocentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cálculo de inasistencias a descuento.
 * Registros por {@see profesores.id}; totales consolidados por DNI en vivo ({@see InasistenciasDocentes::idsProfesoresMismoDni}).
 */
final class CalculoFaltasDescuento
{
    public const ID_CARGO_PROFESOR = 6;

    /** @var array<int, array{0: int, 1: int}> */
    public const BIMESTRE_MESES = [
        1 => [1, 2],
        2 => [3, 4],
        3 => [5, 6],
        4 => [7, 8],
        5 => [9, 10],
        6 => [11, 12],
    ];

    /**
     * @return array{total: float, justificadas: float, injustificadas: float}
     */
    public static function totalsBimestre(int $idProfesor, int $bimestre, int $anio): array
    {
        if ($bimestre < 1 || $bimestre > 6 || ! Schema::hasTable('inasdocentes')) {
            return ['total' => 0.0, 'justificadas' => 0.0, 'injustificadas' => 0.0];
        }

        [$mesIni, $mesFin] = self::BIMESTRE_MESES[$bimestre];
        $ids = InasistenciasDocentes::idsProfesoresMismoDni($idProfesor);

        $row = DB::table('inasdocentes')
            ->whereIn('idProfesores', $ids)
            ->whereYear('fecha', $anio)
            ->whereRaw('MONTH(fecha) IN (?, ?)', [$mesIni, $mesFin])
            ->selectRaw('IFNULL(SUM(cantObligIna), 0) as t, IFNULL(SUM(CASE WHEN justif = 1 THEN cantObligIna ELSE 0 END), 0) as j, IFNULL(SUM(CASE WHEN justif = 0 THEN cantObligIna ELSE 0 END), 0) as i')
            ->first();

        return [
            'total' => (float) ($row->t ?? 0),
            'justificadas' => (float) ($row->j ?? 0),
            'injustificadas' => (float) ($row->i ?? 0),
        ];
    }

    /**
     * @return array{
     *   total: float,
     *   justificadas: float,
     *   injustificadas: float,
     *   maxFaltasPosibles: int,
     *   totalDescuento: float,
     *   tieneFaltasDescuento: bool
     * }
     */
    public static function resumenBimestre(int $idProfesor, int $bimestre, int $anio): array
    {
        $totals = self::totalsBimestre($idProfesor, $bimestre, $anio);
        $calculo = self::calcular($idProfesor, $bimestre, $anio);

        return [
            'total' => $totals['total'],
            'justificadas' => $totals['justificadas'],
            'injustificadas' => $totals['injustificadas'],
            'maxFaltasPosibles' => (int) ($calculo['totalMaxFaltasPosibles'] ?? 0),
            'totalDescuento' => (float) ($calculo['totalDescuento'] ?? 0),
            'tieneFaltasDescuento' => (bool) ($calculo['tieneFaltasDescuento'] ?? false),
        ];
    }

    /**
     * @return array{tieneFaltasDescuento: bool, totalDescuento: float, totalMaxFaltasPosibles: int, detalle: array<int, array<string, mixed>>}
     */
    public static function calcular(int $idProfesor, int $bimestre, int $anio): array
    {
        if ($bimestre < 1 || $bimestre > 6 || ! self::moduloOperativo()) {
            return ['tieneFaltasDescuento' => false, 'totalDescuento' => 0, 'totalMaxFaltasPosibles' => 0, 'detalle' => []];
        }

        [$mesIni, $mesFin] = self::BIMESTRE_MESES[$bimestre];
        $ids = InasistenciasDocentes::idsProfesoresMismoDni($idProfesor);
        $totalDescuento = 0.0;
        $detalle = [];

        $cargos = DB::table('cargosxprofesor as cxp')
            ->join('niveles as n', 'n.id', '=', 'cxp.idNiveles')
            ->join('cargos as c', 'c.id', '=', 'cxp.idCargos')
            ->whereIn('cxp.idProfesores', $ids)
            ->where('cxp.idCargos', '<>', self::ID_CARGO_PROFESOR)
            ->orderBy('n.nivel')
            ->orderBy('c.cargo')
            ->get(['cxp.id', 'cxp.idNiveles', 'n.nivel', 'c.cargo', 'cxp.cant']);

        foreach ($cargos as $row) {
            $maxFaltasPosibles = (int) round((($row->cant * 2) / 100) * 10);
            $cantObligInju = self::sumaInjustificadasPorCargo(
                (int) $row->id,
                (int) $row->idNiveles,
                $mesIni,
                $mesFin,
                $anio
            );
            $aDescuento = max(0, $cantObligInju - $maxFaltasPosibles);
            $etiqueta = count($ids) > 1
                ? trim((string) $row->nivel).' — '.(string) $row->cargo
                : (string) $row->cargo;
            $detalle[] = [
                'cargo' => $etiqueta,
                'cantObligInju' => $cantObligInju,
                'maxFaltasPosibles' => $maxFaltasPosibles,
                'aDescuento' => $aDescuento,
            ];
            $totalDescuento += $aDescuento;
        }

        $cantHorProf = self::sumaCantidadCargoProfesor($ids);
        $cantObligInaProfInju = self::sumaInjustificadasProfesor($ids, $mesIni, $mesFin, $anio);
        $maxFaltasPosiblesProf = (int) round((((float) $cantHorProf * 4) * 2 / 100) * 10);
        $aDescuentoProf = max(0, $cantObligInaProfInju - $maxFaltasPosiblesProf);

        $detalle[] = [
            'cargo' => 'Profesor/a',
            'cantObligInju' => $cantObligInaProfInju,
            'maxFaltasPosibles' => $maxFaltasPosiblesProf,
            'aDescuento' => $aDescuentoProf,
        ];
        $totalDescuento += $aDescuentoProf;

        return [
            'tieneFaltasDescuento' => $totalDescuento > 0,
            'totalDescuento' => $totalDescuento,
            'totalMaxFaltasPosibles' => (int) array_sum(array_column($detalle, 'maxFaltasPosibles')),
            'detalle' => $detalle,
        ];
    }

    private static function moduloOperativo(): bool
    {
        return Schema::hasTable('inasdocentes')
            && Schema::hasTable('cargosxprofesor')
            && Schema::hasTable('cargos');
    }

    private static function sumaInjustificadasPorCargo(
        int $idCargosXProfesor,
        int $idNivel,
        int $mesIni,
        int $mesFin,
        int $anio
    ): float {
        $q = DB::table('inasdocentes')
            ->where('idCargosXProfesor', $idCargosXProfesor)
            ->where('justif', 0)
            ->whereYear('fecha', $anio)
            ->whereRaw('MONTH(fecha) IN (?, ?)', [$mesIni, $mesFin]);

        if (Schema::hasColumn('inasdocentes', 'idNivel')) {
            $q->where('idNivel', $idNivel);
        }

        return (float) $q->sum('cantObligIna');
    }

    /**
     * @param  array<int, int>  $idsProfesores
     */
    private static function sumaCantidadCargoProfesor(array $idsProfesores): float
    {
        return (float) DB::table('cargosxprofesor')
            ->whereIn('idProfesores', $idsProfesores)
            ->where('idCargos', self::ID_CARGO_PROFESOR)
            ->sum('cant');
    }

    /**
     * @param  array<int, int>  $idsProfesores
     */
    private static function sumaInjustificadasProfesor(array $idsProfesores, int $mesIni, int $mesFin, int $anio): float
    {
        return (float) DB::table('inasdocentes as i')
            ->join('cargosxprofesor as cxp', 'i.idCargosXProfesor', '=', 'cxp.id')
            ->whereIn('cxp.idProfesores', $idsProfesores)
            ->where('cxp.idCargos', self::ID_CARGO_PROFESOR)
            ->whereIn('i.idProfesores', $idsProfesores)
            ->where('i.justif', 0)
            ->whereYear('i.fecha', $anio)
            ->whereRaw('MONTH(i.fecha) IN (?, ?)', [$mesIni, $mesFin])
            ->sum('i.cantObligIna');
    }
}
