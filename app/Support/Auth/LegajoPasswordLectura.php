<?php

namespace App\Support\Auth;

use App\Models\Legajo;

final class LegajoPasswordLectura
{
    /**
     * @return array{texto: string, encriptada: bool}
     */
    public static function paraMostrar(Legajo $legajo): array
    {
        $stored = trim((string) $legajo->getRawOriginal('pwrd'));

        if ($stored === '') {
            return [
                'texto' => 'Sin contraseña registrada.',
                'encriptada' => false,
            ];
        }

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return [
                'texto' => 'La contraseña está encriptada y no puede mostrarse en texto claro.',
                'encriptada' => true,
            ];
        }

        return [
            'texto' => $stored,
            'encriptada' => false,
        ];
    }
}
