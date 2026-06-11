<?php

namespace App\Support\Viajes;

/**
 * Normaliza HTML del cuerpo del viaje para TCPDF (tamaño uniforme, sin fuentes legacy).
 */
final class SalidaViajeHtmlParaPdf
{
    public static function preparar(string $html): string
    {
        $html = SalidaViajeHtmlSanitizer::limpiar($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<font\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/font>/i', '', $html) ?? $html;
        $html = preg_replace('/\ssize\s*=\s*("|\')?\d+("|\')?/i', '', $html) ?? $html;
        $html = preg_replace('/<h[1-6]\b[^>]*>/i', '<p><strong>', $html) ?? $html;
        $html = preg_replace('/<\/h[1-6]>/i', '</strong></p>', $html) ?? $html;
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html) ?? $html;
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html) ?? $html;

        return '<div style="font-size:9pt;line-height:1.35;font-family:arial;text-align:justify;">'.$html.'</div>';
    }
}
