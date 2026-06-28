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
        self::dibujarAjustado($pdf, $x, $y, $ancho, $alto, $logoFile);
    }

    /**
     * Dibuja el logo dentro de una caja conservando la proporción (evita ovalar emblemas circulares).
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null  x, y, ancho, alto en mm
     */
    public static function medidasAjustadas(
        float $x,
        float $y,
        float $anchoMax,
        float $altoMax,
        ?string $logoFile = null,
    ): ?array {
        $logo = self::resolverArchivo($logoFile);
        if ($logo === null) {
            return null;
        }

        if (function_exists('schoolLogoEsEmblema') && schoolLogoEsEmblema()) {
            $lado = min($anchoMax, $altoMax);
            $anchoMax = $lado;
            $altoMax = $lado;
        }

        $info = @getimagesize($logo);
        if ($info === false || (int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
            return [$x, $y, $anchoMax, $altoMax];
        }

        $imgW = (float) $info[0];
        $imgH = (float) $info[1];
        $escala = min($anchoMax / $imgW, $altoMax / $imgH);
        $ancho = $imgW * $escala;
        $alto = $imgH * $escala;

        return [
            $x + (($anchoMax - $ancho) / 2),
            $y + (($altoMax - $alto) / 2),
            $ancho,
            $alto,
        ];
    }

    public static function dibujarAjustado(
        TCPDF $pdf,
        float $x,
        float $y,
        float $anchoMax,
        float $altoMax,
        ?string $logoFile = null,
    ): void {
        $medidas = self::medidasAjustadas($x, $y, $anchoMax, $altoMax, $logoFile);
        if ($medidas === null) {
            return;
        }

        [$xAjustada, $yAjustada, $ancho, $alto] = $medidas;
        $logo = self::resolverArchivo($logoFile);
        if ($logo === null) {
            return;
        }

        $pdf->Image(
            TcpdfImagenPng::fuenteTcpdf($logo),
            $xAjustada,
            $yAjustada,
            $ancho,
            $alto,
            '',
            '',
            '',
            false,
            300,
        );
    }
}
