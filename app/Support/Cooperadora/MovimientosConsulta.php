<?php

namespace App\Support\Cooperadora;

use App\Models\CoopEgreso;
use App\Models\CoopIngreso;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class MovimientosConsulta
{
    /**
     * @return Collection<int, object{
     *   fecha: string,
     *   tipo_mov: string,
     *   numero: int,
     *   detalle: string,
     *   ingreso: float,
     *   egreso: float,
     *   anulado: bool,
     *   importe_anulado: float,
     *   id_registro: int,
     * }>
     */
    public static function listado(string $fechaDesde, string $fechaHasta, ?MovimientosFiltros $filtros = null): Collection
    {
        $filtros ??= new MovimientosFiltros;
        $desde = Carbon::parse($fechaDesde)->startOfDay();
        $hasta = Carbon::parse($fechaHasta)->endOfDay();

        $ingresos = collect();
        if ($filtros->incluyeIngresos()) {
            $q = CoopIngreso::query()
                ->with(['rubro:id,nombre', 'item:id,nombre'])
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

            if ((int) $filtros->idRubro > 0) {
                $q->where('id_rubro', (int) $filtros->idRubro);
            }
            if ((int) $filtros->idItem > 0) {
                $q->where('id_item', (int) $filtros->idItem);
            }
            if ($filtros->tipoIngreso !== '') {
                $q->where('tipo', $filtros->tipoIngreso);
            }
            if ((int) $filtros->idMedioPago > 0) {
                $q->where('id_medio_pago', (int) $filtros->idMedioPago);
            }
            if ($filtros->busqueda !== '') {
                $termino = '%'.$filtros->busqueda.'%';
                $q->where(function ($query) use ($termino) {
                    $query->where('pagador_nombre', 'like', $termino)
                        ->orWhere('concepto', 'like', $termino)
                        ->orWhereHas('rubro', fn ($rubro) => $rubro->where('nombre', 'like', $termino))
                        ->orWhereHas('item', fn ($item) => $item->where('nombre', 'like', $termino));
                });
            }

            $ingresos = $q
                ->orderBy('fecha')
                ->orderBy('recibo_numero')
                ->get()
                ->map(function (CoopIngreso $row) {
                    $anulado = (bool) $row->anulado;
                    $importe = (float) $row->importe;

                    return (object) [
                        'fecha' => $row->fecha->format('Y-m-d'),
                        'tipo_mov' => 'ingreso',
                        'numero' => (int) $row->recibo_numero,
                        'detalle' => self::detalleIngreso($row),
                        'ingreso' => $anulado ? 0.0 : $importe,
                        'egreso' => 0.0,
                        'anulado' => $anulado,
                        'importe_anulado' => $anulado ? $importe : 0.0,
                        'id_registro' => (int) $row->id,
                    ];
                });
        }

        $egresos = collect();
        if ($filtros->incluyeEgresos()) {
            $q = CoopEgreso::query()
                ->with(['proveedor:id,nombre'])
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

            if ((int) $filtros->idProveedor > 0) {
                $q->where('id_proveedor', (int) $filtros->idProveedor);
            }
            if ((int) $filtros->idMedioPago > 0) {
                $q->where('id_medio_pago', (int) $filtros->idMedioPago);
            }
            if ($filtros->busqueda !== '') {
                $termino = '%'.$filtros->busqueda.'%';
                $q->where(function ($query) use ($termino) {
                    $query->where('concepto', 'like', $termino)
                        ->orWhereHas('proveedor', fn ($proveedor) => $proveedor->where('nombre', 'like', $termino));
                });
            }

            $egresos = $q
                ->orderBy('fecha')
                ->orderBy('orden_numero')
                ->get()
                ->map(function (CoopEgreso $row) {
                    $anulado = (bool) $row->anulado;
                    $importe = (float) $row->importe;

                    return (object) [
                        'fecha' => $row->fecha->format('Y-m-d'),
                        'tipo_mov' => 'egreso',
                        'numero' => (int) $row->orden_numero,
                        'detalle' => trim((string) ($row->proveedor?->nombre ?? '')).' — '.mb_substr((string) $row->concepto, 0, 80),
                        'ingreso' => 0.0,
                        'egreso' => $anulado ? 0.0 : $importe,
                        'anulado' => $anulado,
                        'importe_anulado' => $anulado ? $importe : 0.0,
                        'id_registro' => (int) $row->id,
                    ];
                });
        }

        return $ingresos
            ->concat($egresos)
            ->sortBy([
                ['fecha', 'asc'],
                ['tipo_mov', 'asc'],
                ['numero', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array{total_ingresos: float, total_egresos: float, saldo: float, filas_con_saldo: Collection<int, object>}
     */
    public static function conSaldoAcumulado(Collection $filas): array
    {
        $saldo = 0.0;
        $totalIngresos = 0.0;
        $totalEgresos = 0.0;

        $conSaldo = $filas->map(function ($fila) use (&$saldo, &$totalIngresos, &$totalEgresos) {
            $totalIngresos += (float) $fila->ingreso;
            $totalEgresos += (float) $fila->egreso;
            $saldo += (float) $fila->ingreso - (float) $fila->egreso;
            $fila->saldo = round($saldo, 2);

            return $fila;
        });

        return [
            'total_ingresos' => round($totalIngresos, 2),
            'total_egresos' => round($totalEgresos, 2),
            'saldo' => round($saldo, 2),
            'filas_con_saldo' => $conSaldo,
        ];
    }

    private static function detalleIngreso(CoopIngreso $row): string
    {
        $partes = array_filter([
            trim((string) ($row->rubro?->nombre ?? '')),
            trim((string) ($row->item?->nombre ?? '')),
            trim((string) $row->pagador_nombre),
        ]);

        return implode(' — ', $partes);
    }
}
