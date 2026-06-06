{{-- Menú de Administración — aranceles, legajos transversales, comunicación. Ver docs/08-menus-de-navegacion.md --}}
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
        config: {{ (str_starts_with($route ?? '', 'abm.terlec') || str_starts_with($route ?? '', 'abm.niveles') || str_starts_with($route ?? '', 'param.') || ($route ?? '') === 'admin.permisos' || ($route ?? '') === 'admin.permisos-por-usuario') ? 'true' : 'false' }},
        permisosSistema: {{ (($route ?? '') === 'admin.permisos' || ($route ?? '') === 'admin.permisos-por-usuario') ? 'true' : 'false' }},
        students: {{ (str_starts_with($route ?? '', 'abm.legajos') || (str_starts_with($route ?? '', 'listados.') && ! request()->routeIs('listados.estudiantes-datos', 'listados.estudiantes-datos.excel', 'listados.estudiantes-datos.pdf'))) ? 'true' : 'false' }},
        cuadernoComunicados: {{ ((str_starts_with($route ?? '', 'comunicaciones.') || ($route ?? '') === 'param.com-canales' || ($route ?? '') === 'push.suscribir') && (tienePermiso(3) || tienePermiso(43) || tienePermiso(4) || tienePermiso(8) || tienePermiso(5))) ? 'true' : 'false' }},
        docentes: {{ (str_starts_with($route ?? '', 'abm.profesores-por-materia') || str_starts_with($route ?? '', 'abm.cursos-por-profesor') || str_starts_with($route ?? '', 'abm.legajos-profesor') || str_starts_with($route ?? '', 'docentes.inasistencias')) ? 'true' : 'false' }},
        gestionCuotas: {{ str_starts_with($route ?? '', 'cuotas.')
            && ($route ?? '') !== 'cuotas.tipos-beca'
            && ($route ?? '') !== 'cuotas.asignacion-becas'
            && ! in_array($route ?? '', ['cuotas.resumen-becas-por-nivel', 'cuotas.resumen-becas-por-nivel.csv', 'cuotas.solicitud-ayuda-familiar', 'cuotas.solicitud-ayuda-familiar.pdf'], true)
            && ($route ?? '') !== 'cuotas.plantillas'
            && ! str_starts_with($route ?? '', 'cuotas.importes.')
            && ($route ?? '') !== 'cuotas.generacion-masiva'
            && ($route ?? '') !== 'cuotas.eliminacion-masiva'
            && ($route ?? '') !== 'cuotas.edicion-generadas'
            && ($route ?? '') !== 'cuotas.cancelar-todas-reservas'
            && ($route ?? '') !== 'cuotas.libro-aranceles'
            && ($route ?? '') !== 'cuotas.libro-aranceles.pdf'
            && ($route ?? '') !== 'cuotas.listado-pagos-por-fecha'
            && ($route ?? '') !== 'cuotas.listado-pagos-por-fecha.pdf'
            && ($route ?? '') !== 'cuotas.listado-estudiantes-por-cuota'
            && ($route ?? '') !== 'cuotas.listado-estudiantes-por-cuota.pdf' ? 'true' : 'false' }},
        becas: {{ in_array($route ?? '', ['cuotas.tipos-beca', 'cuotas.asignacion-becas', 'cuotas.resumen-becas-por-nivel', 'cuotas.resumen-becas-por-nivel.csv', 'cuotas.solicitud-ayuda-familiar', 'cuotas.solicitud-ayuda-familiar.pdf'], true) ? 'true' : 'false' }},
        gestionMasiva: {{ in_array($route ?? '', ['cuotas.plantillas', 'cuotas.generacion-masiva', 'cuotas.eliminacion-masiva', 'cuotas.edicion-generadas', 'cuotas.cancelar-todas-reservas'], true)
            || str_starts_with($route ?? '', 'cuotas.importes.') ? 'true' : 'false' }},
        resumenes: {{ in_array($route ?? '', [
            'cuotas.libro-aranceles',
            'cuotas.libro-aranceles.pdf',
            'cuotas.listado-pagos-por-fecha',
            'cuotas.listado-pagos-por-fecha.pdf',
            'cuotas.listado-estudiantes-por-cuota',
            'cuotas.listado-estudiantes-por-cuota.pdf',
        ], true) ? 'true' : 'false' }},
        gestionMora: {{ str_starts_with($route ?? '', 'mora.') ? 'true' : 'false' }},
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
        @include('layouts.partials.sidebar-nav-administracion')
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
