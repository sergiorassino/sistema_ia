<?php

namespace App\Support\Arca;

use App\Support\Viajes\SalidaViajeHtmlSanitizer;

/**
 * Sanitiza el HTML de `ento.obsFactura` (párrafos para el impreso AFIP).
 */
final class ObsFacturaHtmlSanitizer
{
    public static function limpiar(string $html): string
    {
        return SalidaViajeHtmlSanitizer::limpiar($html);
    }

    public static function estaVacio(string $html): bool
    {
        $texto = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $texto === '';
    }
}
