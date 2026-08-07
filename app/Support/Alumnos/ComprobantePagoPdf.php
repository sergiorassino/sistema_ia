<?php

namespace App\Support\Alumnos;

use App\Models\CuotaGenerada;
use Illuminate\Http\Response;
use TCPDF;

/**
 * Resuelve cálculo y maquetación TCPDF del cupón de pago según tenant.
 */
final class ComprobantePagoPdf
{
    public static function implementacion(): string
    {
        $impl = trim((string) config('tenant.cuotas.comprobante_pago.implementacion', 'sanfranciscoasis'));

        return $impl !== '' ? $impl : 'sanfranciscoasis';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function calcular(CuotaGenerada $registro, ?array $pdfHeader = null): ?array
    {
        return match (self::implementacion()) {
            'epq' => ComprobantePagoEpqCalculo::paraCuotaGenerada($registro, $pdfHeader),
            default => ComprobantePagoCalculo::paraCuotaGenerada($registro, $pdfHeader),
        };
    }

    public static function codigoPagoElectronico(int $idLegajos, int $idNivel): string
    {
        return match (self::implementacion()) {
            'epq' => ComprobantePagoEpqCalculo::codigoPagoElectronico($idLegajos, $idNivel),
            default => ComprobantePagoCalculo::codigoPagoElectronico($idLegajos, $idNivel),
        };
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function generar(array $datos): TCPDF
    {
        return match (self::implementacion()) {
            'epq' => ComprobantePagoEpqTcpdf::generar($datos),
            default => ComprobantePagoTcpdf::generar($datos),
        };
    }

    public static function respuestaHttp(TCPDF $pdf, string $nombreArchivo): Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $binario = $pdf->Output($nombreArchivo, 'S');

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
