<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Favicon para /favicon.ico e /icono-escuela.png: círculo SE (estilo SILAVET).
 */
class InstitutionalIconController extends Controller
{
    public function __invoke(): Response|BinaryFileResponse
    {
        $path = public_path('img/favicon-32.png');
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
        ]);
    }
}
