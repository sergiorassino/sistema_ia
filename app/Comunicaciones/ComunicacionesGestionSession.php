<?php

namespace App\Comunicaciones;

use App\Support\ComunicacionesRutasGestion;

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
        if (! ComunicacionesRutasGestion::accesoBandejaGestion()) {
            return false;
        }

        $ctx = schoolCtx();

        $hilo = ComunicacionesRepository::hiloGestionProfesorEnContexto(
            $idHilo,
            (int) $ctx->idNivel,
            (int) $ctx->idTerlec
        );

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
