<?php

namespace App\Support\Seguimiento;

use App\Support\Viajes\SalidaViajeHtmlSanitizer;

/**
 * Sanitiza el HTML de `sanciones.acta` (editor enriquecido).
 */
final class SancionActaHtmlSanitizer
{
    public static function limpiar(string $html): string
    {
        return SalidaViajeHtmlSanitizer::limpiar($html);
    }

    public static function estaVacio(?string $html): bool
    {
        $texto = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $texto = preg_replace('/\x{00A0}/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto) === '';
    }

    /**
     * Texto plano con saltos de línea para el comunicado / correo a la familia.
     */
    public static function aTextoPlanoMultilinea(?string $html): string
    {
        $html = self::limpiar((string) $html);
        if (self::estaVacio($html)) {
            return '';
        }

        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/li>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/div>/i', "\n", $html) ?? $html;

        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/\x{00A0}/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace("/\n{3,}/u", "\n\n", $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * HTML seguro para DomPDF (sanitizado; sin envoltorio TCPDF).
     */
    public static function paraPdf(?string $html): string
    {
        $html = self::limpiar((string) $html);
        if (self::estaVacio($html)) {
            return '';
        }

        $html = preg_replace('/<font\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/font>/i', '', $html) ?? $html;
        $html = preg_replace('/\ssize\s*=\s*("|\')?\d+("|\')?/i', '', $html) ?? $html;
        $html = preg_replace('/\sclass\s*=\s*("|\')[^"\']*("|\')/i', '', $html) ?? $html;

        return trim($html);
    }
}
