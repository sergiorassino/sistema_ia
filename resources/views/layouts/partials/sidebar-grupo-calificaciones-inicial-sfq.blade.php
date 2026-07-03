{{-- Menú de Secretaría: CALIFICACIONES (Inicial SFQ) — variante San Francisco Quimilí. --}}

@if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraCalificacionesInicialSfq())
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.calificacionesInicialSfq && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('calificacionesInicialSfq')"
            title="Calificaciones (inicial SFQ)">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">CALIFICACIONES (Inicial)</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.calificacionesInicialSfq ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.calificacionesInicialSfq && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA))
        <a href="{{ route(\App\Support\CalificacionesInicial\CalificacionesInicialModulos::rutaStaff(\App\Support\CalificacionesInicial\CalificacionesInicialModulos::CARGA_NOTAS)) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'calificacionesInicialSfq.carga'),
           ])
           title="{{ seSidebarTooltip('Carga de calificaciones (inicial SFQ)', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span class="truncate">Carga de calificaciones</span>
        </a>
        @endif
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA)
            && \App\Support\CalificacionesInicial\CalificacionesInicialModulos::moduloActivo(
                \App\Support\CalificacionesInicial\CalificacionesInicialModulos::BOLETIN,
            ))
        <a href="{{ route(\App\Support\CalificacionesInicial\CalificacionesInicialModulos::rutaStaff(\App\Support\CalificacionesInicial\CalificacionesInicialModulos::BOLETIN)) }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'calificacionesInicialSfq.boletin'),
           ])
           title="{{ seSidebarTooltip('Informes pedagógicos (inicial SFQ)', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <span class="truncate">Informes pedagógicos</span>
        </a>
        @endif
    </div>
@endif
