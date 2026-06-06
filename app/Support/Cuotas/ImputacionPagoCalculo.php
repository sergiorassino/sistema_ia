<?php

namespace App\Support\Cuotas;

use App\Models\CuotaGenerada;
use App\Models\CuotasImporte;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Cálculo de interés/bonificación al imputar un pago (misma lógica que el cupón legacy).
 */
final class ImputacionPagoCalculo
{
    /**
     * @return array{
     *     porcent: float,
     *     porcentDiario: float,
     *     porcentEditable: bool,
     *     esRecargo: bool,
     *     esBonificacion: bool,
     *     usaDias: bool,
     *     dias: int,
     *     diasMora: int,
     *     interes: float,
     *     bonificacion: float,
     *     aPagar: float,
     *     tramo: string
     * }
     */
    public static function calcular(
        CuotaGenerada $registro,
        float $saldoAPagar,
        CarbonInterface $fechaPago,
        ?float $porcentManual = null,
    ): array {
        $saldoAPagar = max(0, round($saldoAPagar, 2));

        $formula = self::formulaDesdeRegistro(
            CuotasImporte::query()
                ->where('idCuotas', (int) $registro->idCuotas)
                ->where('idCursos', (int) $registro->idCursos)
                ->first(),
        );

        $venc1 = self::carbon($registro->venc1);
        $venc2 = self::carbon($registro->venc2);
        $venc3 = self::carbon($registro->venc3);
        $fecha = $fechaPago->copy()->startOfDay();

        $tramo = '1';
        $signo = $formula['signo1'];
        $valor = $formula['valor1'];
        $porcan = $formula['porcan1'];
        $usaDias = false;
        $dias = 0;

        if ($venc1 !== null && $fecha->lte($venc1)) {
            $tramo = '1';
            $signo = $formula['signo1'];
            $valor = $formula['valor1'];
            $porcan = $formula['porcan1'];
        } elseif ($venc2 !== null && $fecha->lte($venc2)) {
            $tramo = '2';
            $signo = $formula['signo2'];
            $valor = $formula['valor2'];
            $porcan = $formula['porcan2'];
            $usaDias = $signo === '+';
        } elseif ($venc3 !== null && $fecha->lte($venc3)) {
            $tramo = '3';
            $signo = $formula['signo3'];
            $valor = $formula['valor3'];
            $porcan = $formula['porcan3'];
            $usaDias = $signo === '+';
        } else {
            $tramo = '4';
            $signo = $formula['signo4'];
            $valor = $formula['valor4'];
            $porcan = $formula['porcan4'];
            $usaDias = $signo === '+' && $porcan === '%';
        }

        $diasMora = $usaDias ? self::diasMoraDesdeVenc1($fecha, $venc1) : 0;
        if ($usaDias) {
            $dias = $diasMora;
        }

        $porcentDiario = (float) $valor;
        $esRecargo = $signo === '+';
        $esBonificacion = ! $esRecargo;

        if ($porcentManual !== null) {
            $porcent = $porcentManual;
        } elseif ($usaDias && $esRecargo && $porcan === '%') {
            $porcent = $porcentDiario * $diasMora;
        } else {
            $porcent = $porcentDiario;
        }

        [$interes, $bonificacion] = self::importesAjuste(
            $saldoAPagar,
            $porcent,
            $porcan,
            $esRecargo,
            $usaDias,
            $diasMora,
        );

        $aPagar = round($saldoAPagar + $interes - $bonificacion, 2);

        return [
            'porcent' => round($porcent, 4),
            'porcentDiario' => round($porcentDiario, 4),
            'porcentEditable' => true,
            'esRecargo' => $esRecargo,
            'esBonificacion' => $esBonificacion,
            'usaDias' => $usaDias,
            'dias' => $dias,
            'diasMora' => $diasMora,
            'interes' => $interes,
            'bonificacion' => $bonificacion,
            'aPagar' => max(0, $aPagar),
            'tramo' => $tramo,
        ];
    }

    /**
     * @return array{0: float, 1: float} interés, bonificación
     */
    private static function importesAjuste(
        float $saldo,
        float $valor,
        string $porcan,
        bool $esRecargo,
        bool $usaDias,
        int $dias,
    ): array {
        if ($saldo <= 0 || $valor == 0.0) {
            return [0.0, 0.0];
        }

        if ($esRecargo) {
            if ($porcan === '%') {
                // Con mora diaria, $valor ya es el % total (diario × días desde venc. 1).
                $monto = ($saldo * $valor) / 100;
            } else {
                $monto = $valor;
                if ($usaDias) {
                    $monto *= max(0, $dias);
                }
            }

            return [round($monto, 2), 0.0];
        }

        $bonif = $porcan === '%'
            ? ($saldo * $valor) / 100
            : $valor;

        return [0.0, round($bonif, 2)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formulaDesdeRegistro(?CuotasImporte $importes): array
    {
        return [
            'signo1' => trim((string) ($importes->signo1v ?? '+')),
            'valor1' => (float) ($importes->valor1v ?? 0),
            'porcan1' => trim((string) ($importes->porcan1v ?? '%')),
            'signo2' => trim((string) ($importes->signo2v ?? '+')),
            'valor2' => (float) ($importes->valor2v ?? 0),
            'porcan2' => trim((string) ($importes->porcan2v ?? '%')),
            'signo3' => trim((string) ($importes->signo3v ?? '+')),
            'valor3' => (float) ($importes->valor3v ?? 0),
            'porcan3' => trim((string) ($importes->porcan3v ?? '%')),
            'signo4' => trim((string) ($importes->signo4v ?? '+')),
            'valor4' => (float) ($importes->valor4v ?? 0),
            'porcan4' => trim((string) ($importes->porcan4v ?? '%')),
        ];
    }

    /**
     * Días de mora desde el primer vencimiento hasta la fecha de pago.
     */
    private static function diasMoraDesdeVenc1(CarbonInterface $fechaPago, ?CarbonInterface $venc1): int
    {
        if ($venc1 === null || $fechaPago->lte($venc1)) {
            return 0;
        }

        return max(0, (int) $venc1->diffInDays($fechaPago));
    }

    private static function carbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha->copy()->startOfDay();
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
