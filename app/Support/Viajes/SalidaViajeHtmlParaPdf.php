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
        $html = preg_replace('/\sclass\s*=\s*("|\')[^"\']*("|\')/i', '', $html) ?? $html;
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html) ?? $html;
        $html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $html) ?? $html;
        $html = self::recortarEspaciosFinales($html);

        if ($html === '') {
            return '';
        }

        return '<div style="font-size:9pt;line-height:1.25;font-family:arial;text-align:justify;">'.$html.'</div>';
    }

    /**
     * Quita líneas / bloques vacíos al final del HTML para no dejar aire
     * entre el cuerpo y el bloque "Lugar y fecha".
     */
    private static function recortarEspaciosFinales(string $html): string
    {
        $vacioInterior = '(?:\s|&nbsp;|&#160;|<br\s*\/?\s*>)*';

        for ($i = 0; $i < 30; $i++) {
            $antes = $html;

            $html = rtrim($html);
            // <br> sueltos al final
            $html = preg_replace('/(?:<br\s*\/?\s*>\s*)+$/i', '', $html) ?? $html;
            // Bloques vacíos al final: <p></p>, <div><br></div>, <span>&nbsp;</span>, etc.
            $html = preg_replace(
                '/(?:<(div|p|span)(?:\s[^>]*)?>'.$vacioInterior.'<\/\1>\s*)+$/i',
                '',
                $html
            ) ?? $html;
            // Bloques vacíos en cualquier parte (p. ej. intercalados por el editor)
            $html = preg_replace(
                '/<(div|p|span)(?:\s[^>]*)?>'.$vacioInterior.'<\/\1>/i',
                '',
                $html
            ) ?? $html;

            $html = rtrim($html);

            if ($html === $antes) {
                break;
            }
        }

        return $html;
    }
}
