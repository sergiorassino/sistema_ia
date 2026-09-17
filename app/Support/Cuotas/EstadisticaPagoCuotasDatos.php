<?php

namespace App\Support\Cuotas;

use App\Models\Cuota;
use App\Support\SchoolAlcancePedagogico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregados de pago (pagadas / no pagadas) por plantilla de cuota del ciclo activo.
 */
final class EstadisticaPagoCuotasDatos
{
    public const COLOR_PAGADA = '#40848D';

    public const COLOR_NO_PAGADA = '#A65D57';

    /**
     * Una cuota generada está pagada cuando no queda saldo pendiente.
     */
    public static function estaPagada(float $faltapa): bool
    {
        return round($faltapa, 2) <= 0;
    }

    /**
     * Porcentaje 0–100 con un decimal. Si total es 0, devuelve 0.
     */
    public static function porcentaje(int|float $parte, int|float $total): float
    {
        $total = (float) $total;
        if ($total <= 0) {
            return 0.0;
        }

        return round(((float) $parte / $total) * 100, 1);
    }

    /**
     * Complemento para que pagadas + no pagadas sumen 100 en el gráfico.
     * Si el porcentaje de pagadas es 0, el complemento es 100 (todas no pagadas).
     */
    public static function porcentajeComplemento(float $porcentaje): float
    {
        if ($porcentaje >= 100) {
            return 0.0;
        }
        if ($porcentaje <= 0) {
            return 100.0;
        }

        return round(100 - $porcentaje, 1);
    }

    /**
     * Plantillas del ciclo de sesión, ordenadas como en el ABM.
     *
     * @return Collection<int, Cuota>
     */
    public static function cuotasDelCicloParaSelector(): Collection
    {
        return CuotasPlantillaCatalog::cuotasDelCicloParaSelector();
    }

    /**
     * @return list<array{id: int, nombre: string, abrev: string}>
     */
    public static function nivelesParaSelector(): array
    {
        return ListadoEstudiantesPorCuotaDatos::nivelesParaSelector();
    }

    /**
     * @param  list<int>  $idsCuotas
     * @return list<array{
     *     id: int,
     *     etiqueta: string,
     *     total: int,
     *     pagadas: int,
     *     no_pagadas: int,
     *     pct_pagadas: float,
     *     pct_no_pagadas: float,
     *     importe: float,
     *     pagado: float,
     *     saldo: float,
     *     pct_cobrado: float
     * }>
     */
    public static function build(array $idsCuotas, int $idNivel = 0): array
    {
        $idTerlec = CuotasPlantillaCatalog::idTerlecActivo();
        if ($idTerlec <= 0 || $idsCuotas === []) {
            return [];
        }

        $permitidas = self::cuotasDelCicloParaSelector()
            ->keyBy(fn (Cuota $cuota) => (int) $cuota->id);

        $ids = [];
        foreach ($idsCuotas as $id) {
            $id = (int) $id;
            if ($id > 0 && $permitidas->has($id)) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return [];
        }

        $agregados = self::consultarAgregados($idTerlec, $ids, $idNivel);
        $filas = [];

        foreach ($ids as $idCuota) {
            /** @var Cuota|null $plantilla */
            $plantilla = $permitidas->get($idCuota);
            $agregado = $agregados[$idCuota] ?? [
                'total' => 0,
                'pagadas' => 0,
                'no_pagadas' => 0,
                'importe' => 0.0,
                'pagado' => 0.0,
                'saldo' => 0.0,
            ];

            $filas[] = self::armarFila(
                $idCuota,
                $plantilla !== null ? CuotasPlantillaCatalog::etiquetaCuota($plantilla) : 'Cuota #'.$idCuota,
                $agregado,
            );
        }

        return $filas;
    }

    /**
     * @param  array{total?: int|float, pagadas?: int|float, no_pagadas?: int|float, importe?: float, pagado?: float, saldo?: float}  $agregado
     * @return array{
     *     id: int,
     *     etiqueta: string,
     *     total: int,
     *     pagadas: int,
     *     no_pagadas: int,
     *     pct_pagadas: float,
     *     pct_no_pagadas: float,
     *     importe: float,
     *     pagado: float,
     *     saldo: float,
     *     pct_cobrado: float
     * }
     */
    public static function armarFila(int $id, string $etiqueta, array $agregado): array
    {
        $total = (int) ($agregado['total'] ?? 0);
        $pagadas = (int) ($agregado['pagadas'] ?? 0);
        $noPagadas = (int) ($agregado['no_pagadas'] ?? max(0, $total - $pagadas));
        $pctPagadas = self::porcentaje($pagadas, $total);
        $importe = round((float) ($agregado['importe'] ?? 0), 2);
        $pagado = round((float) ($agregado['pagado'] ?? 0), 2);

        return [
            'id' => $id,
            'etiqueta' => $etiqueta,
            'total' => $total,
            'pagadas' => $pagadas,
            'no_pagadas' => $noPagadas,
            'pct_pagadas' => $pctPagadas,
            'pct_no_pagadas' => $total > 0 ? self::porcentajeComplemento($pctPagadas) : 0.0,
            'importe' => $importe,
            'pagado' => $pagado,
            'saldo' => round((float) ($agregado['saldo'] ?? 0), 2),
            'pct_cobrado' => self::porcentaje($pagado, $importe),
        ];
    }

    /**
     * @param  list<array{etiqueta: string, pct_pagadas: float, pct_no_pagadas: float}>  $filas
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function chartBarrasComparativo(array $filas): array
    {
        $labels = [];
        $pagadas = [];
        $noPagadas = [];

        foreach ($filas as $fila) {
            $labels[] = (string) ($fila['etiqueta'] ?? '');
            $pagadas[] = (float) ($fila['pct_pagadas'] ?? 0);
            $noPagadas[] = (float) ($fila['pct_no_pagadas'] ?? 0);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Pagadas (%)',
                    'data' => $pagadas,
                    'backgroundColor' => self::COLOR_PAGADA,
                    'stack' => 's1',
                ],
                [
                    'label' => 'No pagadas (%)',
                    'data' => $noPagadas,
                    'backgroundColor' => self::COLOR_NO_PAGADA,
                    'stack' => 's1',
                ],
            ],
        ];
    }

    /**
     * @param  array{pct_pagadas: float, pct_no_pagadas: float}  $fila
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    public static function chartDona(array $fila): array
    {
        return [
            'labels' => ['Pagadas', 'No pagadas'],
            'datasets' => [[
                'data' => [
                    (float) ($fila['pct_pagadas'] ?? 0),
                    (float) ($fila['pct_no_pagadas'] ?? 0),
                ],
                'backgroundColor' => [self::COLOR_PAGADA, self::COLOR_NO_PAGADA],
                'borderWidth' => 1,
            ]],
        ];
    }

    /**
     * @param  list<int>  $idsCuotas
     * @return array<int, array{total: int, pagadas: int, no_pagadas: int, importe: float, pagado: float, saldo: float}>
     */
    private static function consultarAgregados(int $idTerlec, array $idsCuotas, int $idNivel): array
    {
        $query = DB::table('cuotasgeneradas')
            ->join('cursos', 'cursos.Id', '=', 'cuotasgeneradas.idCursos')
            ->where('cuotasgeneradas.idTerlec', $idTerlec)
            ->whereIn('cuotasgeneradas.idCuotas', $idsCuotas)
            ->where('cuotasgeneradas.importe', '>', 0);

        $nivelesPermitidos = collect(self::nivelesParaSelector())->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($idNivel > 0 && in_array($idNivel, $nivelesPermitidos, true)) {
            $query->where('cursos.idNivel', $idNivel);
        } else {
            SchoolAlcancePedagogico::aplicarFiltroColumnaNivel($query, 'cursos.idNivel');
        }

        $rows = $query
            ->groupBy('cuotasgeneradas.idCuotas')
            ->selectRaw('cuotasgeneradas.idCuotas as id_cuota')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN ROUND(cuotasgeneradas.faltapa, 2) <= 0 THEN 1 ELSE 0 END) as pagadas')
            ->selectRaw('SUM(CASE WHEN ROUND(cuotasgeneradas.faltapa, 2) > 0 THEN 1 ELSE 0 END) as no_pagadas')
            ->selectRaw('SUM(cuotasgeneradas.importe) as importe')
            ->selectRaw('SUM(cuotasgeneradas.pagado) as pagado')
            ->selectRaw('SUM(cuotasgeneradas.faltapa) as saldo')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id_cuota ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = [
                'total' => (int) ($row->total ?? 0),
                'pagadas' => (int) ($row->pagadas ?? 0),
                'no_pagadas' => (int) ($row->no_pagadas ?? 0),
                'importe' => (float) ($row->importe ?? 0),
                'pagado' => (float) ($row->pagado ?? 0),
                'saldo' => (float) ($row->saldo ?? 0),
            ];
        }

        return $out;
    }
}
