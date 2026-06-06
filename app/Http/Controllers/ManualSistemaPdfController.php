<?php

namespace App\Http\Controllers;

use App\Support\ManualSistema\ManualSistemaCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ManualSistemaPdfController extends Controller
{
    public function __invoke(): Response
    {
        $colegio = schoolNombre();

        $pdf = Pdf::loadView('pdf.manual-sistema', [
            'meta'    => ManualSistemaCatalog::meta(),
            'intro'   => ManualSistemaCatalog::introduccion(),
            'indice'  => ManualSistemaCatalog::indice(),
            'grupos'  => ManualSistemaCatalog::grupos(),
            'colegio' => $colegio !== '' ? $colegio : null,
        ])->setPaper('a4', 'portrait');

        $filename = 'manual-sistema-'.now()->format('Y-m-d').'.pdf';

        return $pdf->stream($filename);
    }
}
