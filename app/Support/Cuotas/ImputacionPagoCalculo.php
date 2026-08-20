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
    /** @var array<string, array<string, mixed>> */
    private static array $formulaCache = [];

    /**
     * Precarga fórmulas de interés/bonificación en una sola consulta (PDF morosos con miles de filas).
     *
     * @param  iterable<CuotaGenerada>  $registros
     */
    public static function precargarFormulas(iterable $registros): void
    {
        $pares = [];
        foreach ($registros as $registro) {
            if (! $registro instanceof CuotaGenerada) {
                continue;
            }
            $idCuota = (int) $registro->idCuotas;
            $idCurso = (int) $registro->idCursos;
            if ($idCuota < 1) {
                continue;
            }
            $pares[self::claveFormula($idCuota, $idCurso)] = [$idCuota, $idCurso];
        }

        if ($pares === []) {
            return;
        }

        $idsCuotas = array_values(array_unique(array_map(fn (array $p) => $p[0], $pares)));

        $importes = CuotasImporte::query()
            ->whereIn('idCuotas', $idsCuotas)
            ->get([
                'idCuotas', 'idCursos',
                'signo1v', 'valor1v', 'porcan1v',
                'signo2v', 'valor2v', 'porcan2v',
                'signo3v', 'valor3v', 'porcan3v',
                'signo4v', 'valor4v', 'porcan4v',
            ]);

        foreach ($importes as $importe) {
            $clave = self::claveFormula((int) $importe->idCuotas, (int) $importe->idCursos);
            if (isset($pares[$clave])) {
                self::$formulaCache[$clave] = self::formulaDesdeRegistro($importe);
            }
        }

        foreach (array_keys($pares) as $clave) {
            if (! array_key_exists($clave, self::$formulaCache)) {
                self::$formulaCache[$clave] = self::formulaDesdeRegistro(null);
            }
        }
    }

    public static function limpiarCacheFormulas(): void
    {
        self::$formulaCache = [];
    }
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
     *     usaMeses: bool,
     *     mesesMora: int,
     *     porcan: string,
     *     interes: float,
     *     bonificacion: float,
     *     aPagar: float,
     *     tramo: string
     * }
     */
    /**
     * @param  bool  $moraDiariaHastaFechaCalculo  Si true (solo Estado de Deuda Familiar),
     *                                             en tramo 4 cuenta días hasta la fecha de cálculo
     *                                             (legacy imputarPago), no hasta nueVenc/venc3.
     */
    public static function calcular(
        CuotaGenerada $registro,
        float $saldoAPagar,
        CarbonInterface $fechaPago,
        ?float $porcentManual = null,
        bool $moraDiariaHastaFechaCalculo = false,
    ): array {
        $saldoAPagar = max(0, round($saldoAPagar, 2));

        $formula = self::formulaParaRegistro($registro);

        $venc1 = self::carbon($registro->venc1);
        $venc2 = self::carbon($registro->venc2);
        $venc3 = self::carbon($registro->venc3);
        $fecha = $fechaPago->copy()->startOfDay();

        $tramo = '1';
        $signo = $formula['signo1'];
        $valor = $formula['valor1'];
        $porcan = $formula['porcan1'];
        $usaDias = false;
        $usaMeses = false;
        $dias = 0;
        $mesesMora = 0;
        $interesMoraDiario = tenantCuotasInteresMoraEsDiario();

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
            $usaDias = $interesMoraDiario && $signo === '+' && $porcan === '%';
            $usaMeses = $signo === '+' && self::porcanEsMensualAcumulado($porcan);
        } elseif ($venc3 !== null && $fecha->lte($venc3)) {
            $tramo = '3';
            $signo = $formula['signo3'];
            $valor = $formula['valor3'];
            $porcan = $formula['porcan3'];
            $usaDias = $interesMoraDiario && $signo === '+' && $porcan === '%';
            $usaMeses = $signo === '+' && self::porcanEsMensualAcumulado($porcan);
        } else {
            $tramo = '4';
            $signo = $formula['signo4'];
            $valor = $formula['valor4'];
            $porcan = $formula['porcan4'];
            $usaDias = $interesMoraDiario && $signo === '+' && $porcan === '%';
            $usaMeses = $signo === '+' && self::porcanEsMensualAcumulado($porcan);
        }

        $diasMora = 0;
        if ($usaDias) {
            $diasMora = ($tramo === '4' && ! $moraDiariaHastaFechaCalculo)
                ? self::diasMoraTramo4($registro, $venc1, $venc3)
                : self::diasMoraDesdeVenc1($fecha, $venc1);
        }
        if ($usaDias) {
            $dias = $diasMora;
        }
        if ($usaMeses) {
            $mesesMora = self::mesesMoraAcumuladaDesdeVenc1($venc1, $fecha);
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
            $mesesMora,
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
            'usaMeses' => $usaMeses,
            'mesesMora' => $mesesMora,
            'porcan' => $porcan,
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
        int $meses,
    ): array {
        if ($saldo <= 0 || $valor == 0.0) {
            return [0.0, 0.0];
        }

        if ($esRecargo) {
            if ($porcan === '%') {
                // Con mora diaria, $valor ya es el % total (diario × días desde venc. 1).
                $monto = ($saldo * $valor) / 100;
            } elseif ($porcan === 'm') {
                $monto = $valor * max(0, $meses);
            } elseif ($porcan === 'p') {
                $monto = (($saldo * $valor) / 100) * max(0, $meses);
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

    public static function porcanEsMensualAcumulado(string $porcan): bool
    {
        return in_array($porcan, ['m', 'p'], true);
    }

    /**
     * Meses de mora acumulada desde el 1.er vencimiento (legacy ScriptCase imputarPago).
     */
    public static function mesesMoraAcumuladaDesdeVenc1(?CarbonInterface $venc1, CarbonInterface $fechaReferencia): int
    {
        if ($venc1 === null) {
            return 0;
        }

        $fechaInicial = $venc1->copy()->startOfMonth();
        $fecha = $fechaReferencia->copy()->startOfDay();
        $venc1Dia = $venc1->copy()->startOfDay();

        $meses = (int) $fechaInicial->diff($fecha)->format('%m');

        if ($venc1Dia->lt($fecha)) {
            $meses++;
        }

        return max(0, $meses);
    }

    /**
     * @return array<string, mixed>
     */
    private static function formulaParaRegistro(CuotaGenerada $registro): array
    {
        $clave = self::claveFormula((int) $registro->idCuotas, (int) $registro->idCursos);
        if (array_key_exists($clave, self::$formulaCache)) {
            return self::$formulaCache[$clave];
        }

        $formula = self::formulaDesdeRegistro(
            CuotasImporte::query()
                ->where('idCuotas', (int) $registro->idCuotas)
                ->where('idCursos', (int) $registro->idCursos)
                ->first(),
        );
        self::$formulaCache[$clave] = $formula;

        return $formula;
    }

    private static function claveFormula(int $idCuotas, int $idCursos): string
    {
        return $idCuotas.':'.$idCursos;
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
     * Días de mora desde el 1.er venc. hasta la fecha de pago (tramos 2 y 3).
     */
    private static function diasMoraDesdeVenc1(CarbonInterface $fechaPago, ?CarbonInterface $venc1): int
    {
        return self::diasEntre($fechaPago, $venc1);
    }

    /**
     * Tramo 4 (cupón vencido): días desde el 1.er venc. hasta nueVenc o, si no hay, venc3.
     * Misma regla que {@see ComprobantePagoCalculo}.
     */
    private static function diasMoraTramo4(CuotaGenerada $registro, ?CarbonInterface $venc1, ?CarbonInterface $venc3): int
    {
        $nuevoVenc = self::carbon($registro->nueVenc) ?? $venc3;

        return self::diasEntre($nuevoVenc, $venc1);
    }

    /**
     * Días entre dos fechas inclusive del tramo (fechaMayor − fechaMenor).
     */
    private static function diasEntre(?CarbonInterface $fechaMayor, ?CarbonInterface $fechaMenor): int
    {
        if ($fechaMayor === null || $fechaMenor === null || $fechaMayor->lte($fechaMenor)) {
            return 0;
        }

        return max(0, (int) $fechaMenor->diffInDays($fechaMayor));
    }

    private static function carbon(mixed $fecha): ?CarbonInterface
    {
        if ($fecha instanceof CarbonInterface) {
            $parsed = $fecha->copy()->startOfDay();

            return $parsed->year >= 1900 ? $parsed : null;
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00' || str_starts_with($raw, '0000-') || str_starts_with($raw, '-0001')) {
            return null;
        }

        try {
            $parsed = Carbon::parse($raw)->startOfDay();

            return $parsed->year >= 1900 ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
