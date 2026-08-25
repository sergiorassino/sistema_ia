<?php

namespace App\Support\PortalDocente;

use App\Support\ProfesorMenuPortal;

/**
 * Listados de estudiantes por curso (Menú de Secretaría / Administración / Docentes).
 * En portal docente: layout `layouts.docente` y rutas `portalDocente.listados.*`.
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
