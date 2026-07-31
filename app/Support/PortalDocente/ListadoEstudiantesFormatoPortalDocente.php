<?php

namespace App\Support\PortalDocente;

use App\Support\ProfesorMenuPortal;

/**
 * Listados de estudiantes con formato en el Menú de Docentes (layout y rutas portal).
 * Alcance: cursos del nivel de sesión (mismo criterio que secretaría pedagógica).
 */
final class ListadoEstudiantesFormatoPortalDocente
{
    public static function esPortalDocente(): bool
    {
        return PortalDocenteContext::esActivo();
    }

    public static function layout(): string
    {
        return self::esPortalDocente()
            ? 'layouts.docente'
            : ProfesorMenuPortal::layoutStaff();
    }

    /** @param  array<string, mixed>  $params */
    public static function routePdf(array $params = []): string
    {
        $name = self::esPortalDocente()
            ? 'portalDocente.listados.estudiantesFormato.pdf'
            : 'listados.estudiantes-formato.pdf';

        return route($name, $params);
    }
}
