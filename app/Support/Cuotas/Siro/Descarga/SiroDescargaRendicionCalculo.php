<?php

namespace App\Support\Cuotas\Siro\Descarga;

use App\Models\CuponAPagar;
use App\Models\CuotaGenerada;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Desglosa capital, interés y bonificación de una línea SIRO según el cupón
 * emitido en {@see CuponAPagar} (snapshot al generarse), no según el estado
 * actual de {@see CuotaGenerada}.
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
     *     advertencias: list<string>,
     *     descargable: bool
     * }
     */
    public static function calcular(
        array $linea,
        CuotaGenerada $cuotaGenerada,
        ?CuponAPagar $cupon,
        string $matchTipo = '',
    ): array {
        $pagado = SiroDescargaRendicionLinea::importeDesdeCentavos((int) ($linea['importePagadoCentavos'] ?? 0));
        $fechaPagoRaw = SiroDescargaRendicionLinea::fechaDesdeSiro((string) ($linea['fechaPago'] ?? ''));
        $fechaPago = $fechaPagoRaw !== null
            ? Carbon::parse($fechaPagoRaw)->startOfDay()
            : Carbon::today()->startOfDay();

        if ($cupon === null) {
            if (SiroDescargaRendicionMatchCuotaSinCupon448::esMatchTipo($matchTipo)) {
                return self::calcularProvisorioImporteArchivo($cuotaGenerada, null, $pagado);
            }

            return self::noDescargable(
                $pagado,
                'Sin cupón en cupones_a_pagar: no se descarga el pago hasta resolver la referencia.',
            );
        }

        if ($pagado <= 0) {
            return self::noDescargable($pagado, 'Importe pagado inválido o cero.');
        }

        $capital = round((float) ($cupon->saldo_pagar ?? 0), 2);
        if ($capital <= 0) {
            return self::noDescargable(
                $pagado,
                'El cupón '.$cupon->id_factura.' tiene saldo_pagar inválido; no se descarga el pago.',
            );
        }

        $tramo = self::tramoCuponParaPago($cupon, $fechaPago, $pagado);
        if ($tramo === null) {
            if (SiroDescargaRendicionProvisorios::debeUsarImporteArchivoSiTramoNoCierra($linea)) {
                return self::calcularProvisorioImporteArchivo($cuotaGenerada, $cupon, $pagado);
            }

            return self::noDescargable(
                $pagado,
                'El importe cobrado por SIRO ($'.number_format($pagado, 2, ',', '.')
                .') no coincide con importe1/2/3venc del cupón '
                .(string) $cupon->id_factura
                .' (fecha pago '.$fechaPago->format('d/m/Y').'). No se descarga hasta revisar el cupón.',
            );
        }

        $desglose = self::desgloseDesdeCapitalYPagado($capital, $pagado);
        $advertencias = self::avisosCuotaYaCobrada(
            $cuotaGenerada,
            ' Desglose tomado del cupón '.(string) $cupon->id_factura
            .' (tramo '.$tramo['tramo'].').',
        );

        return [
            'importe' => $desglose['importe'],
            'pagado' => $pagado,
            'interes' => $desglose['interes'],
            'bonificacion' => $desglose['bonificacion'],
            'advertencias' => $advertencias,
            'descargable' => true,
        ];
    }

    /**
     * TEMPORAL — provisorio 2: toma el importe del archivo y desglosa contra el capital.
     *
     * @return array{
     *     importe: float,
     *     pagado: float,
     *     interes: float,
     *     bonificacion: float,
     *     advertencias: list<string>,
     *     descargable: bool,
     *     provisorioImporteArchivo: bool
     * }
     */
    private static function calcularProvisorioImporteArchivo(
        CuotaGenerada $cuotaGenerada,
        ?CuponAPagar $cupon,
        float $pagado,
    ): array {
        if ($pagado <= 0) {
            return self::noDescargable($pagado, 'Importe pagado inválido o cero.');
        }

        $capital = SiroDescargaRendicionMatchCuotaSinCupon448::capitalParaDesglose($cupon, $cuotaGenerada);
        if ($capital <= 0) {
            return self::noDescargable(
                $pagado,
                'No hay saldo de cuota para desglosar el pago (provisorio).',
            );
        }

        $desglose = self::desgloseDesdeCapitalYPagado($capital, $pagado);
        $advertencias = [];
        if ($cupon !== null) {
            $advertencias[] = SiroDescargaRendicionProvisorios::avisoImporteArchivo($pagado, $capital);
        } else {
            $advertencias[] = 'Provisorio 448: desglose tomado del importe del archivo SIRO ($'
                .number_format($pagado, 2, ',', '.')
                .') contra capital $'.number_format($capital, 2, ',', '.').'.';
        }

        $advertencias = array_merge($advertencias, self::avisosCuotaYaCobrada($cuotaGenerada));

        return [
            'importe' => $desglose['importe'],
            'pagado' => $pagado,
            'interes' => $desglose['interes'],
            'bonificacion' => $desglose['bonificacion'],
            'advertencias' => $advertencias,
            'descargable' => true,
            'provisorioImporteArchivo' => true,
        ];
    }

    /**
     * Elige el tramo del cupón por fecha de pago; si el importe no cierra,
     * acepta el vencimiento cuyo importe coincida con lo cobrado (sin pagos parciales).
     *
     * @return array{tramo: string, importe: float}|null
     */
    public static function tramoCuponParaPago(CuponAPagar $cupon, CarbonInterface $fechaPago, float $pagado): ?array
    {
        $pagado = round($pagado, 2);
        $fecha = $fechaPago->copy()->startOfDay();
        $tramos = self::tramosCupon($cupon);

        foreach ($tramos as $tramo) {
            $hasta = $tramo['fecha'];
            if ($hasta !== null && $fecha->lte($hasta) && self::importesIguales($pagado, $tramo['importe'])) {
                return [
                    'tramo' => $tramo['tramo'],
                    'importe' => $tramo['importe'],
                ];
            }
        }

        // Fecha posterior al último vencimiento publicado: usar 3.er importe si coincide.
        $ultimo = $tramos !== [] ? $tramos[array_key_last($tramos)] : null;
        if ($ultimo !== null && self::importesIguales($pagado, $ultimo['importe'])) {
            $ultimaFecha = $ultimo['fecha'];
            if ($ultimaFecha === null || $fecha->gt($ultimaFecha)) {
                return [
                    'tramo' => $ultimo['tramo'],
                    'importe' => $ultimo['importe'],
                ];
            }
        }

        foreach ($tramos as $tramo) {
            if (self::importesIguales($pagado, $tramo['importe'])) {
                return [
                    'tramo' => $tramo['tramo'],
                    'importe' => $tramo['importe'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{importe: float, interes: float, bonificacion: float}
     */
    public static function desgloseDesdeCapitalYPagado(float $capital, float $pagado): array
    {
        $capital = round($capital, 2);
        $pagado = round($pagado, 2);
        $diff = round($pagado - $capital, 2);

        if (abs($diff) <= self::TOLERANCIA) {
            return [
                'importe' => $capital,
                'interes' => 0.0,
                'bonificacion' => 0.0,
            ];
        }

        if ($diff < 0) {
            return [
                'importe' => $capital,
                'interes' => 0.0,
                'bonificacion' => round(-$diff, 2),
            ];
        }

        return [
            'importe' => $capital,
            'interes' => $diff,
            'bonificacion' => 0.0,
        ];
    }

    /**
     * @return list<array{tramo: string, fecha: ?CarbonInterface, importe: float}>
     */
    private static function tramosCupon(CuponAPagar $cupon): array
    {
        $out = [];
        foreach ([1, 2, 3] as $n) {
            $importe = round((float) ($cupon->{'importe'.$n.'venc'} ?? 0), 2);
            if ($importe <= 0) {
                continue;
            }
            $out[] = [
                'tramo' => (string) $n,
                'fecha' => self::fechaCupon($cupon->{'fecha'.$n.'venc'} ?? null),
                'importe' => $importe,
            ];
        }

        return $out;
    }

    private static function fechaCupon(mixed $valor): ?CarbonInterface
    {
        if ($valor instanceof CarbonInterface) {
            return $valor->copy()->startOfDay();
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        try {
            return Carbon::parse($texto)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function avisosCuotaYaCobrada(CuotaGenerada $cuotaGenerada, string $extraSaldada = ''): array
    {
        $faltapa = round((float) ($cuotaGenerada->faltapa ?? 0), 2);
        $pagadoPrevio = round((float) ($cuotaGenerada->pagado ?? 0), 2);
        if ($faltapa <= self::TOLERANCIA) {
            return ['La cuota ya estaba saldada al descargar; posible pago doble.'.$extraSaldada];
        }
        if ($pagadoPrevio > self::TOLERANCIA) {
            return [
                'La cuota ya tenía un pago registrado ($'
                .number_format($pagadoPrevio, 2, ',', '.')
                .'); posible pago doble.',
            ];
        }

        return [];
    }

    private static function importesIguales(float $a, float $b): bool
    {
        return abs(round($a, 2) - round($b, 2)) <= self::TOLERANCIA;
    }

    /**
     * @return array{
     *     importe: float,
     *     pagado: float,
     *     interes: float,
     *     bonificacion: float,
     *     advertencias: list<string>,
     *     descargable: bool
     * }
     */
    private static function noDescargable(float $pagado, string $mensaje): array
    {
        return [
            'importe' => 0.0,
            'pagado' => round($pagado, 2),
            'interes' => 0.0,
            'bonificacion' => 0.0,
            'advertencias' => [$mensaje],
            'descargable' => false,
        ];
    }
}
