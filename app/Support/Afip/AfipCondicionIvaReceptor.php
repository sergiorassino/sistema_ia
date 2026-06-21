<?php

namespace App\Support\Afip;

/**
 * Mapeo de etiquetas de condición frente al IVA al código AFIP del receptor.
 */
final class AfipCondicionIvaReceptor
{
    public static function idDesdeEtiqueta(?string $etiqueta, int $default = 5): int
    {
        $n = mb_strtolower(trim((string) $etiqueta));
        if ($n === '') {
            return $default;
        }

        return match (true) {
            str_contains($n, 'inscript') => 1,
            str_contains($n, 'exento') => 4,
            str_contains($n, 'consumidor') => 5,
            str_contains($n, 'monotrib') => 6,
            default => $default,
        };
    }

    public static function etiquetaDesdeId(int $id): string
    {
        return match ($id) {
            4 => 'IVA SUJETO EXENTO',
            5 => 'IVA CONSUMIDOR FINAL',
            6 => 'IVA RESPONSABLE MONOTRIBUTO',
            default => 'SIN DATOS',
        };
    }
}
