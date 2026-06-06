<?php

namespace App\Http\Controllers;

use App\Support\ManualSistema\ManualComunicacionInstitucionalTcpdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class ManualComunicacionInstitucionalPdfController extends Controller
{
    public function __invoke(): Response
    {
        $colegio = schoolNombre();
        $baseUrl = url('/');

        $pdf = ManualComunicacionInstitucionalTcpdf::generar([
            'colegio' => $colegio !== '' ? $colegio : null,
            'base_url' => is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : null,
        ]);

        $filename = 'manual-comunicacion-institucional-'.now()->format('Y-m-d').'.pdf';

        return ManualComunicacionInstitucionalTcpdf::respuestaHttp($pdf, $filename);
    }
}

