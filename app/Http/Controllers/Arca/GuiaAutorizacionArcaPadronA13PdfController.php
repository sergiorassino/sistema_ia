<?php

namespace App\Http\Controllers\Arca;

use App\Support\Arca\GuiaAutorizacionArcaPadronA13Tcpdf;
use App\Support\PermisosArca;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class GuiaAutorizacionArcaPadronA13PdfController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(PermisosArca::puedeConsultaCuitPorDni(), 403);

        $colegio = schoolNombre();
        $pdf = GuiaAutorizacionArcaPadronA13Tcpdf::generar([
            'colegio' => $colegio !== '' ? $colegio : null,
        ]);

        $filename = 'guia-arca-autorizar-padron-a13-'.now()->format('Y-m-d').'.pdf';

        return GuiaAutorizacionArcaPadronA13Tcpdf::respuestaHttp($pdf, $filename);
    }
}
