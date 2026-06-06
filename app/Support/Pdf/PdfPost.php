<?php

namespace App\Support\Pdf;

/**
 * Datos para abrir un PDF vía POST (sin IDs sensibles en la URL).
 */
final class PdfPost
{
    /**
     * @param  array<string, mixed>  $fields
     * @return array{action: string, fields: array<string, mixed>}
     */
    public static function datos(string $action, array $fields): array
    {
        return [
            'action' => $action,
            'fields' => $fields,
        ];
    }
}
