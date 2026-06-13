<?php

namespace App\Support\PortalDocente;

use App\Support\ProfesorMenuPortal;

/**
 * Listado de estudiantes por curso en el Menú de Docentes (layout y rutas portal).
 * Alcance: todos los cursos del nivel de sesión (mismo criterio que secretaría pedagógica).
 */
final class ListadoPorCursoPortalDocente
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
            ? 'portalDocente.listados.porCurso.pdf'
            : 'listados.por-curso.pdf';

        return route($name, $params);
    }

    /** @param  array<string, mixed>  $params */
    public static function routeExcel(array $params = []): string
    {
        $name = self::esPortalDocente()
            ? 'portalDocente.listados.exportarExcel'
            : 'listados.exportar-excel';

        return route($name, $params);
    }
}
