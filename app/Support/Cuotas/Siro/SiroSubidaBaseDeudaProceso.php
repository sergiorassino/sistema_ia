<?php

namespace App\Support\Cuotas\Siro;

use App\Models\CuotaGenerada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\Cuotas\CuponAPagarEmision;

/**
 * Procesamiento final: arma archivo, actualiza {@see CuotaGenerada::ultUpload}
 * y registra cupón en {@see \App\Models\CuponAPagar}.
 */
final class SiroSubidaBaseDeudaProceso
{
    /**
     * @param  Collection<int, CuotaGenerada>  $registros
     * @return array{
     *     archivo: array{contenido: string, nombre: string, cantidad: int, totalImporte1: float},
     *     procesados: int,
     *     omitidos: int
     * }
     */
    public static function procesar(Collection $registros): array
    {
        $detalles = [];

        foreach ($registros as $registro) {
            $eval = SiroSubidaBaseDeudaRegistro::evaluar($registro);
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

        DB::transaction(function () use ($detalles, $archivo, $porId): void {
            $ids = array_map(fn (array $d) => (int) ($d['idCuotaGenerada'] ?? 0), $detalles);

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
