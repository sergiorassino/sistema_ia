@php
    $guard = $guard ?? 'web';
    $logoutRoute = $logoutRoute ?? 'logout';
    $authUser = $guard === 'alumno' ? auth('alumno')->user() : Auth::user();
    $inicialApellido = strtoupper(substr((string) ($authUser?->apellido ?? 'U'), 0, 1));
    $formatoNombre = $formatoNombre ?? 'nombre-apellido';
@endphp

<div class="px-4 py-3 border-t se-sidebar-sep relative z-[1]"
     :class="sidebarCollapsed ? 'px-1.5 py-2.5' : ''">
    <div class="flex items-center gap-3"
         :class="sidebarCollapsed ? 'flex-col gap-2' : ''">
        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
             style="background: var(--se-primary);">
            <span class="text-white text-[13px] font-bold">
                {{ $inicialApellido }}
            </span>
        </div>
        <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-cloak>
            <p class="text-white/90 text-[13px] font-medium truncate">
                @if ($formatoNombre === 'apellido-nombre')
                    {{ $authUser?->apellido ?? '' }}, {{ $authUser?->nombre ?? '' }}
                @else
                    {{ $authUser?->nombre ?? '' }} {{ $authUser?->apellido ?? '' }}
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0"
             :class="sidebarCollapsed ? 'flex-col gap-2' : ''">
            <livewire:auth.cambiar-contrasena-modal :guard="$guard" :key="'cambiar-pwrd-'.$guard" />
            <form method="POST" action="{{ route($logoutRoute) }}">
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
</div>
