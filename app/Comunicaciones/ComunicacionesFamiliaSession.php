<?php

namespace App\Comunicaciones;

/**
 * Hilo activo en el portal familias (sin ID en la URL de lectura).
 */
final class ComunicacionesFamiliaSession
{
    public const HILO_KEY = 'com_hilo_familia_id';

    public static function abrir(int $idHilo): void
    {
        session([self::HILO_KEY => $idHilo]);
    }

    public static function idHiloActivo(): int
    {
        return (int) session(self::HILO_KEY, 0);
    }
}
