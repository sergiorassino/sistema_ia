{{-- Menú de Secretaría: grupo ESTADÍSTICAS. Ver docs/08-menus-de-navegacion.md --}}
@php
    $verRendimiento = \App\Support\Navegacion\MenuSecretariaPerfil::muestraCalificacionesSecundario()
        && tienePermiso(\App\Support\PermisosIaCatalog::ESTADISTICA_RENDIMIENTO_ESCOLAR);
    $verSexo = \App\Support\Navegacion\MenuSecretariaPerfil::muestraEstadisticas()
        && tienePermiso(\App\Support\PermisosIaCatalog::ESTADISTICA_SEXO_CURSO);
    $verEdad = \App\Support\Navegacion\MenuSecretariaPerfil::muestraEstadisticas()
        && tienePermiso(\App\Support\PermisosIaCatalog::ESTADISTICA_POR_EDAD);
    $permisosGrupo = array_values(array_filter([
        $verRendimiento ? \App\Support\PermisosIaCatalog::ESTADISTICA_RENDIMIENTO_ESCOLAR : null,
        $verSexo ? \App\Support\PermisosIaCatalog::ESTADISTICA_SEXO_CURSO : null,
        $verEdad ? \App\Support\PermisosIaCatalog::ESTADISTICA_POR_EDAD : null,
    ]));
@endphp

@if ($verRendimiento || $verSexo || $verEdad)
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.estadisticas && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('estadisticas')"
            title="{{ seSidebarTooltip('Estadísticas v1.0', $permisosGrupo) }}">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">ESTADÍSTICAS</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.estadisticas ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.estadisticas && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if ($verRendimiento)
            <a href="{{ route('estadistica.rendimiento') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'estadistica.rendimiento'),
               ])
               title="{{ seSidebarTooltip('Estadística de rendimiento escolar v1.0', 65) }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="truncate">Estadística de Rendimiento Escolar</span>
            </a>
        @endif
        @if ($verSexo)
            <a href="{{ route('estadistica.sexoCurso') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'estadistica.sexoCurso'),
               ])
               title="{{ seSidebarTooltip('Estadística por sexo y curso v1.0', \App\Support\PermisosIaCatalog::ESTADISTICA_SEXO_CURSO) }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="truncate">Estadística por Sexo y Curso</span>
            </a>
        @endif
        @if ($verEdad)
            <a href="{{ route('estadistica.porEdad') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'estadistica.porEdad'),
               ])
               title="{{ seSidebarTooltip('Estadísticas por edad v1.0', \App\Support\PermisosIaCatalog::ESTADISTICA_POR_EDAD) }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="truncate">Estadísticas por edad</span>
            </a>
        @endif
    </div>
@endif
