<?php

namespace App\Support\Auth;

use App\Models\Profesor;

final class ProfesorPasswordLectura
{
    /**
     * @return array{texto: string, encriptada: bool}
     */
    public static function paraMostrar(Profesor $profesor): array
    {
        $stored = trim((string) $profesor->getRawOriginal('pwrd'));

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
