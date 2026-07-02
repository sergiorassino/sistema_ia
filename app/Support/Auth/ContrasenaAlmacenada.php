<?php

namespace App\Support\Auth;

final class ContrasenaAlmacenada
{
    public static function esHashBcrypt(string $stored): bool
    {
        return str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$');
    }

    /**
     * Devuelve la contraseña en texto si está almacenada en claro (legacy).
     * null si es hash bcrypt (no recuperable).
     */
    public static function textoPlanoRecuperable(?string $stored): ?string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        if (self::esHashBcrypt($stored)) {
            return null;
        }

        return $stored;
    }
}
