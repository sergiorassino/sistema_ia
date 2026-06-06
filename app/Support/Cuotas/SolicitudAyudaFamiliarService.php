<?php

namespace App\Support\Cuotas;

use App\Models\DatoVario;
use App\Models\SolibecaHist;
use Illuminate\Support\Facades\DB;

/**
 * Numeración e historial de solicitudes de ayuda familiar (legacy ScriptCase).
 */
final class SolicitudAyudaFamiliarService
{
    /**
     * Incrementa `datosvarios.ultimaSoliBeca`, registra en `solibecahist` y devuelve el número asignado.
     */
    public static function reservarNumero(int $idLegajo): int
    {
        if ($idLegajo < 1 || GestionAranceles::legajoParaGestion($idLegajo) === null) {
            throw new \InvalidArgumentException('Estudiante no válido para la solicitud.');
        }

        return (int) DB::transaction(function () use ($idLegajo) {
            $datos = DatoVario::query()->whereKey(1)->lockForUpdate()->first();
            if ($datos === null) {
                throw new \RuntimeException('No se encontró el registro de numeración en datosvarios (id = 1).');
            }

            $nro = (int) ($datos->ultimaSoliBeca ?? 0) + 1;
            $datos->update(['ultimaSoliBeca' => $nro]);

            SolibecaHist::query()->create([
                'idLegajos' => $idLegajo,
                'fecha' => now()->toDateString(),
                'nro' => $nro,
            ]);

            return $nro;
        });
    }
}
