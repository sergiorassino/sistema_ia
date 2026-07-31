<?php

namespace App\Support\PortalDocente;

use App\Support\ProfesorMenuPortal;

/**
 * Listado de estudiantes por curso (Menú de Secretaría / Administración).
 * El Menú de Docentes usa {@see ListadoEstudiantesFormatoPortalDocente}.
 */
final class ListadoPorCursoPortalDocente
{
    public static function layout(): string
    {
        return ProfesorMenuPortal::layoutStaff();
    }

    /** @param  array<string, mixed>  $params */
    public static function routePdf(array $params = []): string
    {
        return route('listados.por-curso.pdf', $params);
    }

    /** @param  array<string, mixed>  $params */
    public static function routeExcel(array $params = []): string
    {
        return route('listados.exportar-excel', $params);
    }
}
