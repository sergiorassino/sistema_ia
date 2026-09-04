{{-- Menú de Secretaría: grupo CALIFICACIONES (Primario) — `niveles.id` = 2. --}}

@if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraCalificacionesPrimario())
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.calificacionesPrimario && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('calificacionesPrimario')"
            title="Calificaciones (primario)">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">CALIFICACIONES (Primario)</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.calificacionesPrimario ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.calificacionesPrimario && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI))
        <a href="{{ route('calificacionesPrimario.sincroGe') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesPrimario.sincroGe',
           ])
           title="{{ seSidebarTooltip('Descargar Calificaciones desde GE (primario)', 9) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span class="truncate">Descargar Calificaciones desde GE</span>
        </a>
        <a href="{{ route('calificacionesPrimario.sincroDesempenos') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesPrimario.sincroDesempenos',
           ])
           title="{{ seSidebarTooltip('Descargar Desempeños desde GE (primario)', 9) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Descargar Desempeños desde GE</span>
        </a>
        @endif
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA) && \App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::moduloActivo(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::CARGA_ESTUDIANTE))
        @php
            $rutaCargaStaff = \App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::rutaStaff(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::CARGA_ESTUDIANTE);
            $prefijosCargaStaff = str_contains($rutaCargaStaff, 'Epq')
                ? ['calificacionesPrimarioEpq.carga', 'calificacionesPrimarioEpq.infoAdicional']
                : ['calificacionesPrimario.carga'];
        @endphp
        <a href="{{ route($rutaCargaStaff) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => collect($prefijosCargaStaff)->contains(
                   fn (string $prefijo): bool => str_starts_with($route ?? '', $prefijo)
               ),
           ])
           title="{{ seSidebarTooltip('Carga de calificaciones por estudiante (primario)', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span class="truncate">Carga de calificaciones por estudiante</span>
        </a>
        @endif
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA) && \App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::moduloActivo(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::CARGA_MATERIA))
        <a href="{{ route('calificacionesPrimario.cargaMateria') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesPrimario.cargaMateria',
           ])
           title="{{ seSidebarTooltip('Carga por espacio curricular (primario)', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <span class="truncate">Carga por Espacio Curricular</span>
        </a>
        @endif
        @if (\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::moduloActivo(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::BOLETIN_PRIM))
        <a href="{{ route(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::rutaStaff(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::BOLETIN_PRIM)) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'calificacionesPrimarioEpq.boletin'),
           ])
           title="{{ seSidebarTooltip(tenantBoletinPrimarioMenuEtiquetaBoletinIpe()) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">{{ tenantBoletinPrimarioMenuEtiquetaBoletinIpe() }}</span>
        </a>
        @else
        <a href="{{ route('calificacionesPrimario.boletinIpe') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'calificacionesPrimario.boletinIpe'),
           ])
           title="{{ seSidebarTooltip(tenantBoletinPrimarioMenuEtiquetaBoletinIpe()) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">{{ tenantBoletinPrimarioMenuEtiquetaBoletinIpe() }}</span>
        </a>
        @endif
        @if (\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::moduloActivo(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::PLANILLA))
        @php
            $rutaPlanillaStaff = \App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::rutaStaff(\App\Support\CalificacionesPrimario\CalificacionesPrimarioModulos::PLANILLA);
            $prefijoPlanillaStaff = str_contains($rutaPlanillaStaff, 'Epq') ? 'calificacionesPrimarioEpq.planilla' : 'calificacionesPrimario.planilla';
        @endphp
        <a href="{{ route($rutaPlanillaStaff) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', $prefijoPlanillaStaff),
           ])
           title="{{ seSidebarTooltip('Planilla de calificaciones (primario)') }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            <span class="truncate">Planilla de calificaciones</span>
        </a>
        @endif
    </div>
@endif
