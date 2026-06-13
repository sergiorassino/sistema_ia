<?php

namespace App\Support\Navegacion;

use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;

/**
 * Ítems visibles del Menú de Docentes según nivel de sesión, config del tenant e implementación de módulos.
 */
final class PortalDocenteMenu
{
    /**
     * @return list<array{
     *     id: string,
     *     nivel: int,
     *     label: string,
     *     title: string,
     *     icon: string,
     *     route: string,
     *     active_routes: list<string>,
     *     modulo?: string,
     *     menu_config?: string
     * }>
     */
    public static function itemsParaSesionActual(): array
    {
        $idNivel = (int) (schoolCtx()->idNivel ?? 0);
        if ($idNivel < 1) {
            return [];
        }

        $out = [];
        foreach (PortalDocenteMenuCatalog::items() as $item) {
            if ((int) $item['nivel'] !== $idNivel) {
                continue;
            }

            if (! self::itemHabilitado($item)) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array{
     *     id: string,
     *     nivel: int,
     *     label: string,
     *     title: string,
     *     icon: string,
     *     route: string,
     *     active_routes: list<string>,
     *     modulo?: string,
     *     menu_config?: string
     * }  $item
     */
    public static function itemHabilitado(array $item): bool
    {
        $menuFlag = $item['menu_config'] ?? null;
        if ($menuFlag === null || ! (bool) config($menuFlag, false)) {
            return false;
        }

        if (isset($item['modulo'])) {
            return CalificacionesPrimarioModulos::moduloActivo((string) $item['modulo']);
        }

        if ($item['id'] === 'secundario.solicitud_evaluacion') {
            return tenantSolicitudEvaluacionHabilitada();
        }

        return true;
    }

    public static function itemActivo(array $item, ?string $routeActual): bool
    {
        return request()->routeIs($item['active_routes']);
    }
}
