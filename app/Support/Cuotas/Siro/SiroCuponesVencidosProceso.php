<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use App\Support\Cuotas\CuponAPagarEmision;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Procesamiento de cupones vencidos: actualiza {@see CuotaGenerada::$nueVenc},
 * luego arma archivo SIRO, incrementa {@see CuotaGenerada::$ultUpload}
 * y registra en {@see \App\Models\CuponAPagar}.
 */
final class SiroCuponesVencidosProceso
{
    /**
     * @param  Collection<int, CuotaGenerada>  $registros
     * @return array{
     *     archivo: array{contenido: string, nombre: string, cantidad: int, totalImporte1: float},
     *     procesados: int,
     *     omitidos: int
     * }
     */
    public static function procesar(Collection $registros, string $fechaActualizarAl): array
    {
        $fecha = Carbon::parse($fechaActualizarAl)->startOfDay()->format('Y-m-d');
        $hoy = Carbon::today()->format('Y-m-d');
        $detalles = [];

        foreach ($registros as $registro) {
            $preparado = SiroCuponesVencidosRegistro::prepararParaEvaluar($registro, $fechaActualizarAl);
            $eval = SiroCuponesVencidosRegistro::evaluar($preparado);
            if (! $eval['subeSiro'] || $eval['detalle'] === null) {
                continue;
            }

            $detalles[] = $eval['detalle'];
        }

        if ($detalles === []) {
            return [
                'archivo' => SiroSubidaBaseDeudaArchivo::generar([]),
                'procesados' => 0,
                'omitidos' => $registros->count(),
            ];
        }

        $archivo = SiroSubidaBaseDeudaArchivo::generar($detalles);
        $porId = $registros->keyBy('id');

        DB::transaction(function () use ($detalles, $archivo, $porId, $fecha, $hoy): void {
            $ids = array_map(fn (array $d) => (int) ($d['idCuotaGenerada'] ?? 0), $detalles);

            CuotaGenerada::query()
                ->whereIn('id', $ids)
                ->where('faltapa', '>', 0)
                ->whereDate('venc2', '<', $hoy)
                ->update(['nueVenc' => $fecha]);

            $pendientes = CuotaGenerada::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($detalles as $detalle) {
                $id = (int) ($detalle['idCuotaGenerada'] ?? 0);
                $registro = $pendientes->get($id) ?? $porId->get($id);
                if ($registro === null) {
                    continue;
                }

                $registro->nueVenc = Carbon::parse($fecha)->startOfDay();

                CuponAPagarEmision::desdeSubidaSiro(
                    $registro,
                    $detalle,
                    (string) ($archivo['nombre'] ?? ''),
                );
            }
        });

        return [
            'archivo' => $archivo,
            'procesados' => count($detalles),
            'omitidos' => max(0, $registros->count() - count($detalles)),
        ];
    }
}
