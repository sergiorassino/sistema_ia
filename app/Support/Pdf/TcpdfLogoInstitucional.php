<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\Storage;
use TCPDF;

/**
 * Logo institucional en PDFs TCPDF (ruta absoluta en disco).
 */
final class TcpdfLogoInstitucional
{
    public static function resolverArchivo(?string $logoFile = null): ?string
    {
        if (is_string($logoFile) && $logoFile !== '' && is_file($logoFile)) {
            return $logoFile;
        }

        $path = entoInstitutionalLogoStoragePath();
        if (is_string($path) && $path !== '') {
            $abs = Storage::disk('public')->path($path);
            if (is_file($abs)) {
                return $abs;
            }
        }

        $fallback = public_path('img/3.png');

        return is_file($fallback) ? $fallback : null;
    }

    public static function dibujar(
        TCPDF $pdf,
        float $x,
        float $y,
        float $ancho,
        float $alto,
        ?string $logoFile = null,
    ): void {
        $logo = self::resolverArchivo($logoFile);
        if ($logo === null) {
            return;
        }

        $pdf->Image($logo, $x, $y, $ancho, $alto, '', '', '', false, 300);
    }
}
