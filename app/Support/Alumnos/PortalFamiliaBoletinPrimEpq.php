<?php

namespace App\Support\Alumnos;

use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;

/**
 * Menú y enlaces del Boletín (Prim) EPQ en autogestión familia.
 */
final class PortalFamiliaBoletinPrimEpq
{
    public static function habilitadoEnMenu(): bool
    {
        return tenantAutogestionBoletinPrimEpqHabilitada()
            && studentEsNivelPrimario()
            && CalificacionesPrimarioModulos::implementacionConfigurada(
                CalificacionesPrimarioModulos::BOLETIN_PRIM,
            ) === CalificacionesEpqCatalogo::IMPLEMENTACION;
    }

    /**
     * @return list<array{cara: string, titulo: string, url: string}>
     */
    public static function items(): array
    {
        return [
            [
                'cara' => 'portada',
                'titulo' => 'Boletin de Calificaciones (Portada)',
                'url' => se_route_url('alumnos.boletin-prim-epq', ['cara' => 'portada']),
            ],
            [
                'cara' => 'calificaciones',
                'titulo' => 'Boletín de Calificaciones (Calificaciones)',
                'url' => se_route_url('alumnos.boletin-prim-epq', ['cara' => 'calificaciones']),
            ],
        ];
    }

    public static function caraPdf(string $caraMenu): ?string
    {
        return match ($caraMenu) {
            'portada' => 'anverso',
            'calificaciones' => 'reverso',
            default => null,
        };
    }
}
