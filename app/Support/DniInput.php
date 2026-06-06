<?php

namespace App\Support;

/**
 * Normalización de DNI para campos de login y formularios (solo dígitos).
 */
final class DniInput
{
    public const MAX_LENGTH = 11;

    /**
     * Deja solo dígitos (quita puntos, espacios, guiones, etc.) y limita la longitud.
     */
    public static function digitsOnly(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        if (strlen($digits) > self::MAX_LENGTH) {
            return substr($digits, 0, self::MAX_LENGTH);
        }

        return $digits;
    }
}
