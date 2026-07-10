<?php

namespace App\Http\Controllers\Arca;

use App\Support\Arca\GuiaConfiguracionArcaFacturacionTcpdf;
use App\Support\PermisosArca;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class GuiaConfiguracionArcaFacturacionPdfController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(PermisosArca::puedeDescargarGuiasArca(), 403);

        $colegio = schoolNombre();
        $pdf = GuiaConfiguracionArcaFacturacionTcpdf::generar([
            'colegio' => $colegio !== '' ? $colegio : null,
        ]);

        $filename = 'guia-arca-configuracion-facturacion-'.now()->format('Y-m-d').'.pdf';

        return GuiaConfiguracionArcaFacturacionTcpdf::guiaRespuestaHttp($pdf, $filename);
    }
}
