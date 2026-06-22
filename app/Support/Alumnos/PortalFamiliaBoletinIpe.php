<?php

namespace App\Support\Alumnos;

use App\Support\CalificacionesPrimario\BoletinIpePrimarioGenerador;

/**
 * Menú y enlaces del boletín IPE primario en autogestión familia.
 */
final class PortalFamiliaBoletinIpe
{
    public static function habilitadoEnMenu(): bool
    {
        return tenantAutogestionConsultaCalificacionesHabilitada()
            && tenantAutogestionBoletinIpePrimarioHabilitada()
            && studentEsNivelPrimario()
            && BoletinIpePrimarioGenerador::usaSelectorEtapa();
    }

    public static function consultaSecundariaVisible(): bool
    {
        return tenantAutogestionConsultaCalificacionesHabilitada()
            && studentEsNivelSecundario();
    }

    /**
     * @return list<array{etapa: int, titulo: string, url: string}>
     */
    public static function itemsEtapa(): array
    {
        $base = tenantBoletinPrimarioMenuEtiquetaBoletinIpe();

        return [
            [
                'etapa' => 1,
                'titulo' => $base.' 1º Etapa',
                'url' => se_route_url('alumnos.boletin-ipe-primario', ['etapa' => 1]),
            ],
            [
                'etapa' => 2,
                'titulo' => $base.' 2º Etapa',
                'url' => se_route_url('alumnos.boletin-ipe-primario', ['etapa' => 2]),
            ],
        ];
    }
}
