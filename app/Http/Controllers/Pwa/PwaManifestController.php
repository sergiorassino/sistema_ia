<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\Response;

class PwaManifestController extends Controller
{
    public function __invoke(): Response
    {
        $nombre = PwaIdentity::nombre();

        $payload = [
            // Relativo al manifiesto: el navegador resuelve contra el host real (no APP_URL).
            'id' => './',
            'name' => $nombre,
            'short_name' => PwaIdentity::nombreCorto(),
            'description' => 'Gestión escolar: personal, familias y notificaciones.',
            'lang' => 'es',
            'dir' => 'ltr',
            'start_url' => './entrar',
            'scope' => './',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'background_color' => '#F4F8F9',
            'theme_color' => '#40848D',
            'orientation' => 'any',
            'prefer_related_applications' => false,
            'categories' => ['education', 'productivity'],
            'icons' => [
                [
                    'src' => './pwa-icon/192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => './pwa-icon/512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => './pwa-icon/192.png?maskable=1',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => './pwa-icon/512.png?maskable=1',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Personal de la institución',
                    'short_name' => 'Personal',
                    'url' => './loginUsuario',
                ],
                [
                    'name' => 'Familias y estudiantes',
                    'short_name' => 'Familias',
                    'url' => './loginEstudiante',
                ],
            ],
        ];

        return response(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=utf-8',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]
        );
    }
}
