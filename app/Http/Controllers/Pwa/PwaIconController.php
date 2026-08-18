<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Iconos PNG 180/192/512 a partir de `public/img/1.png` (monograma SE, 500×500).
 */
class PwaIconController extends Controller
{
    private const ALLOWED = [180, 192, 512];

    public function __invoke(Request $request, string $size): Response|BinaryFileResponse
    {
        $size = (int) $size;
        if (! in_array($size, self::ALLOWED, true)) {
            abort(404);
        }

        $source = public_path('img/1.png');
        if (! is_file($source)) {
            abort(404);
        }

        $maskable = $request->boolean('maskable');
        $png = $this->renderPng($source, $size, $maskable);
        if ($png === null) {
            return response()->file($source, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400, must-revalidate',
            ]);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
        ]);
    }

    private function renderPng(string $source, int $size, bool $maskable): ?string
    {
        if (! function_exists('imagecreatefrompng') || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $src = @imagecreatefrompng($source);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $out = imagecreatetruecolor($size, $size);
        if ($out === false) {
            imagedestroy($src);

            return null;
        }

        imagealphablending($out, false);
        imagesavealpha($out, true);

        if ($maskable) {
            $bg = imagecolorallocate($out, 0x40, 0x84, 0x8D);
            imagefilledrectangle($out, 0, 0, $size, $size, $bg);
            imagealphablending($out, true);
            $pad = (int) round($size * 0.22);
        } else {
            $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
            imagefilledrectangle($out, 0, 0, $size, $size, $transparent);
            imagealphablending($out, true);
            $pad = (int) round($size * 0.06);
        }

        $inner = max(1, $size - (2 * $pad));
        imagecopyresampled($out, $src, $pad, $pad, 0, 0, $inner, $inner, $srcW, $srcH);
        imagedestroy($src);

        ob_start();
        imagesavealpha($out, true);
        imagepng($out, null, 6);
        imagedestroy($out);
        $png = ob_get_clean();

        return is_string($png) && $png !== '' ? $png : null;
    }
}
