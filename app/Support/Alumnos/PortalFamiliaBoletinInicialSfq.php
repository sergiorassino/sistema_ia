<?php

namespace App\Support\Alumnos;

use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo;
use App\Support\NivelSistema;

/**
 * Informes pedagógicos inicial SFQ (diagnóstico, etapas y Bellas Artes) en autogestión familia.
 */
final class PortalFamiliaBoletinInicialSfq
{
    public static function habilitadoEnMenu(): bool
    {
        return tenantAutogestionBoletinInicialSfqHabilitada()
            && NivelSistema::esInicial((int) (studentCtx()->idNivel ?? 0))
            && CalificacionesInicialModulos::implementacionConfigurada(
                CalificacionesInicialModulos::BOLETIN,
            ) === CalificacionesInicialSfqCatalogo::IMPLEMENTACION;
    }

    /**
     * @return list<array{tipo: string, titulo: string, url: string}>
     */
    public static function items(): array
    {
        $items = [];
        foreach (CalificacionesInicialSfqCatalogo::TIPOS_INFORME as $tipo) {
            $meta = CalificacionesInicialSfqCatalogo::metaTipoInforme($tipo);
            if ($meta === null) {
                continue;
            }

            $items[] = [
                'tipo' => $tipo,
                'titulo' => (string) $meta['etiqueta'],
                'url' => se_route_url('alumnos.informe-pedagogico-inicial', ['tipo' => $tipo]),
            ];
        }

        return $items;
    }
}
