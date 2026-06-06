<?php

namespace App\Support;

/**
 * Permisos del grupo Menú de Secretaría «MATRÍCULA WEB».
 */
final class PermisosMatriculaWeb
{
    /** Subir y reemplazar PDFs de aceptación por nivel (compromiso, AEC, normas, traslado). */
    public const DOCUMENTOS_ACEPTACION = 44;

    public static function tiene(int $orden = self::DOCUMENTOS_ACEPTACION): bool
    {
        return tienePermiso($orden);
    }

    public static function tieneAlgunAccesoMenu(): bool
    {
        return self::tiene(self::DOCUMENTOS_ACEPTACION);
    }
}
