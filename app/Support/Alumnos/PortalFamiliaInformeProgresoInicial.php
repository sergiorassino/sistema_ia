<?php

namespace App\Support\Alumnos;

use App\Support\NivelSistema;

/**
 * Informe de progreso escolar (inicial) en autogestión familia.
 */
final class PortalFamiliaInformeProgresoInicial
{
    public static function habilitadoEnMenu(): bool
    {
        return tenantAutogestionConsultaCalificacionesHabilitada()
            && tenantAutogestionInformeProgresoInicialHabilitada()
            && NivelSistema::esInicial((int) (studentCtx()->idNivel ?? 0));
    }

    /**
     * @return list<array{etapa: int, titulo: string, url: string}>
     */
    public static function itemsEtapa(): array
    {
        return [
            [
                'etapa' => 1,
                'titulo' => 'Informe de progreso escolar 1º Etapa',
                'url' => se_route_url('alumnos.informe-progreso-inicial', ['etapa' => 1]),
            ],
            [
                'etapa' => 2,
                'titulo' => 'Informe de progreso escolar 2º Etapa',
                'url' => se_route_url('alumnos.informe-progreso-inicial', ['etapa' => 2]),
            ],
        ];
    }
}
