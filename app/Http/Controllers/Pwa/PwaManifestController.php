<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\JsonResponse;

/**
 * Mismo patrón que SILAVET (WebManifestController):
 * URLs absolutas con url()/asset(); start_url = /pwa-…/entrar (no login ni carpeta raíz).
 */
class PwaManifestController extends Controller
{
    public function __invoke(?string $portal = null): JsonResponse
    {
        $portal = PwaIdentity::normalizarPortal($portal);
        $esFamilias = $portal === PwaIdentity::FAMILIAS;
        $startUrl = PwaIdentity::startUrlAbsoluto($portal);
        $scopeUrl = PwaIdentity::scopeAbsoluto($portal);
        $icon192 = PwaIdentity::iconAbsoluto('icon-se-192.png');
        $icon512 = PwaIdentity::iconAbsoluto('icon-se-512.png');

        return response()->json([
            'id' => $scopeUrl,
            'name' => PwaIdentity::nombreApp($portal),
            'short_name' => PwaIdentity::nombreCortoApp($portal),
            'description' => $esFamilias
                ? 'Portal de familias y estudiantes.'
                : 'Portal del personal de la institución.',
            'start_url' => $startUrl,
            'scope' => $scopeUrl,
            'display' => 'standalone',
            'background_color' => '#FFFFFF',
            'theme_color' => '#40848D',
            'icons' => [
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => $icon512,
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
