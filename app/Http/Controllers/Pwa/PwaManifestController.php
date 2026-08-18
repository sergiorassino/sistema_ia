<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\JsonResponse;

/**
 * Mismo patrón que SILAVET (WebManifestController):
 * URLs absolutas con url()/asset(); start_url = login (200, no la carpeta raíz 403/404).
 */
class PwaManifestController extends Controller
{
    public function __invoke(?string $portal = null): JsonResponse
    {
        $portal = PwaIdentity::normalizarPortal($portal);
        $esFamilias = $portal === PwaIdentity::FAMILIAS;
        $startUrl = PwaIdentity::startUrlAbsoluto($portal);

        return response()->json([
            'id' => $startUrl,
            'name' => PwaIdentity::nombreApp($portal),
            'short_name' => PwaIdentity::nombreCortoApp($portal),
            'description' => $esFamilias
                ? 'Portal de familias y estudiantes.'
                : 'Portal del personal de la institución.',
            'start_url' => $startUrl,
            'scope' => url('/'),
            'display' => 'standalone',
            'background_color' => '#FFFFFF',
            'theme_color' => '#40848D',
            'icons' => [
                [
                    'src' => asset('img/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('img/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('img/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => asset('img/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
