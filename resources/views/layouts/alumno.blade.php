{{-- Menú de Alumnos — autogestión familia/estudiante. Ver docs/08-menus-de-navegacion.md --}}
<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F4F8F9]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? (isset($title) ? $title . ' — ' : '') }}{{ config('app.name') }}</title>
    @include('layouts.partials.favicon-alumno')
    @include('layouts.partials.sidebar-bosque-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $route = request()->route()?->getName();
    /** En desktop: rail colapsado en todas las pantallas; hover/focus expanden (mismo patrón que Secretaría salvo dashboard). */
    $isSidebarPeekMode = true;
@endphp
<body class="h-full">

<div id="se-shell"
     class="h-full"
     x-data="{
        sidebarOpen: false,
        peekMenuMode: @json($isSidebarPeekMode),
        sidebarCollapsed: false,
        _sidebarPeekTimer: null,
        isDesktopPeekLayout() {
            return window.matchMedia && window.matchMedia('(min-width: 768px)').matches;
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
            this.applyPeekSidebarBootState(false);
            if (!this._sePeekResizeBound) {
                this._sePeekResizeBound = true;
                window.addEventListener('resize', () => this.applyPeekSidebarBootState(true));
            }
        },
     }">

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

<aside x-ref="seSidebar"
       @mouseenter="peekSidebarExpandNow()"
       @mouseleave="peekSidebarMaybeCollapseLater()"
       @focusin="peekSidebarExpandNow()"
       @focusout="peekSidebarFocusOut($event)"
       class="se-sidebar se-sidebar--bosque fixed inset-y-0 left-0 z-[1000] flex flex-col overflow-hidden transform transition-transform duration-200 ease-in-out
              md:translate-x-0 md:transition-[width] md:duration-200 md:ease-in-out md:shadow-lg"
       :class="[
           sidebarOpen ? 'translate-x-0' : '-translate-x-full',
           'md:translate-x-0',
           sidebarCollapsed ? 'is-collapsed' : ''
       ]">

    @php
        $logoUrl = studentLogoUrl() ?: entoInstitutionalLogoUrlFallback() ?: asset('img/3.png');
        $alumno = auth('alumno')->user();
        $sidebarSessionLine = studentCtx()->nivelNombre()
            . ' · ' . studentCtx()->terlecAno()
            . ' · ' . trim((string) ($alumno?->apellido ?? '') . ', ' . (string) ($alumno?->nombre ?? ''));
    @endphp

    <div class="border-b se-sidebar-sep relative z-[1] overflow-hidden flex-shrink-0"
         :class="sidebarCollapsed ? 'flex flex-col items-center gap-2 py-3 px-1' : 'min-h-12 px-2.5 py-2 flex flex-row items-center gap-2'">

        <div class="flex min-w-0 items-center gap-2"
             :class="sidebarCollapsed ? 'flex-col justify-center' : 'flex-1'">
            @include('layouts.partials.logo-institucional', ['url' => $logoUrl, 'context' => 'sidebar'])

            <p class="text-white/90 text-[12px] font-semibold truncate min-w-0 leading-snug"
               x-show="!sidebarCollapsed" x-cloak
               title="{{ $sidebarSessionLine }}">
                <span class="text-white/90">{{ studentCtx()->nivelNombre() }}</span>
                <span class="text-white/45"> · </span>
                <span class="text-white/90">{{ studentCtx()->terlecAno() }}</span>
                <span class="block text-[11px] font-medium text-white/65 truncate mt-0.5">
                    {{ $alumno?->apellido ?? '' }}{{ ($alumno?->apellido && $alumno?->nombre) ? ', ' : '' }}{{ $alumno?->nombre ?? '' }}
                </span>
            </p>
        </div>
    </div>

    @php
        $alumnoRuta = $route ?? '';
        $alumnoComCuadernoActivo = str_starts_with($alumnoRuta, 'alumnos.comunicaciones');
    @endphp
    <nav class="flex-1 relative z-[1] px-2.5 py-3 overflow-y-auto space-y-0.5"
         :class="sidebarCollapsed ? '!px-1 !py-2' : ''"
         @click.capture="$event.target.closest('a[href]') && (sidebarOpen = false)">

        @if (tenantAutogestionMenuInicioHabilitada())
            <a href="{{ route('alumnos.home') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                   'is-active shadow-sm' => ($route ?? '') === 'alumnos.home',
               ])
               title="Escritorio de inicio">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Inicio</span>
            </a>
        @endif

        @include('layouts.partials.alumno-nav-calificaciones')

        @if (tenantAutogestionInformeInasistenciasHabilitada())
        <a href="{{ se_route_url('alumnos.inasistencias.informe') }}"
           target="_blank"
           rel="noopener noreferrer"
           class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
           title="Informe de Inasistencias (se abre en una nueva pestaña)">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">Informe de Inasistencias</span>
        </a>
        @endif

        @if (tenantAutogestionActualizacionDatosHabilitada())
            <a href="{{ route('alumnos.actualizacion-datos') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'alumnos.actualizacion-datos'),
               ])
               title="Actualización de datos personales del legajo">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Actualización de Datos Personales</span>
            </a>
        @endif

        @if (tenantAutogestionFichaMatriculaHabilitada())
            <a href="{{ se_route_url('alumnos.ficha-matricula') }}"
               target="_blank"
               rel="noopener noreferrer"
               class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
               title="Imprimir ficha de matrícula (se abre en una nueva pestaña)">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Imprimir Ficha de Matrícula</span>
            </a>
        @endif

        @if (tenantAutogestionArancelesEscolaresHabilitada())
            <a href="{{ route('alumnos.aranceles-escolares') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'alumnos.aranceles-escolares'),
               ])
               title="{{ tenantAutogestionArancelesEscolaresMenuEtiqueta() }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ tenantAutogestionArancelesEscolaresMenuEtiqueta() }}</span>
            </a>
        @endif

        @if (tenantAutogestionHorarioClaseHabilitada())
            <a href="{{ se_route_url('alumnos.horario-clase') }}"
               target="_blank"
               rel="noopener noreferrer"
               class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
               title="Horario de clase de su curso (se abre en una nueva pestaña)">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Horario de Clase</span>
            </a>
        @endif

        @if(filled(config('tenant.autogestion.aranceles_aulica_url')))
            <a href="{{ config('tenant.autogestion.aranceles_aulica_url') }}"
               target="_blank"
               rel="noopener noreferrer"
               class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
               title="Gestión de Aranceles Escolares (se abre en una nueva pestaña)">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Gestión de Aranceles Escolares</span>
            </a>
        @endif

        @if (tenantAutogestionComunicacionesHabilitada())
            <p x-show="!sidebarCollapsed" x-cloak class="se-sidebar-nav-label mt-3 mb-0.5 px-2.5">
                Cuaderno de comunicados
            </p>

            <a href="{{ route('alumnos.comunicaciones.index') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                   'is-active shadow-sm' => $alumnoComCuadernoActivo,
               ])
               title="Bandeja de comunicados con la escuela">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Bandeja de Comunicados</span>
            </a>

            <a href="{{ route('alumnos.comunicaciones.nuevo') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                   'is-active shadow-sm' => $alumnoRuta === 'alumnos.comunicaciones.nuevo',
               ])
               title="Escribir un nuevo comunicado a la escuela">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Nuevo comunicado</span>
            </a>

            <p x-show="!sidebarCollapsed" x-cloak class="se-sidebar-nav-label mt-3 mb-0.5 px-2.5">
                Ajustes
            </p>

            <a href="{{ route('alumnos.push.index') }}"
               @class([
                   'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                   'is-active shadow-sm' => str_starts_with($route ?? '', 'alumnos.push'),
               ])
               title="Notificaciones Push">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="truncate">Notificaciones Push</span>
            </a>
        @endif
    </nav>

    @include('layouts.partials.sidebar-user-footer', [
        'guard' => 'alumno',
        'logoutRoute' => 'alumnos.logout',
        'formatoNombre' => 'apellido-nombre',
    ])
</aside>

<div class="se-main flex flex-col min-h-screen transition-[padding] duration-200 ease-in-out"
     :class="[
        sidebarCollapsed ? 'is-collapsed' : '',
        sidebarOpen ? 'is-mobile-open' : ''
     ]">

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

    <main class="flex-1 p-4 md:p-8">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </main>
</div>

</div>

@include('layouts.partials.livewire-scripts')
<script>
    (() => {
        const IDLE_TIMEOUT_MS = 15 * 60 * 1000;
        const LOGOUT_URL = @json(se_route_url('alumnos.logout'));
        const LOGIN_URL = @json(se_route_url('alumnos.login'));

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
</body>
</html>

