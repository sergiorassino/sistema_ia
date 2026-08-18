{{--
    Menú de Docentes — portal reducido para profesores (pocas tareas).
    No confundir con el Menú de Secretaría (layouts/app.blade.php) ni con el grupo
    sidebar "DOCENTES" de secretaría. Ver docs/08-menus-de-navegacion.md
--}}
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
    $portalDocenteMenuItems = \App\Support\Navegacion\PortalDocenteMenu::itemsParaSesionActual();
    /** En desktop: rail colapsado salvo inicio; hover/focus expanden (mismo patrón que Secretaría salvo dashboard). */
    $isSidebarPeekMode = (($route ?? '') !== 'portalDocente.home');
    $docenteComBandejaActiva = str_starts_with($route ?? '', 'portalDocente.comunicaciones')
        && ! in_array($route ?? '', ['portalDocente.comunicaciones.nuevo', 'portalDocente.comunicaciones.grupos', 'portalDocente.comunicaciones.revision'], true);
    $docentePushActivo = ($route ?? '') === 'portalDocente.push.suscribir';
    $docenteRecursosDidacticosVisible = tenantPortalDocenteRecursosDidacticosVisible();
    $docenteRecursosDidacticosListadoActivo = ($route ?? '') === 'portalDocente.materialDidactico.index';
    $docenteRecursosDidacticosReservarActivo = ($route ?? '') === 'portalDocente.materialDidactico.reservar';
@endphp
<body class="h-full">

<div id="se-shell-docente"
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
        $logoUrl = schoolLogoUrl() ?: asset('img/3.png');
        $usuario = Auth::user();
        $sidebarSessionLine = schoolCtx()->nivelNombre()
            . ' · ' . schoolCtx()->terlecAno()
            . ' · ' . trim((string) ($usuario?->apellido ?? '') . ' ' . (string) ($usuario?->nombre ?? ''));
    @endphp

    <div class="border-b se-sidebar-sep relative z-[1] overflow-hidden flex-shrink-0"
         :class="sidebarCollapsed ? 'flex flex-col items-center gap-2 py-3 px-1' : 'min-h-12 px-2.5 py-2 flex flex-col gap-1'">

        <a href="{{ route('portalDocente.home') }}"
           @click="sidebarOpen = false"
           class="flex min-w-0 items-center gap-2 rounded-lg text-left no-underline text-inherit transition-colors hover:bg-[var(--se-hover-bg)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--se-light-blue)]"
           :class="sidebarCollapsed ? 'flex-col justify-center' : 'flex-row flex-1'"
           title="Inicio del portal docente">
            @include('layouts.partials.logo-institucional', ['url' => $logoUrl, 'context' => 'sidebar'])
            <p class="text-white/90 text-[12px] font-semibold truncate min-w-0 leading-snug"
               x-show="!sidebarCollapsed" x-cloak
               title="{{ $sidebarSessionLine }}">
                <span class="text-white/90">{{ schoolCtx()->nivelNombre() }}</span>
                <span class="text-white/45"> · </span>
                <span class="text-white/90">{{ schoolCtx()->terlecAno() }}</span>
                <span class="block text-[11px] font-medium text-white/65 truncate mt-0.5">
                    {{ $usuario?->nombre ?? '' }} {{ $usuario?->apellido ?? '' }}
                </span>
            </p>
        </a>

        <p x-show="!sidebarCollapsed" x-cloak class="se-sidebar-nav-label px-0.5">
            Menú de Docentes
        </p>
    </div>

    <nav class="flex-1 min-h-0 relative z-[1] px-2.5 py-3 overflow-y-auto space-y-0.5"
         :class="sidebarCollapsed ? '!px-1 !py-2' : ''"
         @click.capture="$event.target.closest('a[href]') && (sidebarOpen = false)">

        @foreach ($portalDocenteMenuItems as $item)
            @include('layouts.partials.sidebar-portal-docente-item', ['item' => $item])
        @endforeach

        @if ($docenteRecursosDidacticosVisible)
            <p x-show="!sidebarCollapsed" x-cloak class="se-sidebar-nav-label mt-3 mb-0.5 px-2.5">
                Recursos didácticos
            </p>

            @if (tenantPortalDocenteRecursosDidacticosListado())
                <a href="{{ route('portalDocente.materialDidactico.index') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                       'is-active shadow-sm' => $docenteRecursosDidacticosListadoActivo,
                   ])
                   title="Consultar reservas de material didáctico">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-cloak class="truncate">Listado de reservas</span>
                </a>
            @endif

            @if (tenantPortalDocenteRecursosDidacticosNuevaReserva())
                <a href="{{ route('portalDocente.materialDidactico.reservar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
                       'is-active shadow-sm' => $docenteRecursosDidacticosReservarActivo,
                   ])
                   title="Registrar nueva reserva de material didáctico">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-cloak class="truncate">Nueva reserva</span>
                </a>
            @endif
        @endif

        <p x-show="!sidebarCollapsed" x-cloak class="se-sidebar-nav-label mt-3 mb-0.5 px-2.5">
            Comunicación institucional
        </p>

        <a href="{{ route('portalDocente.comunicaciones.index') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
               'is-active shadow-sm' => $docenteComBandejaActiva,
           ])
           title="Bandeja de comunicados con familias y personal">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">Bandeja de comunicados</span>
        </a>

        <a href="{{ route('portalDocente.comunicaciones.nuevo') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'portalDocente.comunicaciones.nuevo',
           ])
           title="Nuevo comunicado a familias o personal">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">Nuevo comunicado</span>
        </a>

        <a href="{{ route('portalDocente.comunicaciones.grupos') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'portalDocente.comunicaciones.grupos',
           ])
           title="Grupos propios de destinatarios para este nivel">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">Mis grupos</span>
        </a>

        <a href="{{ route('portalDocente.push.suscribir') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors',
               'is-active shadow-sm' => $docentePushActivo,
           ])
           title="Notificaciones push en este dispositivo">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">Notificaciones Push</span>
        </a>

    </nav>

    @include('layouts.partials.sidebar-user-footer', ['guard' => 'web', 'logoutRoute' => 'logout'])
</aside>

<div class="se-main flex flex-col min-h-screen transition-[padding] duration-200 ease-in-out"
     :class="[
        sidebarCollapsed ? 'is-collapsed' : '',
        sidebarOpen ? 'is-mobile-open' : ''
     ]">

    <header class="sticky top-0 z-20 md:hidden border-b border-[#C1D7DA] bg-white/95 backdrop-blur-sm supports-[backdrop-filter]:bg-white/85">
        <div class="flex items-center gap-3 h-14 px-4">
            <button type="button" @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="font-semibold text-gray-800 text-sm">@yield('pageTitle', 'Portal docente')</span>
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
@include('layouts.partials.abrir-pdf-post')
</body>
</html>
