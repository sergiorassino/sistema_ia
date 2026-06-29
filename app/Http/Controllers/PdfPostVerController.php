<?php

namespace App\Http\Controllers;

use App\Support\Pdf\PdfPostEntrega;
use Illuminate\Http\Response;

class PdfPostVerController extends Controller
{
    public function __invoke(string $token): Response
    {
        $data = PdfPostEntrega::recuperar($token);
        if ($data === null) {
            abort(404, 'El PDF ya no está disponible o expiró.');
        }

        return response($data['binario'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$data['nombre'].'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
