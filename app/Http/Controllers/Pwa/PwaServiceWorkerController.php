<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\Response;

/**
 * Sirve /sw.js por Laravel (MIME correcto) cuando el hosting no entrega el archivo estático.
 */
class PwaServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $path = public_path('sw.js');
        if (! is_file($path)) {
            abort(404);
        }

        $scope = PwaIdentity::rootPath('');

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Service-Worker-Allowed' => $scope,
        ]);
    }
}
