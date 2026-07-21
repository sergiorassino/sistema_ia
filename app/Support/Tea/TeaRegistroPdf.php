<?php

namespace App\Support\Tea;

use App\Models\ReincoRegistro;
use Illuminate\Http\Response;
use TCPDF;

/**
 * PDF de un registro TEA — plantilla estática por tenant (FPDI) o TCPDF por implementación.
 */
final class TeaRegistroPdf
{
    public static function generar(ReincoRegistro $registro): TCPDF
    {
        return TeaRegistroPdfGenerador::generar($registro);
    }

    public static function respuestaHttp(TCPDF $pdf, string $nombreArchivo): Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $contenido = $pdf->Output('', 'S');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
