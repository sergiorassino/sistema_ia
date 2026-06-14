<?php

namespace App\Support\Cooperadora;

use App\Models\CoopEgreso;
use App\Models\CoopProveedor;
use Illuminate\Support\Facades\DB;

final class RegistroEgresoService
{
    /**
     * @param  array{
     *   id_proveedor: int,
     *   fecha: string,
     *   concepto: string,
     *   importe: float,
     *   firmante?: string|null,
     *   id_medio_pago: int,
     * }  $datos
     */
    public static function registrar(array $datos): CoopEgreso
    {
        return DB::transaction(function () use ($datos) {
            CoopProveedor::query()->where('activo', true)->findOrFail((int) $datos['id_proveedor']);

            $importe = round((float) $datos['importe'], 2);
            $ordenNum = NumeroDocumentoCooperadora::reservarOrdenPago();
            $medio = MedioPagoCooperadora::resolver((int) $datos['id_medio_pago']);
            abort_unless($medio !== null, 422);

            return CoopEgreso::query()->create([
                'id_proveedor' => (int) $datos['id_proveedor'],
                'fecha' => $datos['fecha'],
                'concepto' => trim((string) $datos['concepto']),
                'importe' => $importe,
                'importe_letras' => ImporteEnLetrasEs::pesos($importe),
                'orden_numero' => $ordenNum,
                'firmante' => trim((string) ($datos['firmante'] ?? '')) ?: null,
                'id_medio_pago' => $medio['id'],
                'medio_pago' => $medio['nombre'],
                'id_profesor' => (int) schoolCtx()->idProfesor,
                'anulado' => false,
            ]);
        });
    }
}
