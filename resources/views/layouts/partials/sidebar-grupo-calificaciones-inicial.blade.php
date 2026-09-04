{{-- Menú de Secretaría: grupo CALIFICACIONES (Inicial) — `niveles.id` = 1. Ítems futuros (sin secundario). --}}

@php
    $cargaNotasOffSecretaria = \App\Support\EntoCargaNotas::entradaSecretariaBloqueada();
    $mensajeCargaNotasOffSecretaria = \App\Support\EntoCargaNotas::mensajeEntradaSecretariaBloqueada();
@endphp

@if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraCalificacionesInicial())
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.calificacionesInicial && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('calificacionesInicial')"
            title="Calificaciones (inicial)">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">CALIFICACIONES (Inicial)</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.calificacionesInicial ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.calificacionesInicial && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_SINCRO_CIDI))
        <a href="{{ route('calificacionesInicial.sincroGe') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesInicial.sincroGe',
           ])
           title="{{ seSidebarTooltip('Descargar Calificaciones desde GE (inicial)', 9) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span class="truncate">Descargar Calificaciones desde GE</span>
        </a>
        <a href="{{ route('calificacionesInicial.sincroDesempenos') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesInicial.sincroDesempenos',
           ])
           title="{{ seSidebarTooltip('Descargar Desempeños desde GE (inicial)', 9) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Descargar Desempeños desde GE</span>
        </a>
        @endif
        @if (tienePermiso(\App\Support\PermisosIaCatalog::CALIF_CARGA))
        <a href="{{ route('calificacionesInicial.indicadores') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'calificacionesInicial.indicadores'),
           ])
           title="{{ seSidebarTooltip('Editar indicadores (inicial) · Espacios curriculares por período v1.0', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span class="truncate">Editar indicadores</span>
        </a>
        <a href="{{ route('calificacionesInicial.observaciones') }}"
           @if ($cargaNotasOffSecretaria)
               @click.prevent="window.seSwalAviso(@js($mensajeCargaNotasOffSecretaria), 'Carga de calificaciones')"
           @endif
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesInicial.observaciones'
                   || str_starts_with($route ?? '', 'calificacionesInicial.observaciones.'),
           ])
           title="{{ seSidebarTooltip('Carga de observaciones (inicial) · Por estudiante', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Carga de observaciones</span>
        </a>
        <a href="{{ route('calificacionesInicial.observacionesMateria') }}"
           @if ($cargaNotasOffSecretaria)
               @click.prevent="window.seSwalAviso(@js($mensajeCargaNotasOffSecretaria), 'Carga de calificaciones')"
           @endif
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'calificacionesInicial.observacionesMateria',
           ])
           title="{{ seSidebarTooltip('Carga por espacio curricular (inicial)', 71) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            <span class="truncate">Carga por Espacio Curricular</span>
        </a>
        @endif
        <a href="{{ route('calificacionesInicial.informeProgreso') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'calificacionesInicial.informeProgreso'),
           ])
           title="{{ seSidebarTooltip('Informe de progreso escolar (inicial) · PDF por sala y alumno') }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <span class="truncate">Informe de progreso escolar</span>
        </a>
    </div>
@endif
