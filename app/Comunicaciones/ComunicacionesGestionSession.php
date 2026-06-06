<?php

namespace App\Comunicaciones;

use App\Models\ComHilo;

/**
 * Hilo activo en comunicaciones de personal (secretaría o portal docente), sin ID en la URL de lectura.
 */
final class ComunicacionesGestionSession
{
    public const HILO_KEY = 'com_hilo_gestion_id';

    public static function abrir(int $idHilo): void
    {
        session([self::HILO_KEY => $idHilo]);
    }

    public static function idHiloActivo(): int
    {
        return (int) session(self::HILO_KEY, 0);
    }

    public static function puedeVerHilo(int $idHilo): bool
    {
        if (! tienePermiso(3)) {
            return false;
        }

        $ctx = schoolCtx();

        $hilo = ComHilo::query()
            ->where('id', $idHilo)
            ->where('id_nivel', (int) $ctx->idNivel)
            ->where('id_terlec', (int) $ctx->idTerlec)
            ->first();

        if ($hilo === null) {
            return false;
        }

        if (tienePermiso(8)) {
            return true;
        }

        return ComunicacionesRepository::profesorPuedeVerHilo(
            (int) $hilo->id,
            (int) $ctx->idProfesor,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec
        );
    }
}
