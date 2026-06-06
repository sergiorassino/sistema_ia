<?php

namespace App\Support\Cuotas;

use App\Models\CuotaPago;
use Carbon\CarbonInterface;

/**
 * Datos para el comprobante de pago post-imputación (sin cupón / código de barras).
 */
final class ComprobantePagoImputacionDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public static function paraPago(CuotaPago $pago, int $idLegajo): ?array
    {
        $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
        $registro = $idCuotaGenerada > 0
            ? GestionAranceles::cuotaParaGestion($idCuotaGenerada, $idLegajo)
            : null;

        if ($registro === null) {
            return null;
        }

        $encabezado = GestionAranceles::encabezadoEstudiante($idLegajo);
        $apellidoNombre = mb_strtoupper(trim(($encabezado['apellido'] ?? '').' '.($encabezado['nombre'] ?? '')));

        $importe = round((float) ($pago->importe ?? 0), 2);
        $bonificacion = round((float) ($pago->bonificacion ?? 0), 2);
        $interes = round((float) ($pago->interes ?? 0), 2);
        $abonado = round(max(0, $importe + $interes - $bonificacion), 2);

        $medioPago = trim((string) ($pago->tipoPago?->tipoPago ?? ''));
        if ($medioPago === '') {
            $medioPago = '—';
        }

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'fechaImpresion' => now()->format('d/m/Y H:i'),
            'nroComprobanteTexto' => self::textoNumeroComprobante($registro->nroComp, (int) $pago->id),
            'apellidoNombre' => $apellidoNombre,
            'cursec' => mb_strtoupper(trim((string) ($registro->curso?->nombreParaListado() ?? ''))),
            'nivel' => mb_strtoupper(trim((string) ($registro->curso?->nivel?->nivel ?? ''))),
            'cuotaNombre' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),
            'importeOriginalFmt' => CuotasFormato::formatearImporte((float) ($registro->importe ?? 0)),
            'medioPago' => $medioPago,
            'importeFmt' => CuotasFormato::formatearImporte($importe),
            'bonificacionFmt' => CuotasFormato::formatearImporte($bonificacion),
            'interesFmt' => CuotasFormato::formatearImporte($interes),
            'abonadoFmt' => CuotasFormato::formatearImporte($abonado),
            'fechaPagoEsp' => self::formatearFechaPago($pago->fechhora),
        ];
    }

    private static function textoNumeroComprobante(mixed $nroComp, int $idPago): string
    {
        $nro = (int) ($nroComp ?? 0);
        if ($nro <= 0) {
            $nro = $idPago;
        }

        return '00001-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
    }

    private static function formatearFechaPago(mixed $fecha): string
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha->format('d/m/Y');
        }

        $raw = trim((string) ($fecha ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }
}
