{{-- Menú de Secretaría — gestión pedagógica (niveles 1–4). Ver docs/08-menus-de-navegacion.md --}}
<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F4F8F9]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? (isset($title) ? $title . ' — ' : '') }}{{ config('app.name') }}</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.sidebar-bosque-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $route = request()->route()?->getName();
    /** En desktop el menú usa rail colapsado salvo dashboard; hover/focus lo expanden. */
    $isSidebarPeekMode = (($route ?? '') !== 'dashboard');
@endphp
<body class="h-full">

{{-- Livewire puede usar el <body> como raíz; el estado del layout va en un wrapper para evitar choques con Alpine. --}}
<div id="se-shell"
     class="h-full"
     x-data="{
    sidebarOpen: false,
    peekMenuMode: @json($isSidebarPeekMode),
    sidebarCollapsed: false,
    _sidebarNavScrollTop: 0,
    _sidebarPeekTimer: null,
    groups: {
        config: {{ (str_starts_with($route ?? '', 'abm.terlec') || str_starts_with($route ?? '', 'abm.niveles') || str_starts_with($route ?? '', 'abm.cursos') || str_starts_with($route ?? '', 'abm.planes') || str_starts_with($route ?? '', 'abm.curplan') || str_starts_with($route ?? '', 'abm.materias-anio') || str_starts_with($route ?? '', 'param.') || ($route ?? '') === 'admin.permisos' || ($route ?? '') === 'admin.permisos-por-usuario') ? 'true' : 'false' }},
        permisosSistema: {{ (($route ?? '') === 'admin.permisos' || ($route ?? '') === 'admin.permisos-por-usuario') ? 'true' : 'false' }},
        planesCursos: {{ (str_starts_with($route ?? '', 'abm.planes') || str_starts_with($route ?? '', 'abm.curplan')) ? 'true' : 'false' }},
        cursosMateriasAno: {{ (str_starts_with($route ?? '', 'abm.cursos') || str_starts_with($route ?? '', 'abm.materias-anio')) ? 'true' : 'false' }},
        students: {{ (str_starts_with($route ?? '', 'abm.legajos') || (str_starts_with($route ?? '', 'listados.') && ! request()->routeIs('listados.estudiantes-datos', 'listados.estudiantes-datos.excel', 'listados.estudiantes-datos.pdf'))) ? 'true' : 'false' }},
        viajesSalidas: {{ request()->routeIs('listados.estudiantes-datos', 'listados.estudiantes-datos.excel', 'listados.estudiantes-datos.pdf', 'viajes.salidas', 'viajes.salidas.create', 'viajes.salidas.edit', 'viajes.salidas.imprimir', 'viajes.salidas.pdf') ? 'true' : 'false' }},
        materialDidactico: {{ request()->routeIs('material-didactico.*') ? 'true' : 'false' }},
        cuadernoComunicados: {{ ((str_starts_with($route ?? '', 'comunicaciones.') || ($route ?? '') === 'param.com-canales' || ($route ?? '') === 'push.suscribir') && (tienePermiso(3) || tienePermiso(43) || tienePermiso(4) || tienePermiso(8) || tienePermiso(5))) ? 'true' : 'false' }},
        calificacionesInicial: {{ str_starts_with($route ?? '', 'calificacionesInicial.') ? 'true' : 'false' }},
        calificacionesPrimario: {{ str_starts_with($route ?? '', 'calificacionesPrimario.') ? 'true' : 'false' }},
        calificacionesSec: {{ (str_starts_with($route ?? '', 'calificacionesSecundario.') || str_starts_with($route ?? '', 'boletinesSecundario.')) ? 'true' : 'false' }},
        estadisticas: {{ str_starts_with($route ?? '', 'estadistica.rendimiento') ? 'true' : 'false' }},
        disciplinario: {{ str_starts_with($route ?? '', 'seguimiento.disciplinario') ? 'true' : 'false' }},
        inasistenciasEstudiantes: {{ str_starts_with($route ?? '', 'seguimiento.inasistencias') || str_starts_with($route ?? '', 'seguimiento.partes-diarios') || ($route ?? '') === 'seguimiento.toma-asistencia-clase' ? 'true' : 'false' }},
        docentes: {{ (str_starts_with($route ?? '', 'abm.profesores-por-materia') || str_starts_with($route ?? '', 'abm.cursos-por-profesor') || str_starts_with($route ?? '', 'abm.legajos-profesor') || str_starts_with($route ?? '', 'docentes.inasistencias') || request()->routeIs('listados.docentes', 'listados.docentes.pdf', 'listados.docentes.excel')) ? 'true' : 'false' }},
        examenes: {{ str_starts_with($route ?? '', 'examenes.') ? 'true' : 'false' }},
        matrizAnaliticos: {{ str_starts_with($route ?? '', 'matrizAnaliticos.') ? 'true' : 'false' }},
        certificados: {{ str_starts_with($route ?? '', 'certificados.') ? 'true' : 'false' }},
        horarios: {{ str_starts_with($route ?? '', 'horarios.') ? 'true' : 'false' }},
        aspirantes: {{ str_starts_with($route ?? '', 'aspirantes.') ? 'true' : 'false' }},
        matriculaWeb: {{ str_starts_with($route ?? '', 'matricula-web.') ? 'true' : 'false' }},
        cooperadora: {{ str_starts_with($route ?? '', 'cooperadora.') ? 'true' : 'false' }},
        comunicaciones: false,
    },
    isDesktopPeekLayout() {
        return window.matchMedia && window.matchMedia('(min-width: 768px)').matches;
    },
    saveSidebarNavScroll() {
        const nav = this.$refs.seSidebarNav;
        if (!nav) return;
        this._sidebarNavScrollTop = nav.scrollTop;
        try {
            sessionStorage.setItem('seSidebarNavScrollTop', String(this._sidebarNavScrollTop));
        } catch (e) {}
    },
    loadSidebarNavScroll() {
        try {
            const raw = sessionStorage.getItem('seSidebarNavScrollTop');
            if (raw === null || raw === '') return;
            const n = parseInt(raw, 10);
            if (!Number.isNaN(n) && n >= 0) this._sidebarNavScrollTop = n;
        } catch (e) {}
    },
    restoreSidebarNavScroll() {
        const nav = this.$refs.seSidebarNav;
        if (!nav) return;
        const top = this._sidebarNavScrollTop;
        let tries = 0;
        const apply = () => {
            nav.scrollTop = top;
            if (Math.abs(nav.scrollTop - top) > 2 && tries++ < 20) {
                requestAnimationFrame(apply);
            }
        };
        this.$nextTick(() => requestAnimationFrame(apply));
    },
    onSidebarNavScroll() {
        if (!this.sidebarCollapsed) this.saveSidebarNavScroll();
    },
    onSidebarNavLinkActivate(ev) {
        const link = ev.target.closest('a[href]');
        if (!link) return;
        const href = (link.getAttribute('href') || '').trim();
        if (!href || href === '#') return;
        this.saveSidebarNavScroll();
        if (ev.type === 'click') this.sidebarOpen = false;
    },
    peekSidebarExpandNow() {
        if (!this.peekMenuMode || !this.isDesktopPeekLayout()) return;
        clearTimeout(this._sidebarPeekTimer);
        const el = this.$refs.seSidebar;
        if (el) el.classList.remove('is-narrowing');
        this.sidebarCollapsed = false;
    },
    peekSidebarMaybeCollapseLater() {
        if (!this.peekMenuMode || !this.isDesktopPeekLayout()) return;
        clearTimeout(this._sidebarPeekTimer);
        const el = this.$refs.seSidebar;
        if (el) el.classList.add('is-narrowing');
        this._sidebarPeekTimer = window.setTimeout(() => {
            if (!el) return;
            if (el.matches(':hover') || el.contains(document.activeElement)) {
                el.classList.remove('is-narrowing');
                return;
            }
            el.classList.remove('is-narrowing');
            this.saveSidebarNavScroll();
            this.sidebarCollapsed = true;
        }, 200);
    },
    peekSidebarFocusOut(ev) {
        if (!this.peekMenuMode || !this.isDesktopPeekLayout()) return;
        const sidebar = this.$refs.seSidebar;
        const rt = ev.relatedTarget;
        if (sidebar && rt && sidebar.contains(rt)) return;
        this.peekSidebarMaybeCollapseLater();
    },
    applyPeekSidebarBootState(respectInteraction = true) {
        if (!this.peekMenuMode || !this.isDesktopPeekLayout()) {
            this.sidebarCollapsed = false;
            return;
        }
        if (respectInteraction) {
            const el = this.$refs.seSidebar;
            if (el && (el.matches(':hover') || el.contains(document.activeElement))) return;
        }
        this.sidebarCollapsed = true;
    },
    init() {
        const raw = localStorage.getItem('sidebarGroups');
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') this.groups = { ...this.groups, ...parsed };
            } catch (e) {}
        }
        this.loadSidebarNavScroll();
        // Desktop dashboard: sidebar ancho siempre; resto de rutas: rail hasta hover/focus.
        this.applyPeekSidebarBootState(false);
        this.$watch('sidebarCollapsed', (collapsed) => {
            if (!collapsed && this.peekMenuMode && this.isDesktopPeekLayout()) {
                this.restoreSidebarNavScroll();
            }
        });
        if (!this._sePeekResizeBound) {
            this._sePeekResizeBound = true;
            window.addEventListener('resize', () => this.applyPeekSidebarBootState(true));
        }
    },
    toggleGroup(key) {
        this.groups[key] = !this.groups[key];
        localStorage.setItem('sidebarGroups', JSON.stringify(this.groups));
    },
}">

{{-- Mobile sidebar backdrop --}}
<div x-show="sidebarOpen"
     x-transition:enter="transition-opacity ease-linear duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 bg-gray-900/50 md:hidden"
     @click="sidebarOpen = false"
     style="display:none"></div>

{{-- Sidebar --}}
<aside x-ref="seSidebar"
       @mouseenter="peekSidebarExpandNow()"
       @mouseleave="saveSidebarNavScroll(); peekSidebarMaybeCollapseLater()"
       @focusin="peekSidebarExpandNow()"
       @focusout="peekSidebarFocusOut($event)"
       class="se-sidebar se-sidebar--bosque se-sidebar--active-typography fixed inset-y-0 left-0 z-[1000] flex flex-col overflow-hidden transform transition-transform duration-200 ease-in-out
              md:translate-x-0 md:transition-[width] md:duration-200 md:ease-in-out md:shadow-lg"
       :class="[
           sidebarOpen ? 'translate-x-0' : '-translate-x-full',
           'md:translate-x-0',
           sidebarCollapsed ? 'is-collapsed' : ''
       ]">

    {{-- Header: logo y contexto; en desktop fuera del dashboard el menú se expande con hover sobre el lateral --}}
    @php
        $sidebarLogoUrl = schoolLogoUrl() ?: asset('img/3.png');
        $sidebarSessionLine = schoolCtx()->nivelNombre()
            . ' · ' . schoolCtx()->terlecAno()
            . ' · ' . trim((Auth::user()->nombre ?? '') . ' ' . (Auth::user()->apellido ?? ''));
    @endphp
    <div class="border-b se-sidebar-sep relative z-10 overflow-hidden flex-shrink-0"
         :class="sidebarCollapsed ? 'flex flex-col items-center gap-2 py-3 px-1' : 'min-h-12 px-2.5 py-2 flex flex-row items-center gap-2'">

        <a href="{{ route('dashboard') }}"
           @click="sidebarOpen = false"
           class="flex min-w-0 items-center gap-2 rounded-lg text-left no-underline text-inherit transition-colors hover:bg-[var(--se-hover-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--se-light-blue)]"
           :class="sidebarCollapsed ? 'flex-col justify-center' : 'flex-1'"
           title="Ir al panel principal v1.0">
            <span class="se-sidebar-brand rounded-lg bg-white px-2 py-1.5 shadow-sm">
                <img src="{{ $sidebarLogoUrl }}" alt=""
                     width="152" height="36"
                     class="object-contain block">
            </span>

            <p class="text-white/90 text-[12px] font-semibold truncate min-w-0 leading-snug"
               x-show="!sidebarCollapsed" x-cloak
               title="{{ $sidebarSessionLine }}">
                <span class="text-white/90">{{ schoolCtx()->nivelNombre() }}</span>
                <span class="text-white/45"> · </span>
                <span class="text-white/90">{{ schoolCtx()->terlecAno() }}</span>
                <span class="block text-[11px] font-medium text-white/65 truncate mt-0.5">
                    {{ Auth::user()->nombre ?? '' }} {{ Auth::user()->apellido ?? '' }}
                </span>
            </p>
        </a>

        <div x-show="!sidebarCollapsed" x-cloak class="min-w-0 flex-shrink-0">
            <livewire:school.context-switcher />
        </div>
    </div>

    {{-- Navigation --}}
    <nav x-ref="seSidebarNav"
         class="flex-1 min-h-0 relative z-[1] px-2.5 py-3 overflow-y-auto space-y-0.5"
         :class="sidebarCollapsed ? '!px-1 !py-2' : ''"
         @scroll.passive="onSidebarNavScroll()"
         @mousedown.capture="onSidebarNavLinkActivate($event)"
         @click.capture="onSidebarNavLinkActivate($event)">

        @if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraGrupoEstudiantes())
        {{-- Estudiantes --}}
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.students && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('students')"
                    title="Estudiantes v1.0">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Estudiantes</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.students ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.students && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                <a href="{{ route('abm.legajos') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.legajos'),
                   ])
                   title="Legajos de Estudiantes v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">Legajos de Estudiantes</span>
                </a>

                @php
                    if (! \Illuminate\Support\Facades\Route::has('listados.por-curso')) {
                        throw new \RuntimeException("Sidebar: falta la ruta 'listados.por-curso'.");
                    }
                @endphp
                <a href="{{ route('listados.por-curso') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('listados.por-curso', 'listados.por-curso.pdf', 'listados.exportar-excel'),
                   ])
                   title="Listados de Estudiantes por Curso v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Listados de Estudiantes por Curso</span>
                </a>

                @php
                    if (! \Illuminate\Support\Facades\Route::has('listados.libro-matricula')) {
                        throw new \RuntimeException("Sidebar: falta la ruta 'listados.libro-matricula'.");
                    }
                @endphp
                <a href="{{ route('listados.libro-matricula') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('listados.libro-matricula', 'listados.libro-matricula.pdf'),
                   ])
                   title="Libro de Matrícula v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <span class="truncate">Libro de Matrícula</span>
                </a>

                @if (tenantSecretariaFichaMatriculaHabilitada())
                    @php
                        if (! \Illuminate\Support\Facades\Route::has('listados.ficha-matricula')) {
                            throw new \RuntimeException("Sidebar: falta la ruta 'listados.ficha-matricula'.");
                        }
                    @endphp
                    <a href="{{ route('listados.ficha-matricula') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => request()->routeIs('listados.ficha-matricula', 'listados.ficha-matricula.pdf'),
                       ])
                       title="{{ tenantSecretariaFichaMatriculaEtiqueta() }} v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">{{ tenantSecretariaFichaMatriculaEtiqueta() }}</span>
                    </a>
                @endif

            </div>
        @endif


        @if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraViajesSalidasEducativas() && tienePermiso(\App\Support\PermisosIaCatalog::VIAJES_SALIDAS_EDUCATIVAS))
        {{-- Viajes / Salidas educativas: Menú de Secretaría, niveles 1–4 (no Administración) --}}
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.viajesSalidas && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('viajesSalidas')"
                    title="Viajes / Salidas educativas v1.0">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">VIAJES / SALIDAS EDUCATIVAS</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.viajesSalidas ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.viajesSalidas && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @php
                    if (! \Illuminate\Support\Facades\Route::has('listados.estudiantes-datos')) {
                        throw new \RuntimeException("Sidebar: falta la ruta 'listados.estudiantes-datos'.");
                    }
                    if (! \Illuminate\Support\Facades\Route::has('viajes.salidas')) {
                        throw new \RuntimeException("Sidebar: falta la ruta 'viajes.salidas'.");
                    }
                @endphp
                <a href="{{ route('viajes.salidas') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('viajes.salidas', 'viajes.salidas.create', 'viajes.salidas.edit', 'viajes.salidas.imprimir', 'viajes.salidas.pdf'),
                   ])
                   title="Gestión de salidas educativas">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span class="truncate">Salidas educativas</span>
                </a>
                <a href="{{ route('listados.estudiantes-datos') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('listados.estudiantes-datos', 'listados.estudiantes-datos.excel', 'listados.estudiantes-datos.pdf'),
                   ])
                   title="Generar Excel Viaje v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Generar Excel Viaje</span>
                </a>
            </div>
        @endif

        {{-- Material Didáctico --}}
        @if(tienePermiso(\App\Support\PermisosIaCatalog::RESERVA_MATERIAL_ADMIN) || tienePermiso(\App\Support\PermisosIaCatalog::RESERVA_MATERIAL_PROFESOR) || tienePermiso(\App\Support\PermisosIaCatalog::RESERVA_MATERIAL_LECTURA))
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.materialDidactico && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('materialDidactico')"
                    title="Material Didáctico">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.753 0-3.332.477-4.5 1.253"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">MATERIAL DIDÁCTICO</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.materialDidactico ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.materialDidactico && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                <a href="{{ route('material-didactico.index') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('material-didactico.index'),
                   ])
                   title="Listado de reservas">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Listado de reservas</span>
                </a>
                @if(tienePermiso(\App\Support\PermisosIaCatalog::RESERVA_MATERIAL_ADMIN) || tienePermiso(\App\Support\PermisosIaCatalog::RESERVA_MATERIAL_PROFESOR))
                    <a href="{{ route('material-didactico.reservar') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => request()->routeIs('material-didactico.reservar', 'material-didactico.reservar.edit'),
                       ])
                       title="Registrar nueva reserva">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 4v16m8-8H4"/>
                        </svg>
                        <span class="truncate">Registrar reserva</span>
                    </a>
                @endif
                @if(tienePermiso(\App\Support\PermisosIaCatalog::RESERVA_MATERIAL_ADMIN))
                    <a href="{{ route('material-didactico.recursos') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => request()->routeIs('material-didactico.recursos'),
                       ])
                       title="Gestión de recursos">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="truncate">Gestión de recursos</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- Comunicación institucional --}}
        @if(tienePermiso(3) || tienePermiso(43) || tienePermiso(4) || tienePermiso(8) || tienePermiso(5))
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.cuadernoComunicados && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('cuadernoComunicados')"
                    title="Comunicación institucional v1.0">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">COMUNICACIÓN INSTITUCIONAL</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.cuadernoComunicados ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.cuadernoComunicados && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if(tienePermiso(3))
                <a href="{{ route('comunicaciones.index') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'comunicaciones.') && ! in_array(($route ?? ''), ['comunicaciones.nuevo', 'comunicaciones.revision', 'comunicaciones.auditoria'], true),
                   ])
                   title="Bandeja de comunicados v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Bandeja de comunicados</span>
                </a>
                @endif

                @if(tienePermiso(4))
                <a href="{{ route('comunicaciones.nuevo') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'comunicaciones.nuevo',
                   ])
                   title="Nuevo comunicado a familias v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="truncate">Nuevo comunicado</span>
                </a>
                @endif

                @if(tienePermiso(8))
                <a href="{{ route('comunicaciones.revision') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'comunicaciones.revision',
                   ])
                   title="Control Cuaderno de Comunicados v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="truncate">Control Cuaderno de Comunicados</span>
                </a>
                @endif

                @if(tienePermiso(43))
                <a href="{{ route('comunicaciones.auditoria') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'comunicaciones.auditoria',
                   ])
                   title="Auditoría de borrados y marcas de lectura en bandejas">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span class="truncate">Auditoría Comunicación</span>
                </a>
                @endif

                @if(tienePermiso(5))
                <a href="{{ route('param.com-canales') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'param.com-canales',
                   ])
                   title="Configuración de canales v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">Configuración de Canales</span>
                </a>
                @endif

                @if (tienePermisoConfig(32))
                <a href="{{ route('push.suscribir') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'push.suscribir',
                   ])
                   title="Notificaciones push en este dispositivo">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="truncate">Notificaciones Push</span>
                </a>
                @endif
            </div>
        @endif

        @unless (\App\Support\Navegacion\MenuSecretariaPerfil::ocultarGruposPedagogicos())
        @include('layouts.partials.sidebar-grupo-calificaciones-inicial')
        @include('layouts.partials.sidebar-grupo-calificaciones-primario')
        @include('layouts.partials.sidebar-grupo-calificaciones-secundario')

        @if (tienePermiso(37))
        {{-- Seguimiento disciplinario (solo niveles pedagógicos) --}}
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.disciplinario && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('disciplinario')"
                    title="Seguimiento disciplinario v1.0">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">SEGUIMIENTO DISCIPLINARIO</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.disciplinario ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.disciplinario && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                    <a href="{{ route('seguimiento.disciplinario') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'seguimiento.disciplinario'),
                       ])
                       title="Seguimiento Disciplinario v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Seguimiento Disciplinario</span>
                    </a>
            </div>
        @endif

        {{-- Asistencia estudiantes --}}
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.inasistenciasEstudiantes && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('inasistenciasEstudiantes')"
                    title="Asistencia estudiantes v1.0">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">ASISTENCIA ESTUDIANTES</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.inasistenciasEstudiantes ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.inasistenciasEstudiantes && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (tienePermiso(38))
                <a href="{{ route('seguimiento.inasistencias') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'seguimiento.inasistencias'
                           || ($route ?? '') === 'seguimiento.inasistencias.create'
                           || ($route ?? '') === 'seguimiento.inasistencias.edit',
                   ])
                   title="Gestión de Inasistencias del Estudiante v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Gestión de Inasistencias del Estudiante</span>
                </a>
                @endif
                @if (tienePermiso(1))
                <a href="{{ route('seguimiento.toma-asistencia-clase') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'seguimiento.toma-asistencia-clase',
                   ])
                   title="Toma de asistencia por curso, materia y fecha">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span class="truncate">Toma de asistencia a clase</span>
                </a>
                @endif
                @if (tienePermiso(24))
                <a href="{{ route('seguimiento.inasistencias.sincroCidi') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'seguimiento.inasistencias.sincroCidi',
                   ])
                   title="Importar inasistencias desde CSV CIDI/GE">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span class="truncate">Descargar inasistencias desde CIDI</span>
                </a>
                @endif
                <a href="{{ route('seguimiento.partes-diarios') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'seguimiento.partes-diarios'),
                   ])
                   title="Parte diario del preceptor (PDF por curso(s) y fecha)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Parte diario del preceptor</span>
                </a>
                <a href="{{ route('seguimiento.inasistencias.informe') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'seguimiento.inasistencias.informe',
                   ])
                   title="Informe de inasistencias por curso (PDF, mismo formato que autogestión)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Informe de Inasistencias</span>
                </a>
            </div>
        @endunless

        @include('layouts.partials.sidebar-grupo-docentes-usuarios')

        @unless (\App\Support\Navegacion\MenuSecretariaPerfil::ocultarGruposPedagogicos())
        @if (tienePermiso(12))
        {{-- Exámenes --}}
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.examenes && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('examenes')"
                    title="Exámenes">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">EXÁMENES</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.examenes ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.examenes && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                <a href="{{ route('examenes.materias-adeudadas.gestion.entrar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('examenes.materias-adeudadas.gestion', 'examenes.materias-adeudadas.gestion.entrar'),
                   ])
                   title="Gestión de materias adeudadas (secundario)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span class="truncate">Gestión de Materias Adeudadas</span>
                </a>
                <a href="{{ route('examenes.borrar-inscripciones') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('examenes.borrar-inscripciones'),
                   ])
                   title="Borrar todas las inscripciones a examen">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span class="truncate">Borrar TODAS las Inscripciones a Examen</span>
                </a>
                <a href="{{ route('examenes.materias-adeudadas.entrar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('examenes.materias-adeudadas', 'examenes.materias-adeudadas.entrar', 'examenes.materias-adeudadas.pdf'),
                   ])
                   title="Listado de materias adeudadas">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Listado de Materias Adeudadas</span>
                </a>
                <a href="{{ route('examenes.acta-volante-previos.entrar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('examenes.acta-volante-previos', 'examenes.acta-volante-previos.entrar', 'examenes.acta-volante-previos.pdf'),
                   ])
                   title="Actas volante de examen (previas)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span class="truncate">Actas Volante</span>
                </a>
                <a href="{{ route('examenes.permiso-examen.entrar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('examenes.permiso-examen', 'examenes.permiso-examen.entrar', 'examenes.permiso-examen.pdf', 'examenes.permiso-examen.pdf.preparar'),
                   ])
                   title="Permisos de examen por alumno">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Permisos de Examen</span>
                </a>
                @if (tenantBoletinMuestraTercerMateria())
                <a href="{{ route('examenes.tercer-materia') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('examenes.tercer-materia', 'examenes.tercer-materia.pdf', 'examenes.tercer-materia.acta-compromiso.pdf'),
                   ])
                   title="Gestión de tercer materia (condición TM)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span class="truncate">Gestión de Tercer Materia</span>
                </a>
                @endif
            </div>
        @endif

        @if (tienePermiso(16))
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.matrizAnaliticos && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('matrizAnaliticos')"
                    title="Matríz y analíticos">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">MATRÍZ Y ANALÍTICOS</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.matrizAnaliticos ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.matrizAnaliticos && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                <a href="{{ route('matrizAnaliticos.libroMatriz') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'matrizAnaliticos.'),
                   ])
                   title="Libro matriz, pase y certificado analítico">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Libro Matriz / Pase / Analítico</span>
                </a>
            </div>
        @endif

        @if (tienePermiso(17) || tienePermiso(18) || tienePermiso(19) || tienePermiso(20) || tienePermiso(21) || tienePermiso(22) || tienePermiso(66))
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.certificados && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('certificados')"
                    title="Certificados">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">CERTIFICADOS</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.certificados ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.certificados && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (tienePermiso(17))
                    <a href="{{ route('certificados.alumnoRegular') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'certificados.alumnoRegular',
                       ])
                       title="Constancia de Alumno Regular">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Constancia de Alumno Regular</span>
                    </a>
                @endif
                @if (tienePermiso(18))
                    <a href="{{ route('certificados.estudiosTramite') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'certificados.estudiosTramite',
                       ])
                       title="Constancia de Certificado en Trámite">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Constancia de Certificado en Trámite</span>
                    </a>
                @endif
                @if (tienePermiso(19))
                    <a href="{{ route('certificados.constanciaDocumentos') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'certificados.constanciaDocumentos',
                       ])
                       title="Constancia de Documentos">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Constancia de Documentos</span>
                    </a>
                @endif
                @if (tienePermiso(20))
                    <a href="{{ route('certificados.asistenciaProfesor') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'certificados.asistenciaProfesor',
                       ])
                       title="Certificado de Asistencia del Profesor">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Asistencia del Profesor</span>
                    </a>
                @endif
                @if (tienePermiso(21))
                    <a href="{{ route('certificados.paseParcial') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'certificados.paseParcial',
                       ])
                       title="Pase Parcial">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Pase Parcial</span>
                    </a>
                @endif
                @if (tienePermiso(22))
                    <a href="{{ route('certificados.solicitudDePase') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'certificados.solicitudDePase',
                       ])
                       title="Solicitud de Pase">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Solicitud de Pase</span>
                    </a>
                @endif
                @if (tienePermiso(66))
                    <a href="{{ route('certificados.cusIsaVozImagen') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => in_array($route ?? '', ['certificados.cusIsaVozImagen', 'certificados.cusIsaVozImagen.pdf'], true),
                       ])
                       title="C.U.S. / I.S.A. / Voz-Imagen">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">C.U.S. / I.S.A. / Voz-Imagen</span>
                    </a>
                @endif
            </div>
        @endif

        {{-- Horarios --}}
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.horarios && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('horarios')"
                    title="Horarios de profesores">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">HORARIOS</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.horarios ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.horarios && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (tienePermiso(13))
                    <a href="{{ route('horarios.config') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'horarios.config',
                       ])
                       title="Turnos, días de clase y horario reloj">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="truncate">Configuración de horarios</span>
                    </a>
                @endif

                @if (tienePermiso(13))
                    <a href="{{ route('horarios.carga') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'horarios.carga',
                       ])
                       title="Carga de horas cátedra por docente">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span class="truncate">Carga de horarios</span>
                    </a>
                @endif

                    <a href="{{ route('horarios.impresion') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'horarios.impresion') || str_starts_with($route ?? '', 'horarios.pdf.'),
                       ])
                       title="Impresión PDF por curso o docente">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <span class="truncate">Impresión de horarios</span>
                    </a>
            </div>

        @include('layouts.partials.sidebar-grupo-estadisticas')

        {{-- Aspirantes --}}
        @if (tienePermiso(\App\Support\PermisosIaCatalog::ASPIRANTES_GESTION))
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.aspirantes && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('aspirantes')"
                    title="Gestión de aspirantes">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">ASPIRANTES</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.aspirantes ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.aspirantes && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                <a href="{{ route('aspirantes.cursos-modelo') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'aspirantes.cursos-modelo',
                   ])
                   title="Cursos modelo (sin sección) que ofrece el nivel">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                    <span class="truncate">Cursos modelo</span>
                </a>
                <a href="{{ route('aspirantes.instancia') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['aspirantes.instancia', 'aspirantes.instancia.create', 'aspirantes.instancia.edit'], true),
                   ])
                   title="Instancia de registro: fechas, cursos y URL pública">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Instancia de registro</span>
                </a>
                <a href="{{ route('aspirantes.listado') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'aspirantes.listado',
                   ])
                   title="Aspirantes registrados">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Aspirantes registrados</span>
                </a>
            </div>
        @endif

        {{-- Matrícula web --}}
        @if (\App\Support\PermisosMatriculaWeb::tieneAlgunAccesoMenu())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.matriculaWeb && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('matriculaWeb')"
                    title="Matrícula web">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">MATRÍCULA WEB</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.matriculaWeb ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.matriculaWeb && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosMatriculaWeb::tiene())
                    <a href="{{ route('matricula-web.documentos') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'matricula-web.documentos'),
                       ])
                       title="PDF de aceptación por nivel (compromiso, AEC, normas, traslado)">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <span class="truncate">Documentos de aceptación</span>
                    </a>
                @endif
            </div>
        @endif
        @endunless

        @include('layouts.partials.sidebar-grupo-cooperadora')

        {{-- Configuración --}}
        @if (tieneAlgunPermisoConfiguracion())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.config && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('config')"
                    title="Configuración v1.0">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 16v-2m8-6h-2M6 12H4m14.364 6.364l-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0L16.95 7.05M7.05 16.95l-1.414 1.414"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Configuración</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.config ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.config && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (tienePermisoConfig(25))
                <a href="{{ route('abm.terlec') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.terlec'),
                   ])
                   title="Términos Lectivos v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Términos Lectivos</span>
                </a>
                @endif

                @if (tienePermisoConfig(26))
                <a href="{{ route('abm.niveles') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.niveles'),
                   ])
                   title="Niveles v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                    <span class="truncate">Niveles</span>
                </a>
                @endif

                @if (tienePermisoConfig(27))
                <a href="{{ route('param.campos-listado-alumnos') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'param.campos-listado-alumnos'),
                   ])
                   title="Campos activos (Legajo del estudiante) v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span class="truncate">Campos activos (Legajo del estudiante)</span>
                </a>
                @endif

                @if (tienePermisoConfig(28))
                <a href="{{ route('param.solapas-legajo') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'param.solapas-legajo'),
                   ])
                   title="Solapas del Legajo v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M3 6h18M3 14h10M3 18h10"/>
                    </svg>
                    <span class="truncate">Solapas del Legajo</span>
                </a>
                @endif

                @if (tienePermisoConfig(29))
                <a href="{{ route('param.campos-legajo-profesor') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'param.campos-legajo-profesor'),
                   ])
                   title="Campos activos (Legajo del docente) v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span class="truncate">Campos activos (Legajo del docente)</span>
                </a>
                @endif

                @if (tienePermisoConfig(30))
                <a href="{{ route('param.solapas-legajo-profesor') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'param.solapas-legajo-profesor'),
                   ])
                   title="Solapas del Legajo del docente v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M3 6h18M3 14h10M3 18h10"/>
                    </svg>
                    <span class="truncate">Solapas del Legajo del docente</span>
                </a>
                @endif

                @if (tienePermisoConfig(31))
                <a href="{{ route('param.parametros-sistema') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'param.parametros-sistema'),
                   ])
                   title="Parámetros del sistema v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6V4m0 16v-2m8-6h-2M6 12H4m14.364 6.364l-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0L16.95 7.05M7.05 16.95l-1.414 1.414"/>
                    </svg>
                    <span class="truncate">Parámetros del sistema</span>
                </a>
                @endif

                @if (tienePermisoConfig(\App\Support\PermisosConfiguracion::ASPIRANTES_CAMPOS))
                <a href="{{ route('param.campos-aspirantes') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'param.campos-aspirantes',
                   ])
                   title="Campos activos (Aspirantes) — qué columnas aparecen en el form público">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span class="truncate">Campos activos (Aspirantes)</span>
                </a>
                @endif

                @if (\App\Support\PermisosConfiguracion::tieneAlgunPermisoSistemaMenu())
                <button type="button"
                        class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors mt-2"
                        :class="(groups.permisosSistema && !sidebarCollapsed) ? 'is-open' : ''"
                        @click="toggleGroup('permisosSistema')"
                        title="Permisos del sistema v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Permisos del sistema</span>
                    <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                         :class="groups.permisosSistema ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div class="space-y-0.5 se-sidebar-group-items"
                     x-show="groups.permisosSistema && !sidebarCollapsed"
                     x-collapse
                     x-cloak>
                    @if (tienePermiso(0))
                    <a href="{{ route('admin.permisos') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'admin.permisos',
                       ])
                       title="Asignación de permisos de usuario v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span class="truncate">Asignación de Permisos de Usuario</span>
                    </a>
                    @endif
                    @if (tienePermiso(14))
                    <a href="{{ route('admin.permisos-por-usuario') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => ($route ?? '') === 'admin.permisos-por-usuario',
                       ])
                       title="Consulta de permisos concedidos por usuario v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span class="truncate">Permisos por Usuario</span>
                    </a>
                    @endif
                </div>
                @endif

                {{-- Planes + Cursos modelo --}}
                @if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraPlanesCursosModelo() && \App\Support\PermisosConfiguracion::tieneAlgunPlanCursoModelo())
                <button type="button"
                        class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors mt-2"
                        :class="(groups.planesCursos && !sidebarCollapsed) ? 'is-open' : ''"
                        @click="toggleGroup('planesCursos')"
                        title="Gestión de planes y cursos modelo v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6V4m0 16v-2m8-6h-2M6 12H4m14.364 6.364l-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0L16.95 7.05M7.05 16.95l-1.414 1.414"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">GESTIÓN DE PLANES Y CURSOS MODELO</span>
                    <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                         :class="groups.planesCursos ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div class="space-y-0.5 se-sidebar-group-items"
                     x-show="groups.planesCursos && !sidebarCollapsed"
                     x-collapse
                     x-cloak>
                    @if (tienePermisoConfig(33))
                    <a href="{{ route('abm.planes') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.planes'),
                       ])
                       title="Gestión de Planes de Estudio v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        <span class="truncate">Gestión de Planes de Estudio</span>
                    </a>
                    @endif

                    @if (tienePermisoConfig(34))
                    <a href="{{ route('abm.curplan') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.curplan'),
                       ])
                       title="Gestión de Cursos y Materias del Plan v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Gestión de Cursos y Materias del Plan</span>
                    </a>
                    @endif
                </div>
                @endif

                {{-- Cursos + Materias del año --}}
                @if (\App\Support\Navegacion\MenuSecretariaPerfil::muestraCursosMateriasAnio() && \App\Support\PermisosConfiguracion::tieneAlgunCursoMateriaAnio())
                <button type="button"
                        class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors mt-2"
                        :class="(groups.cursosMateriasAno && !sidebarCollapsed) ? 'is-open' : ''"
                        @click="toggleGroup('cursosMateriasAno')"
                        title="Gestión de cursos y materias del año v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6V4m0 16v-2m8-6h-2M6 12H4m14.364 6.364l-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0L16.95 7.05M7.05 16.95l-1.414 1.414"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">GESTION DE CURSOS Y MATERIAS DEL AÑO</span>
                    <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                         :class="groups.cursosMateriasAno ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div class="space-y-0.5 se-sidebar-group-items"
                     x-show="groups.cursosMateriasAno && !sidebarCollapsed"
                     x-collapse
                     x-cloak>
                    @if (tienePermisoConfig(35))
                    <a href="{{ route('abm.cursos') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.cursos'),
                       ])
                       title="Gestión de Cursos / Grados / Salas v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="truncate">Gestión de Cursos / Grados / Salas</span>
                    </a>
                    @endif

                    @if (tienePermisoConfig(36))
                    <a href="{{ route('abm.materias-anio') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => str_starts_with($route ?? '', 'abm.materias-anio'),
                       ])
                       title="Gestión de asignaturas del año v1.0">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        <span class="truncate">Gestión de asignaturas del año</span>
                    </a>
                    @endif
                </div>
                @endif

            </div>

        @endif

        {{-- Autogestión Docente: aparece SOLO si el usuario (no-docente) tiene cursos en ppc.
             Cambia al Menú de Docentes; para volver a Secretaría hay que cerrar sesión.
             Ver docs/08-menus-de-navegacion.md. --}}
        @if (\App\Support\ProfesorMenuPortal::tieneAccesoAutogestion(Auth::user()))
            <div class="mt-4 pt-3 border-t se-sidebar-sep">
                <form method="POST" action="{{ route('autogestion.docente.activar') }}" class="m-0 p-0">
                    @csrf
                    <button type="submit"
                            class="se-sidebar-link w-full flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors text-left appearance-none bg-transparent border-0 cursor-pointer"
                            title="Abrir el Menú de Docentes con sus materias asignadas. Para volver a Secretaría, cerrar sesión y reingresar.">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                        <span class="truncate" x-show="!sidebarCollapsed" x-cloak>Autogestión Docente</span>
                    </button>
                </form>
            </div>
        @endif

    </nav>

    {{-- User footer --}}
    <div class="px-4 py-3 border-t se-sidebar-sep relative z-[1]"
         :class="sidebarCollapsed ? 'px-1.5 py-2.5' : ''">
        <div class="flex items-center gap-3"
             :class="sidebarCollapsed ? 'flex-col gap-2' : ''">
            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background: var(--se-primary);">
                <span class="text-white text-[13px] font-bold">
                    {{ strtoupper(substr(Auth::user()->apellido ?? 'U', 0, 1)) }}
                </span>
            </div>
            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-cloak>
                <p class="text-white/90 text-[13px] font-medium truncate">
                    {{ Auth::user()->nombre ?? '' }} {{ Auth::user()->apellido ?? '' }}
                </p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        title="Cerrar sesión"
                        class="text-white/85 hover:text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- Main content area --}}
<div class="se-main flex flex-col min-h-screen transition-[padding] duration-200 ease-in-out"
     :class="[
        sidebarCollapsed ? 'is-collapsed' : '',
        sidebarOpen ? 'is-mobile-open' : ''
     ]">

    {{-- Barra estrecha visible al colapsar: el toggle vive en el sidebar --}}
    {{-- Top bar (mobile): translúcida y borde marca --}}
    <header class="sticky top-0 z-20 md:hidden border-b border-[#C1D7DA] bg-white/95 backdrop-blur-sm supports-[backdrop-filter]:bg-white/85">
        <div class="flex items-center gap-3 h-14 px-4">
            <button @click="sidebarOpen = true"
                    class="text-gray-500 hover:text-gray-700 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="font-semibold text-gray-800 text-sm">
                @yield('pageTitle', config('app.name'))
            </span>
        </div>
    </header>

    {{-- Contenido principal: padding generoso en desktop --}}
    <main class="flex-1 p-4 md:p-8">
        @if (isset($slot) && ! $slot->isEmpty())
            {{ $slot }}
        @elseif (View::hasSection('content'))
            @yield('content')
        @endif
    </main>
</div>

</div>

@include('layouts.partials.livewire-scripts')
<script>
    (() => {
        const IDLE_TIMEOUT_MS = 15 * 60 * 1000;
        const LOGOUT_URL = @json(route('logout'));
        const LOGIN_URL = @json(route('login'));

        let timer = null;
        let hasTriggered = false;

        const getCsrfToken = () =>
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const logoutAndRedirect = async () => {
            if (hasTriggered) return;
            hasTriggered = true;

            try {
                await fetch(LOGOUT_URL, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });
            } catch (e) {
                // Si falla el request, igual redirigimos al login.
            } finally {
                window.location.assign(LOGIN_URL);
            }
        };

        const resetTimer = () => {
            if (hasTriggered) return;
            if (timer) window.clearTimeout(timer);
            timer = window.setTimeout(logoutAndRedirect, IDLE_TIMEOUT_MS);
        };

        const activityEvents = [
            'mousemove',
            'mousedown',
            'keydown',
            'scroll',
            'touchstart',
            'pointerdown',
        ];

        activityEvents.forEach((evt) => {
            window.addEventListener(evt, resetTimer, { passive: true });
        });

        window.addEventListener('focus', resetTimer);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) resetTimer();
        });

        resetTimer();
    })();
</script>
@include('layouts.partials.abrir-pdf-post')
</body>
</html>
