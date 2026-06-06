<?php

namespace App\Support\Aspirantes;

use App\Models\Aspiento;

/**
 * Genera tokens opacos para la URL pública del registro de aspirantes.
 *
 * Características:
 * - 32 caracteres base62 (~190 bits): no enumerable, no adivinable.
 * - Único en la tabla aspiento (loop hasta encontrar uno libre).
 */
final class AspirantesTokenService
{
    public const LONGITUD = 32;

    public function generarUnico(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = $this->generar();
            if (! Aspiento::query()->where('token', $token)->exists()) {
                return $token;
            }
        }

        // Caso ultra improbable: agregamos timestamp para forzar unicidad.
        return $this->generar().dechex(time());
    }

    private function generar(): string
    {
        $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $largo    = strlen($alfabeto);
        $bytes    = random_bytes(self::LONGITUD);
        $out      = '';
        for ($i = 0; $i < self::LONGITUD; $i++) {
            $out .= $alfabeto[ord($bytes[$i]) % $largo];
        }

        return $out;
    }
}
