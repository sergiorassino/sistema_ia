<?php

namespace App\Support;

use App\Support\PortalDocente\PortalDocenteContext;

/**
 * Rutas y layout del cuaderno de comunicados para personal (secretaría o portal docente).
 */
final class ComunicacionesRutasGestion
{
    private const PORTAL_PREFIX = 'portalDocente.comunicaciones.';

    private const STAFF_PREFIX = 'comunicaciones.';

    public static function esPortalDocente(): bool
    {
        return PortalDocenteContext::esActivo();
    }

    public static function nombreRuta(string $accion): string
    {
        $base = self::esPortalDocente() ? self::PORTAL_PREFIX : self::STAFF_PREFIX;

        return $base . $accion;
    }

    /**
     * @param  array<string, mixed>|int|string  $parameters
     */
    public static function route(string $accion, array|int|string $parameters = []): string
    {
        return route(self::nombreRuta($accion), $parameters);
    }

    public static function layout(): string
    {
        return self::esPortalDocente() ? 'layouts.docente' : \App\Support\ProfesorMenuPortal::layoutStaff();
    }

    /** Bandeja e hilos: en portal docente todos los profesores; en secretaría, permiso 3. */
    public static function accesoBandejaGestion(): bool
    {
        if (self::esPortalDocente()) {
            return true;
        }

        return tienePermiso(3);
    }

    /** Nuevo comunicado: en portal docente todos los profesores; en secretaría, permisos 3 y 4. */
    public static function accesoNuevoComunicado(): bool
    {
        if (self::esPortalDocente()) {
            return true;
        }

        return tienePermiso(3) && tienePermiso(4);
    }

    /** Grupos propios de destinatarios: mismo acceso que iniciar un comunicado. */
    public static function accesoMisGrupos(): bool
    {
        return self::accesoNuevoComunicado();
    }
}
