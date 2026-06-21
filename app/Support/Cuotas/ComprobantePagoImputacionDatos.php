<?php

namespace App\Support\Cuotas;

use App\Models\CuotaPago;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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
        return self::paraPagos(collect([$pago]), $idLegajo);
    }

    /**
     * @param  Collection<int, CuotaPago>|list<CuotaPago>  $pagos
     * @return array<string, mixed>|null
     */
    public static function paraPagos(Collection|array $pagos, int $idLegajo): ?array
    {
        $coleccion = $pagos instanceof Collection ? $pagos : collect($pagos);
        if ($coleccion->isEmpty()) {
            return null;
        }

        $encabezado = GestionAranceles::encabezadoEstudiante($idLegajo);
        if ($encabezado === null) {
            return null;
        }

        $lineas = [];
        $importeTotal = 0.0;
        $bonificacionTotal = 0.0;
        $interesTotal = 0.0;
        $abonadoTotal = 0.0;
        $fechaPagoEsp = '—';
        $medioPago = '—';
        $primerNroComp = null;

        foreach ($coleccion as $pago) {
            if (! $pago instanceof CuotaPago) {
                continue;
            }

            $idCuotaGenerada = (int) ($pago->idCuotasGeneradas ?? 0);
            $registro = $idCuotaGenerada > 0
                ? GestionAranceles::cuotaDelLegajo($idCuotaGenerada, $idLegajo)
                : null;

            if ($registro === null) {
                return null;
            }

            $importe = round((float) ($pago->importe ?? 0), 2);
            $bonificacion = round((float) ($pago->bonificacion ?? 0), 2);
            $interes = round((float) ($pago->interes ?? 0), 2);
            $abonado = round(max(0, $importe + $interes - $bonificacion), 2);

            $importeTotal += $importe;
            $bonificacionTotal += $bonificacion;
            $interesTotal += $interes;
            $abonadoTotal += $abonado;

            if ($medioPago === '—') {
                $medio = trim((string) ($pago->tipoPago?->tipoPago ?? ''));
                if ($medio !== '') {
                    $medioPago = $medio;
                }
            }

            $fechaLinea = self::formatearFechaPago($pago->fechhora);
            if ($fechaPagoEsp === '—' && $fechaLinea !== '—') {
                $fechaPagoEsp = $fechaLinea;
            }

            if ($primerNroComp === null) {
                $primerNroComp = self::textoNumeroComprobante($registro->nroComp, (int) $pago->id);
            }

            $lineas[] = [
                'cuotaNombre' => mb_strtoupper(trim((string) ($registro->cuota?->nombre ?? ''))),
                'cursec' => mb_strtoupper(trim((string) ($registro->curso?->nombreParaListado() ?? ''))),
                'importeOriginalFmt' => CuotasFormato::formatearImporte((float) ($registro->importe ?? 0)),
                'importeFmt' => CuotasFormato::formatearImporte($importe),
                'bonificacionFmt' => CuotasFormato::formatearImporte($bonificacion),
                'interesFmt' => CuotasFormato::formatearImporte($interes),
                'abonadoFmt' => CuotasFormato::formatearImporte($abonado),
            ];
        }

        if ($lineas === []) {
            return null;
        }

        $primera = $lineas[0];
        $esMultiple = count($lineas) > 1;

        return [
            'pdfHeader' => schoolPdfHeaderData(),
            'fechaImpresion' => now()->format('d/m/Y H:i'),
            'nroComprobanteTexto' => $primerNroComp ?? self::textoNumeroComprobante(0, (int) $coleccion->first()->id),
            'apellidoNombre' => mb_strtoupper(trim(($encabezado['apellido'] ?? '').' '.($encabezado['nombre'] ?? ''))),
            'cursec' => $primera['cursec'],
            'nivel' => mb_strtoupper(trim((string) (GestionAranceles::cuotaDelLegajo(
                (int) ($coleccion->first()->idCuotasGeneradas ?? 0),
                $idLegajo,
            )?->curso?->nivel?->nivel ?? ''))),
            'cuotaNombre' => $esMultiple ? 'VARIAS CUOTAS' : $primera['cuotaNombre'],
            'importeOriginalFmt' => $esMultiple ? '' : $primera['importeOriginalFmt'],
            'medioPago' => $medioPago,
            'importeFmt' => CuotasFormato::formatearImporte($importeTotal),
            'bonificacionFmt' => CuotasFormato::formatearImporte($bonificacionTotal),
            'interesFmt' => CuotasFormato::formatearImporte($interesTotal),
            'abonadoFmt' => CuotasFormato::formatearImporte($abonadoTotal),
            'fechaPagoEsp' => $fechaPagoEsp,
            'lineas' => $lineas,
            'esMultiple' => $esMultiple,
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
