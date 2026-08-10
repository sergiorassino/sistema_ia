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
    private const TOLERANCIA = 0.02;

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
        $fechaPago = $fechaPagoRaw !== null
            ? Carbon::parse($fechaPagoRaw)->startOfDay()
            : Carbon::today()->startOfDay();

        $capitalMax = self::capitalMaximo($cuotaGenerada, $cupon);

        if ($pagado <= 0) {
            return [
                'importe' => 0.0,
                'pagado' => 0.0,
                'interes' => 0.0,
                'bonificacion' => 0.0,
                'advertencias' => ['Importe pagado inválido o cero.'],
            ];
        }

        if ($capitalMax <= 0) {
            // Pago doble: imputar el total como capital para dejar faltapa negativa.
            $advertencias[] = 'La cuota ya estaba saldada al descargar; posible pago doble.';

            return [
                'importe' => $pagado,
                'pagado' => $pagado,
                'interes' => 0.0,
                'bonificacion' => 0.0,
                'advertencias' => $advertencias,
            ];
        }

        $desglose = self::desgloseDesdePagado($cuotaGenerada, $pagado, $fechaPago, $capitalMax);

        if ($desglose === null) {
            $advertencias[] = 'No se pudo descomponer el importe rendido por SIRO ($'
                .number_format($pagado, 2, ',', '.').') en capital, interés y bonificación.';

            return [
                'importe' => min($pagado, $capitalMax),
                'pagado' => $pagado,
                'interes' => 0.0,
                'bonificacion' => 0.0,
                'advertencias' => $advertencias,
            ];
        }

        return [
            'importe' => $desglose['importe'],
            'pagado' => $pagado,
            'interes' => $desglose['interes'],
            'bonificacion' => $desglose['bonificacion'],
            'advertencias' => $advertencias,
        ];
    }

    private static function capitalMaximo(CuotaGenerada $cuotaGenerada, ?CuponAPagar $cupon): float
    {
        $faltapa = round((float) ($cuotaGenerada->faltapa ?? 0), 2);
        if ($faltapa <= 0) {
            return 0.0;
        }

        if ($cupon === null) {
            return $faltapa;
        }

        $saldoCupon = round((float) $cupon->saldo_pagar, 2);

        return $saldoCupon > 0 ? min($faltapa, $saldoCupon) : $faltapa;
    }

    /**
     * @return array{importe: float, interes: float, bonificacion: float, total: float}|null
     */
    private static function desgloseDesdePagado(
        CuotaGenerada $registro,
        float $pagado,
        Carbon $fechaPago,
        float $capitalMax,
    ): ?array {
        $pagado = round($pagado, 2);
        $capitalMax = round($capitalMax, 2);

        $desgloseCompleto = self::desgloseParaCapital($registro, $capitalMax, $fechaPago);
        if (abs($desgloseCompleto['total'] - $pagado) <= self::TOLERANCIA) {
            return $desgloseCompleto;
        }

        if ($pagado + self::TOLERANCIA >= $capitalMax) {
            return self::desglosePagoCapitalCompleto($registro, $pagado, $fechaPago, $capitalMax);
        }

        $capital = self::capitalDesdePagado($registro, $pagado, $fechaPago, $capitalMax);
        if ($capital === null) {
            return null;
        }

        return self::desgloseParaCapital($registro, $capital, $fechaPago);
    }

    /**
     * Pago que cubre todo el capital: bonificación según fórmula; interés = pagado − capital + bonificación.
     *
     * @return array{importe: float, interes: float, bonificacion: float, total: float}
     */
    private static function desglosePagoCapitalCompleto(
        CuotaGenerada $registro,
        float $pagado,
        Carbon $fechaPago,
        float $capital,
    ): array {
        $capital = round($capital, 2);
        $calc = ImputacionPagoCalculo::calcular($registro, $capital, $fechaPago, null);
        $bonificacion = round((float) $calc['bonificacion'], 2);
        $interes = round(max(0, $pagado - $capital + $bonificacion), 2);

        return [
            'importe' => $capital,
            'interes' => $interes,
            'bonificacion' => $bonificacion,
            'total' => round($capital + $interes - $bonificacion, 2),
        ];
    }

    /**
     * @return array{importe: float, interes: float, bonificacion: float, total: float}
     */
    private static function desgloseParaCapital(
        CuotaGenerada $registro,
        float $capital,
        Carbon $fechaPago,
    ): array {
        $capital = round(max(0, $capital), 2);
        $calc = ImputacionPagoCalculo::calcular($registro, $capital, $fechaPago, null);
        $interes = round((float) $calc['interes'], 2);
        $bonificacion = round((float) $calc['bonificacion'], 2);
        $total = round($capital + $interes - $bonificacion, 2);

        return [
            'importe' => $capital,
            'interes' => $interes,
            'bonificacion' => $bonificacion,
            'total' => $total,
        ];
    }

    private static function capitalDesdePagado(
        CuotaGenerada $registro,
        float $pagado,
        Carbon $fechaPago,
        float $capitalMax,
    ): ?float {
        $pagado = round($pagado, 2);
        $capitalMax = round($capitalMax, 2);

        if ($pagado <= 0 || $capitalMax <= 0) {
            return null;
        }

        $limiteSuperior = min($capitalMax, $pagado);
        $mejorCapital = null;
        $mejorDiff = PHP_FLOAT_MAX;

        $bajo = 0.01;
        $alto = $limiteSuperior;

        for ($i = 0; $i < 64 && $bajo <= $alto; $i++) {
            $medio = round(($bajo + $alto) / 2, 2);
            $desglose = self::desgloseParaCapital($registro, $medio, $fechaPago);
            $diff = round($desglose['total'] - $pagado, 2);

            if (abs($diff) < $mejorDiff) {
                $mejorDiff = abs($diff);
                $mejorCapital = $medio;
            }

            if (abs($diff) <= self::TOLERANCIA) {
                return $medio;
            }

            if ($diff > 0) {
                $alto = round($medio - 0.01, 2);
            } else {
                $bajo = round($medio + 0.01, 2);
            }
        }

        return $mejorDiff <= self::TOLERANCIA ? $mejorCapital : null;
    }
}
