<?php

namespace App\Support\Aulica;

use App\Support\DniInput;

/**
 * DNI usable contra Áulica (7 a 11 dígitos; descarta 0 / guión / vacío).
 */
final class AulicaDni
{
    public static function normalizar(mixed $valor): ?string
    {
        $digits = DniInput::digitsOnly(is_scalar($valor) ? (string) $valor : '');
        if ($digits === '' || $digits === '0') {
            return null;
        }

        $len = strlen($digits);
        if ($len < 7 || $len > 11) {
            return null;
        }

        return $digits;
    }
}
