<?php

namespace App\Support\Navegacion;

use App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos;
use App\Support\NivelSistema;

/**
 * Catálogo de ítems opcionales del Menú de Docentes (por nivel pedagógico).
 *
 * @see docs/08-menus-de-navegacion.md
 */
final class PortalDocenteMenuCatalog
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
    public static function items(): array
    {
        return [
            [
                'id' => 'inicial.listado_estudiantes',
                'nivel' => NivelSistema::INICIAL,
                'menu_config' => 'tenant.portal_docente.menu.inicial.listado_estudiantes',
                'route' => 'portalDocente.listados.porCurso',
                'active_routes' => [
                    'portalDocente.listados.porCurso',
                    'portalDocente.listados.porCurso.pdf',
                    'portalDocente.listados.exportarExcel',
                ],
                'label' => 'Listados por curso',
                'title' => 'Listados de estudiantes por curso',
                'icon' => 'document',
            ],
            [
                'id' => 'primario.carga_estudiante',
                'nivel' => NivelSistema::PRIMARIO,
                'modulo' => CalificacionesPrimarioModulos::CARGA_ESTUDIANTE,
                'menu_config' => 'tenant.portal_docente.menu.primario.carga_estudiante',
                'route' => 'portalDocente.calificacionesPrimario.carga',
                'active_routes' => [
                    'portalDocente.calificacionesPrimario.carga',
                    'portalDocente.calificacionesPrimario.carga.alumno',
                ],
                'label' => 'Carga por estudiante',
                'title' => 'Carga de calificaciones por estudiante (primario)',
                'icon' => 'edit',
            ],
            [
                'id' => 'primario.carga_materia',
                'nivel' => NivelSistema::PRIMARIO,
                'modulo' => CalificacionesPrimarioModulos::CARGA_MATERIA,
                'menu_config' => 'tenant.portal_docente.menu.primario.carga_materia',
                'route' => 'portalDocente.calificacionesPrimario.cargaMateria',
                'active_routes' => ['portalDocente.calificacionesPrimario.cargaMateria'],
                'label' => 'Carga por Espacio Curricular',
                'title' => 'Carga por espacio curricular (primario)',
                'icon' => 'rows',
            ],
            [
                'id' => 'primario.planilla',
                'nivel' => NivelSistema::PRIMARIO,
                'modulo' => CalificacionesPrimarioModulos::PLANILLA,
                'menu_config' => 'tenant.portal_docente.menu.primario.planilla',
                'route' => 'portalDocente.calificacionesPrimario.planilla',
                'active_routes' => [
                    'portalDocente.calificacionesPrimario.planilla',
                    'portalDocente.calificacionesPrimario.planilla.pdf',
                ],
                'label' => 'Planilla de calificaciones',
                'title' => 'Planilla de calificaciones (primario)',
                'icon' => 'print',
            ],
            [
                'id' => 'primario.listado_estudiantes',
                'nivel' => NivelSistema::PRIMARIO,
                'menu_config' => 'tenant.portal_docente.menu.primario.listado_estudiantes',
                'route' => 'portalDocente.listados.porCurso',
                'active_routes' => [
                    'portalDocente.listados.porCurso',
                    'portalDocente.listados.porCurso.pdf',
                    'portalDocente.listados.exportarExcel',
                ],
                'label' => 'Listados por curso',
                'title' => 'Listados de estudiantes por curso',
                'icon' => 'document',
            ],
            [
                'id' => 'secundario.calificaciones',
                'nivel' => NivelSistema::SECUNDARIO,
                'menu_config' => 'tenant.portal_docente.menu.secundario.calificaciones',
                'route' => 'portalDocente.calificaciones',
                'active_routes' => [
                    'portalDocente.calificaciones',
                    'portalDocente.calificaciones.carga',
                    'portalDocente.calificaciones.pdf',
                ],
                'label' => 'Calificaciones',
                'title' => 'Carga y consulta de calificaciones',
                'icon' => 'clipboard',
            ],
            [
                'id' => 'secundario.solicitud_evaluacion',
                'nivel' => NivelSistema::SECUNDARIO,
                'menu_config' => 'tenant.portal_docente.menu.secundario.solicitud_evaluacion',
                'route' => 'portalDocente.solicitudEvaluacion',
                'active_routes' => [
                    'portalDocente.solicitudEvaluacion',
                    'portalDocente.solicitudEvaluacion.create',
                ],
                'label' => 'Solicitud de evaluación',
                'title' => 'Registrar próximas evaluaciones del curso (máx. 2 por día)',
                'icon' => 'calendar',
            ],
            [
                'id' => 'secundario.cuaderno_seguimiento_aulico',
                'nivel' => NivelSistema::SECUNDARIO,
                'menu_config' => 'tenant.portal_docente.menu.secundario.cuaderno_seguimiento_aulico',
                'route' => 'portalDocente.cuadernoSeguimiento',
                'active_routes' => [
                    'portalDocente.cuadernoSeguimiento',
                    'portalDocente.cuadernoSeguimiento.registro',
                    'portalDocente.cuadernoSeguimiento.alumno',
                ],
                'label' => 'Cuaderno de Seguimiento Áulico',
                'title' => 'Cuaderno de seguimiento áulico y situación disciplinaria',
                'icon' => 'book',
            ],
            [
                'id' => 'secundario.listado_estudiantes',
                'nivel' => NivelSistema::SECUNDARIO,
                'menu_config' => 'tenant.portal_docente.menu.secundario.listado_estudiantes',
                'route' => 'portalDocente.listados.porCurso',
                'active_routes' => [
                    'portalDocente.listados.porCurso',
                    'portalDocente.listados.porCurso.pdf',
                    'portalDocente.listados.exportarExcel',
                ],
                'label' => 'Listados por curso',
                'title' => 'Listados de estudiantes por curso',
                'icon' => 'document',
            ],
        ];
    }
}
