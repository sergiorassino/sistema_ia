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
    /** Huso horario institucional para registrar fecha/hora de pago. */
    public const TIMEZONE_PAGO = 'America/Argentina/Buenos_Aires';

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
        $pagoCreado = null;

        DB::transaction(function () use ($registro, $datos, &$pagoCreado) {
            $pagoCreado = self::registrarInterno($registro, $datos);
        });

        return $pagoCreado;
    }

    /**
     * Imputa varias cuotas en una sola transacción.
     *
     * @param  list<array{registro: CuotaGenerada, datos: array{
     *     idCuotastipopago: int,
     *     saldoAPagar: float,
     *     interes: float,
     *     bonificacion: float,
     *     aPagar: float,
     *     fechaPago: string,
     *     obs: string,
     *     avisoPago: bool
     * }>  $items
     * @return list<CuotaPago>
     */
    public static function registrarLote(array $items): array
    {
        $pagos = [];

        DB::transaction(function () use ($items, &$pagos) {
            foreach ($items as $item) {
                $registro = $item['registro'] ?? null;
                if (! $registro instanceof CuotaGenerada) {
                    continue;
                }

                $pago = self::registrarInterno($registro, $item['datos']);
                if ($pago !== null) {
                    $pagos[] = $pago;
                }
            }
        });

        return $pagos;
    }

    /**
     * Valor actual (Argentina) para input HTML `datetime-local`.
     */
    public static function ahoraParaInput(): string
    {
        return Carbon::now(self::TIMEZONE_PAGO)->format('Y-m-d\TH:i');
    }

    /**
     * Formatea un datetime al valor de input `datetime-local` (sin segundos).
     */
    public static function paraInputDatetimeLocal(Carbon|\Carbon\CarbonInterface|string $fecha): string
    {
        $tz = self::TIMEZONE_PAGO;
        if ($fecha instanceof \Carbon\CarbonInterface) {
            return $fecha->copy()->timezone($tz)->format('Y-m-d\TH:i');
        }

        return self::fechaHoraPago((string) $fecha)->format('Y-m-d\TH:i');
    }

    /**
     * Interpreta fecha/hora de pago en huso argentino.
     * Acepta `Y-m-d`, `Y-m-d\TH:i` (datetime-local) o `Y-m-d H:i:s`.
     * Si viene solo la fecha, completa con la hora actual de Argentina.
     */
    public static function fechaHoraPago(string $fechaPago): Carbon
    {
        $tz = self::TIMEZONE_PAGO;
        $rawOriginal = trim($fechaPago);
        if ($rawOriginal === '') {
            return Carbon::now($tz);
        }

        $raw = trim(str_replace('T', ' ', $rawOriginal));
        $parsed = Carbon::parse($raw, $tz);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawOriginal) === 1) {
            $ahora = Carbon::now($tz);
            $parsed->setTime($ahora->hour, $ahora->minute, $ahora->second);

            return $parsed;
        }

        // datetime-local suele venir sin segundos (Y-m-dTH:i)
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}$/', $rawOriginal) === 1) {
            $parsed->setSecond(0);
        }

        return $parsed;
    }

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
    private static function registrarInterno(CuotaGenerada $registro, array $datos): ?CuotaPago
    {
        $saldo = round(max(0, (float) $datos['saldoAPagar']), 2);
        $interes = round(max(0, (float) $datos['interes']), 2);
        $bonificacion = round(max(0, (float) $datos['bonificacion']), 2);
        $aPagar = round(max(0, (float) $datos['aPagar']), 2);
        $fechaPago = self::fechaHoraPago((string) $datos['fechaPago']);
        $avisoPago = (bool) ($datos['avisoPago'] ?? false);
        $obs = trim((string) ($datos['obs'] ?? ''));

        $pagoCreado = null;

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

        return $pagoCreado;
    }
}
