<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use App\Support\Cuotas\ImputacionPagoCalculo;
use Carbon\Carbon;

/**
 * Calcula importe (capital), interés y bonificación para una línea de rendición.
 */
final class SiroDescargaRendicionCalculo
{
    /**
     * @param  array<string, mixed>  $linea
     * @return array{
     *     importe: float,
     *     pagado: float,
     *     interes: float,
     *     bonificacion: float,
     *     advertencias: list<string>
     * }
     */
    public static function calcular(
        array $linea,
        CuotaGenerada $cuotaGenerada,
        ?CuponAPagar $cupon,
    ): array {
        $advertencias = [];
        $pagado = SiroDescargaRendicionLinea::importeDesdeCentavos((int) ($linea['importePagadoCentavos'] ?? 0));

        $fechaPagoRaw = SiroDescargaRendicionLinea::fechaDesdeSiro((string) ($linea['fechaPago'] ?? ''));
        $fechaPago = $fechaPagoRaw !== null ? Carbon::parse($fechaPagoRaw)->startOfDay() : Carbon::today();

        $faltapa = round((float) ($cuotaGenerada->faltapa ?? 0), 2);
        $saldo = min($pagado, max($faltapa, 0));

        if ($cupon !== null) {
            $saldoCupon = round((float) $cupon->saldo_pagar, 2);
            if ($saldoCupon > 0 && $saldo > $saldoCupon) {
                $saldo = $saldoCupon;
            }
        }

        if ($saldo <= 0 && $pagado > 0) {
            $saldo = $pagado;
            $advertencias[] = 'La cuota ya estaba saldada al descargar; posible pago doble.';
        }

        $calc = ImputacionPagoCalculo::calcular($cuotaGenerada, $saldo, $fechaPago, null);
        $interes = round((float) $calc['interes'], 2);
        $bonificacion = round((float) $calc['bonificacion'], 2);
        $totalCalc = round($saldo + $interes - $bonificacion, 2);

        if (abs($totalCalc - $pagado) > 0.02) {
            $saldoAjustado = self::estimarSaldoDesdePagado($cuotaGenerada, $pagado, $fechaPago);
            if ($saldoAjustado !== null) {
                $calc = ImputacionPagoCalculo::calcular($cuotaGenerada, $saldoAjustado, $fechaPago, null);
                $saldo = $saldoAjustado;
                $interes = round((float) $calc['interes'], 2);
                $bonificacion = round((float) $calc['bonificacion'], 2);
                $totalCalc = round($saldo + $interes - $bonificacion, 2);
            }
        }

        if (abs($totalCalc - $pagado) > 0.02) {
            $advertencias[] = 'El importe rendido por SIRO ($'.number_format($pagado, 2, ',', '.').') difiere del calculado ($'.number_format($totalCalc, 2, ',', '.').').';
        }

        if ($pagado > $faltapa && $faltapa > 0) {
            $advertencias[] = 'Pago superior al saldo adeudado al momento de la descarga.';
        }

        return [
            'importe' => round($saldo, 2),
            'pagado' => $pagado,
            'interes' => $interes,
            'bonificacion' => $bonificacion,
            'advertencias' => $advertencias,
        ];
    }

    private static function estimarSaldoDesdePagado(
        CuotaGenerada $registro,
        float $pagado,
        Carbon $fechaPago,
    ): ?float {
        $faltapa = round((float) ($registro->faltapa ?? 0), 2);
        $candidatos = array_unique([
            $pagado,
            min($pagado, $faltapa > 0 ? $faltapa : $pagado),
            max(0, $faltapa),
        ]);

        $mejorSaldo = null;
        $mejorDiff = PHP_FLOAT_MAX;

        foreach ($candidatos as $saldo) {
            if ($saldo <= 0) {
                continue;
            }
            $calc = ImputacionPagoCalculo::calcular($registro, (float) $saldo, $fechaPago, null);
            $total = round((float) $saldo + (float) $calc['interes'] - (float) $calc['bonificacion'], 2);
            $diff = abs($total - $pagado);
            if ($diff < $mejorDiff) {
                $mejorDiff = $diff;
                $mejorSaldo = (float) $saldo;
            }
        }

        return $mejorDiff <= 0.02 ? $mejorSaldo : null;
    }
}
