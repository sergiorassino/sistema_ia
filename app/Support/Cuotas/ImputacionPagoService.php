<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotaPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra un pago manual en cuotaspagos y actualiza cuotasgeneradas.
 */
final class ImputacionPagoService
{
    /**
     * @param  array{
     *     idCuotastipopago: int,
     *     saldoAPagar: float,
     *     interes: float,
     *     bonificacion: float,
     *     aPagar: float,
     *     fechaPago: string,
     *     obs: string,
     *     avisoPago: bool
     * }  $datos
     */
    public static function registrar(CuotaGenerada $registro, array $datos): ?CuotaPago
    {
        $saldo = round(max(0, (float) $datos['saldoAPagar']), 2);
        $interes = round(max(0, (float) $datos['interes']), 2);
        $bonificacion = round(max(0, (float) $datos['bonificacion']), 2);
        $aPagar = round(max(0, (float) $datos['aPagar']), 2);
        $fechaPago = Carbon::parse($datos['fechaPago'])->startOfDay();
        $avisoPago = (bool) ($datos['avisoPago'] ?? false);
        $obs = trim((string) ($datos['obs'] ?? ''));

        $pagoCreado = null;

        DB::transaction(function () use ($registro, $saldo, $interes, $bonificacion, $aPagar, $fechaPago, $avisoPago, $obs, $datos, &$pagoCreado) {
            $locked = CuotaGenerada::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();

            if ($saldo > 0 || $aPagar > 0) {
                $pagoCreado = CuotaPago::query()->create([
                    'idCuotasGeneradas' => (int) $locked->id,
                    'idCuotastipopago' => (int) $datos['idCuotastipopago'],
                    'fechhora' => $fechaPago->format('Y-m-d H:i:s'),
                    'importe' => $saldo,
                    'bonificacion' => $bonificacion,
                    'interes' => $interes,
                    'nombreArchivo' => 'Imputación manual',
                    'cadenaPago' => '',
                ]);

                $nuevoPagado = round((float) $locked->pagado + $aPagar, 2);
                $nuevoFaltapa = round(max(0, (float) $locked->faltapa - $saldo), 2);
                $nuevoInteres = round((float) $locked->interes + $interes, 2);
                $nuevoBonif = round((float) $locked->bonificacion + $bonificacion, 2);

                $locked->pagado = $nuevoPagado;
                $locked->faltapa = $nuevoFaltapa;
                $locked->interes = $nuevoInteres;
                $locked->bonificacion = $nuevoBonif;
                $locked->fechaPago = $fechaPago->format('Y-m-d H:i:s');
            }

            if ($obs !== '') {
                $locked->obs = $obs;
            }

            $locked->avisoPago = $avisoPago ? 1 : 0;
            $locked->save();
        });

        return $pagoCreado;
    }
}
