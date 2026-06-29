<?php

namespace App\Support\Alumnos;

use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;
use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;

/**
 * Consulta de calificaciones — informe EPQ secundario en autogestión familia.
 */
final class PortalFamiliaBoletinEpqSecundario
{
    public static function habilitadoEnMenu(): bool
    {
        return tenantAutogestionBoletinSecEpqHabilitada()
            && studentEsNivelSecundario()
            && CalificacionesSecundarioModulos::implementacionConfigurada(
                CalificacionesSecundarioModulos::BOLETIN,
            ) === CalificacionesEpqSecundarioCatalogo::IMPLEMENTACION;
    }

    public static function tituloMenu(): string
    {
        return 'Consulta de Calificaciones';
    }

    public static function urlPdf(): string
    {
        return se_route_url('alumnos.boletin-sec-epq');
    }
}
