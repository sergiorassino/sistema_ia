<?php

namespace App\Support;

/**
 * Permisos del grupo Menú de Secretaría «MATRÍCULA WEB».
 */
final class PermisosMatriculaWeb
{
    /** Subir y reemplazar PDFs de aceptación por nivel (compromiso, AEC, normas, traslado). */
    public const DOCUMENTOS_ACEPTACION = 44;

    /** Parametrizar documentos que sube la familia en actualización de datos. */
    public const DOCUMENTOS_ESTUDIANTE_FAMILIA = 83;

    /** Editar bloqmatr / bloqadmi en matrículas regulares del ciclo activo. */
    public const BLOQUEOS_MATRICULA = 82;

    public static function tiene(int $orden = self::DOCUMENTOS_ACEPTACION): bool
    {
        return tienePermiso($orden);
    }

    public static function tieneAlgunAccesoMenu(): bool
    {
        return self::tiene(self::DOCUMENTOS_ACEPTACION)
            || self::tiene(self::DOCUMENTOS_ESTUDIANTE_FAMILIA)
            || self::tiene(self::BLOQUEOS_MATRICULA);
    }
}
