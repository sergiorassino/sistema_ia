<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Support\Pwa\PwaIdentity;
use Illuminate\Http\Response;

class PwaManifestController extends Controller
{
    public function __invoke(): Response
    {
        $base = PwaIdentity::baseUrl();
        $nombre = PwaIdentity::nombre();

        $payload = [
            'id' => PwaIdentity::idPath(),
            'name' => $nombre,
            'short_name' => PwaIdentity::nombreCorto(),
            'description' => 'Gestión escolar: personal, familias y notificaciones.',
            'lang' => 'es',
            'dir' => 'ltr',
            'start_url' => route('pwa.inicio'),
            'scope' => $base,
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'background_color' => '#F4F8F9',
            'theme_color' => '#40848D',
            'orientation' => 'any',
            'prefer_related_applications' => false,
            'categories' => ['education', 'productivity'],
            'icons' => [
                [
                    'src' => route('pwa.icon', ['size' => 192]),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('pwa.icon', ['size' => 512]),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('pwa.icon', ['size' => 192, 'maskable' => 1]),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => route('pwa.icon', ['size' => 512, 'maskable' => 1]),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Personal de la institución',
                    'short_name' => 'Personal',
                    'url' => route('login'),
                ],
                [
                    'name' => 'Familias y estudiantes',
                    'short_name' => 'Familias',
                    'url' => route('alumnos.login'),
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
