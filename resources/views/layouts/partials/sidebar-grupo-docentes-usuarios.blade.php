{{-- Grupo DOCENTES / USUARIOS (Secretaría y Administración; no es el Menú de Docentes). Ver docs/08-menus-de-navegacion.md --}}

@php
    $permLegajosDocente = \App\Support\PermisosIaCatalog::LEGAJOS_DOCENTES;
    $permAsignacionPpc = \App\Support\PermisosIaCatalog::ASIGNACION_PROFESORES_POR_CURSO;
    $permCertServicios = \App\Support\PermisosIaCatalog::CERTIFICACION_SERVICIOS;
@endphp

@if (puedeConsultarLegajosDocentes() || tienePermiso($permAsignacionPpc) || tienePermiso(23) || tienePermiso($permCertServicios))
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.docentes && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('docentes')"
            title="{{ seSidebarTooltip('Docentes / Usuarios v1.0', [11, 48, 23, $permCertServicios]) }}">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 14l9-5-9-5-9 5 9 5z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 14l9-5v8a2 2 0 01-9 5v0a2 2 0 01-9-5V9l9 5"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">DOCENTES / USUARIOS</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.docentes ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.docentes && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if (puedeConsultarLegajosDocentes())
        <a href="{{ route('abm.legajos-profesor') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.legajos-profesor'),
           ])
           title="{{ seSidebarTooltip('Legajos del docente v1.0 (consulta abierta; alta/edición/baja: permiso 11)', $permLegajosDocente) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span class="truncate">Legajos del docente</span>
        </a>

        @php
            if (! \Illuminate\Support\Facades\Route::has('listados.docentes')) {
                throw new \RuntimeException("Sidebar: falta la ruta 'listados.docentes'.");
            }
        @endphp
        <a href="{{ route('listados.docentes') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => request()->routeIs('listados.docentes', 'listados.docentes.pdf', 'listados.docentes.excel'),
           ])
           title="{{ seSidebarTooltip('Listado de docentes v1.0 (consulta; editar legajos: permiso 11)', null) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Listado de docentes</span>
        </a>
        @endif

        @if (tienePermiso($permAsignacionPpc))
        <a href="{{ route('abm.profesores-por-materia') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.profesores-por-materia'),
           ])
           title="{{ seSidebarTooltip('Asignación de profesores por materia y curso · ppc · v1.0', 48) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 14l9-5-9-5-9 5 9 5z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 14l9-5v8a2 2 0 01-9 5v0a2 2 0 01-9-5V9l9 5"/>
            </svg>
            <span class="truncate">Asignación de Profesores por Materia y Curso</span>
        </a>

        <a href="{{ route('abm.cursos-por-profesor') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.cursos-por-profesor'),
           ])
           title="{{ seSidebarTooltip('Cursos por profesor · ppc + horarios26 · consulta', 48) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="truncate">Cursos por profesor</span>
        </a>
        @endif

        @if (tienePermiso(23))
            <a href="{{ route('docentes.inasistencias') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'docentes.inasistencias'),
               ])
               title="{{ seSidebarTooltip('Inasistencias docentes', 23) }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="truncate">Inasistencias docentes</span>
            </a>
        @endif
        @if (tienePermiso($permCertServicios))
            @php
                if (! \Illuminate\Support\Facades\Route::has('docentes.certificacion-servicios')) {
                    throw new \RuntimeException("Sidebar: falta la ruta 'docentes.certificacion-servicios'.");
                }
            @endphp
            <a href="{{ route('docentes.certificacion-servicios') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'docentes.certificacion-servicios'),
               ])
               title="{{ seSidebarTooltip('Certificación de servicios', $permCertServicios) }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="truncate">Certificación de servicios</span>
            </a>
        @endif
    </div>
@endif
