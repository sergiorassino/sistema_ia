<?php

namespace App\Support\Viajes;

/**
 * Sanitiza HTML del cuerpo de una salida educativa (sin imágenes ni scripts).
 */
final class SalidaViajeHtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><a><span><ul><ol><li><h1><h2><h3><h4><div>';

    public static function limpiar(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        $html = strip_tags($html, self::ALLOWED_TAGS);

        $html = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                if (! preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $hrefMatch)) {
                    return '<a>';
                }

                $href = $hrefMatch[2] !== '' ? $hrefMatch[2] : ($hrefMatch[3] !== '' ? $hrefMatch[3] : $hrefMatch[4]);
                $href = trim($href);
                if ($href === '' || preg_match('/^\s*javascript:/i', $href)) {
                    return '<a>';
                }

                return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" target="_blank" rel="noopener noreferrer">';
            },
            $html
        ) ?? $html;

        return trim($html);
    }

    /**
     * Vista previa legible para listados: sin etiquetas ni entidades HTML (&nbsp;, &aacute;, etc.).
     */
    public static function aTextoPlano(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Espacios no separables y saltos de bloque → espacio simple
        $texto = preg_replace('/\x{00A0}/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }
}
