<?php

namespace App\Support\Navegacion;

use App\Support\CalificacionesInicial\CalificacionesInicialModulos;
use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos;

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

            $out[] = self::enriquecerItemRutas($item);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function enriquecerItemRutas(array $item): array
    {
        if ($item['id'] === 'inicial.carga_notas'
            && CalificacionesInicialModulos::moduloActivo(CalificacionesInicialModulos::CARGA_NOTAS)) {
            $item['route'] = CalificacionesInicialModulos::rutaPortal(CalificacionesInicialModulos::CARGA_NOTAS);
            $item['active_routes'] = CalificacionesInicialModulos::rutasActivasPortal(CalificacionesInicialModulos::CARGA_NOTAS);
        }

        if ($item['id'] === 'inicial.boletin'
            && CalificacionesInicialModulos::moduloActivo(CalificacionesInicialModulos::BOLETIN)) {
            $item['route'] = CalificacionesInicialModulos::rutaPortal(CalificacionesInicialModulos::BOLETIN);
            $item['active_routes'] = CalificacionesInicialModulos::rutasActivasPortal(CalificacionesInicialModulos::BOLETIN);
        }

        if ($item['id'] === 'primario.carga_estudiante'
            && CalificacionesPrimarioModulos::moduloActivo(CalificacionesPrimarioModulos::CARGA_ESTUDIANTE)) {
            $item['route'] = CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::CARGA_ESTUDIANTE);
            $item['active_routes'] = array_values(array_unique(array_filter([
                CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::CARGA_ESTUDIANTE),
                CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::CARGA_ESTUDIANTE, 'form'),
                CalificacionesPrimarioModulos::definicionActiva(CalificacionesPrimarioModulos::CARGA_ESTUDIANTE)['ruta_portal_info'] ?? null,
            ])));
        }

        if ($item['id'] === 'primario.boletin_ipe'
            && CalificacionesPrimarioModulos::moduloActivo(CalificacionesPrimarioModulos::BOLETIN_PRIM)) {
            $item['route'] = CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::BOLETIN_PRIM);
            $item['active_routes'] = CalificacionesPrimarioModulos::rutasActivasPortal(CalificacionesPrimarioModulos::BOLETIN_PRIM);
        }

        if ($item['id'] === 'primario.planilla'
            && CalificacionesPrimarioModulos::moduloActivo(CalificacionesPrimarioModulos::PLANILLA)) {
            $item['route'] = CalificacionesPrimarioModulos::rutaPortal(CalificacionesPrimarioModulos::PLANILLA);
            $item['active_routes'] = CalificacionesPrimarioModulos::rutasActivasPortal(CalificacionesPrimarioModulos::PLANILLA);
        }

        if ($item['id'] === 'secundario.calificaciones'
            && CalificacionesSecundarioModulos::moduloActivo(CalificacionesSecundarioModulos::CARGA)) {
            $item['route'] = CalificacionesSecundarioModulos::rutaPortal(CalificacionesSecundarioModulos::CARGA);
            $item['active_routes'] = array_values(array_unique(array_filter([
                CalificacionesSecundarioModulos::rutaPortal(CalificacionesSecundarioModulos::CARGA),
                CalificacionesSecundarioModulos::rutaPortal(CalificacionesSecundarioModulos::CARGA, 'form'),
                CalificacionesSecundarioModulos::definicionActiva(CalificacionesSecundarioModulos::CARGA)['ruta_portal_pdf'] ?? null,
            ])));
        }

        return $item;
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
            if (str_starts_with((string) $item['id'], 'inicial.')) {
                return CalificacionesInicialModulos::moduloActivo((string) $item['modulo']);
            }

            return CalificacionesPrimarioModulos::moduloActivo((string) $item['modulo']);
        }

        if ($item['id'] === 'secundario.solicitud_evaluacion') {
            return tenantSolicitudEvaluacionHabilitada();
        }

        if (str_contains((string) $item['id'], '.libro_de_temas')) {
            return tenantLibroDeTemasHabilitado();
        }

        return true;
    }

    public static function itemActivo(array $item, ?string $routeActual): bool
    {
        return request()->routeIs($item['active_routes']);
    }
}
