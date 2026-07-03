{{-- Menú de Secretaría: grupo CALIFICACIONES (Secundario) — `niveles.id` = 3. Ver docs/08-menus-de-navegacion.md --}}

@if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraCalificacionesSecundario())
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.calificacionesSec && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('calificacionesSec')"
            title="Calificaciones (secundario) v1.0">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">CALIFICACIONES (Secundario)</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.calificacionesSec ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.calificacionesSec && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI))
        <a href="{{ route('calificacionesSecundario.sincroGe') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesSecundario.sincroGe',
           ])
           title="{{ seSidebarTooltip('Descargar calificaciones desde CIDI (secundario) v1.0', 9) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span class="truncate">Descargar calificaciones desde CIDI</span>
        </a>
        @endif
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA))
        @php
            $rutaCargaSecStaff = \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::moduloActivo(
                \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::CARGA
            )
                ? \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::rutaStaff(
                    \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::CARGA
                )
                : 'calificacionesSecundario.carga';
            $prefijosCargaSecStaff = str_contains($rutaCargaSecStaff, 'Epq')
                ? ['calificacionesSecundarioEpq.carga']
                : ['calificacionesSecundario.carga'];
        @endphp
        <a href="{{ route($rutaCargaSecStaff) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => collect($prefijosCargaSecStaff)->contains(
                   fn (string $prefijo): bool => str_starts_with($route ?? '', $prefijo)
               ),
           ])
           title="{{ seSidebarTooltip('Carga de calificaciones (secundario) v1.0', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Carga de calificaciones</span>
        </a>
        @endif
        @if (tenantSecretariaConsultaCalificacionesHabilitada())
        <a href="{{ route('calificacionesSecundario.consulta') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesSecundario.consulta'
                   || ($route ?? '') === 'calificacionesSecundario.consulta.pdf',
           ])
           title="Consulta de calificaciones (secundario) v1.0">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            <span class="truncate">Consulta de calificaciones</span>
        </a>
        @endif
        <a href="{{ route(\App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::moduloActivo(\App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::BOLETIN)
                ? \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::rutaStaff(\App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::BOLETIN)
                : 'boletinesSecundario.index') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'boletinesSecundario.')
                   || str_starts_with($route ?? '', 'calificacionesSecundarioEpq.boletin'),
           ])
           title="{{ seSidebarTooltip(tenantBoletinSecundarioMenuEtiqueta().' v1.0', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">{{ tenantBoletinSecundarioMenuEtiqueta() }}</span>
        </a>
        @php
            $rutaPlanillaSecStaff = \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::moduloActivo(
                \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::PLANILLA
            )
                ? \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::rutaStaff(
                    \App\Support\CalificacionesSecundario\CalificacionesSecundarioModulos::PLANILLA
                )
                : 'calificacionesSecundario.planilla';
            $prefijosPlanillaSecStaff = str_contains($rutaPlanillaSecStaff, 'Epq')
                ? ['calificacionesSecundarioEpq.planilla']
                : ['calificacionesSecundario.planilla'];
        @endphp
        <a href="{{ route($rutaPlanillaSecStaff) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => collect($prefijosPlanillaSecStaff)->contains(
                   fn (string $prefijo): bool => str_starts_with($route ?? '', $prefijo)
               ),
           ])
           title="{{ seSidebarTooltip('Planilla de calificaciones (secundario) v1.0', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Planilla de calificaciones</span>
        </a>
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_PLANILLA_RESUMEN))
        <a href="{{ route('calificacionesSecundario.planillaResumen') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesSecundario.planillaResumen'
                   || ($route ?? '') === 'calificacionesSecundario.planillaResumen.pdf',
           ])
           title="{{ seSidebarTooltip('Planilla resumen de calificaciones (secundario)', 76) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            <span class="truncate">Planilla resumen</span>
        </a>
        @endif
        @if (tienePermiso(10))
        <a href="{{ route('calificacionesSecundario.coloquios') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesSecundario.coloquios',
           ])
           title="{{ seSidebarTooltip('Carga de coloquios Dic / Feb (secundario)', 10) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="truncate">Carga de coloquios</span>
        </a>
        @endif
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_ACTAS_VOLANTES_COLOQUIO))
        <a href="{{ route('calificacionesSecundario.actaVolanteColoquios') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesSecundario.actaVolanteColoquios'
                   || ($route ?? '') === 'calificacionesSecundario.actaVolanteColoquios.pdf',
           ])
           title="{{ seSidebarTooltip('Actas volantes de coloquio (secundario)', 77) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Actas volantes coloquio</span>
        </a>
        @endif
        @if (tienePermiso(15))
        <a href="{{ route('calificacionesSecundario.cierreAnual') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesSecundario.cierreAnual'
                   || ($route ?? '') === 'calificacionesSecundario.cierreAnual.historial',
           ])
           title="{{ seSidebarTooltip('Cierre anual (secundario)', 15) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="truncate">Cierre anual</span>
        </a>
        @endif
        @if (tenantSolicitudEvaluacionHabilitada()
            && tienePermiso(\App\Support\PermisosIaCatalog::SOLICITUDES_EVALUACION_GESTION))
        <a href="{{ route('calificacionesSecundario.gestionSolicitudesEvaluacion.index') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => request()->routeIs(
                   'calificacionesSecundario.gestionSolicitudesEvaluacion.*'
               ),
           ])
           title="{{ seSidebarTooltip('Listado, alta, edición y baja de evaluaciones programadas', 45) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="truncate">Gestión de Solicitudes de Evaluación</span>
        </a>
        @endif
    </div>
@endif
