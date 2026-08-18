<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Iconos PNG 180/192/512 estáticos (círculo SE, mismo criterio que SILAVET).
 */
class PwaIconController extends Controller
{
    private const FILES = [
        180 => 'apple-touch-icon-se.png',
        192 => 'icon-se-192.png',
        512 => 'icon-se-512.png',
    ];

    public function __invoke(string $size): Response|BinaryFileResponse
    {
        $size = (int) $size;
        $file = self::FILES[$size] ?? null;
        if ($file === null) {
            abort(404);
        }

        $path = public_path('img/'.$file);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
