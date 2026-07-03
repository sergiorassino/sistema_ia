{{-- Menú de Secretaría: grupo COOPERADORA — antes de Configuración. --}}

@if (\App\Support\Cooperadora\PermisosCooperadora::muestraGrupoCooperadora())
    <div class="mt-4"></div>
    <button type="button"
            class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
            :class="(groups.cooperadora && !sidebarCollapsed) ? 'is-open' : ''"
            @click="toggleGroup('cooperadora')"
            title="{{ seSidebarTooltip('Cooperadora escolar', [72, 73, 74, 75]) }}">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">COOPERADORA</span>
        <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
             :class="groups.cooperadora ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div class="mt-1 space-y-0.5 se-sidebar-group-items"
         x-show="groups.cooperadora && !sidebarCollapsed"
         x-collapse
         x-cloak>
        @if (\App\Support\Cooperadora\PermisosCooperadora::puedeIngresos())
        <a href="{{ route('cooperadora.ingresos') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'cooperadora.ingresos' || ($route ?? '') === 'cooperadora.recibo.pdf',
           ])
           title="{{ seSidebarTooltip('Registro de ingresos y recibos', 73) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4v16m8-8H4"/>
            </svg>
            <span class="truncate">Ingresos</span>
        </a>
        @endif
        @if (\App\Support\Cooperadora\PermisosCooperadora::puedeEgresos())
        <a href="{{ route('cooperadora.egresos') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'cooperadora.egresos') || ($route ?? '') === 'cooperadora.orden-pago.pdf',
           ])
           title="{{ seSidebarTooltip('Registro de egresos y órdenes de pago', 74) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M20 12H4"/>
            </svg>
            <span class="truncate">Egresos</span>
        </a>
        @endif
        @if (\App\Support\Cooperadora\PermisosCooperadora::puedeMovimientos())
        <a href="{{ route('cooperadora.movimientos') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => in_array($route ?? '', ['cooperadora.movimientos', 'cooperadora.movimientos.pdf'], true),
           ])
           title="{{ seSidebarTooltip('Movimientos por fecha con saldo', 75) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span class="truncate">Movimientos</span>
        </a>
        @endif
        @if (\App\Support\Cooperadora\PermisosCooperadora::puedeIngresos())
        <a href="{{ route('cooperadora.pagos-estudiante') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => in_array($route ?? '', [
                   'cooperadora.pagos-estudiante',
                   'cooperadora.pagos-estudiante.ver',
                   'cooperadora.pagos-estudiante.pdf',
               ], true),
           ])
           title="{{ seSidebarTooltip('Pagos registrados por estudiante', 73) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span class="truncate">Pagos por estudiante</span>
        </a>
        @endif
        @if (\App\Support\Cooperadora\PermisosCooperadora::puedeParametrizacion())
        <a href="{{ route('cooperadora.config') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'cooperadora.config',
           ])
           title="{{ seSidebarTooltip('Datos institucionales y descuento hermanos', 72) }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="truncate">Configuración</span>
        </a>
        <a href="{{ route('cooperadora.rubros') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'cooperadora.rubros',
           ])
           title="{{ seSidebarTooltip('Rubros de ingreso', 72) }}">
            <span class="truncate">Rubros de Ingreso</span>
        </a>
        <a href="{{ route('cooperadora.items') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'cooperadora.items',
           ])
           title="{{ seSidebarTooltip('Ítems de ingreso', 72) }}">
            <span class="truncate">Ítems de ingreso</span>
        </a>
        <a href="{{ route('cooperadora.proveedores') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => str_starts_with($route ?? '', 'cooperadora.proveedores'),
           ])
           title="{{ seSidebarTooltip('Proveedores para egresos', 72) }}">
            <span class="truncate">Proveedores</span>
        </a>
        <a href="{{ route('cooperadora.medios-pago') }}"
           @class([
               'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
               'is-active shadow-sm' => ($route ?? '') === 'cooperadora.medios-pago',
           ])
           title="{{ seSidebarTooltip('Medios de pago para ingresos y egresos', 72) }}">
            <span class="truncate">Medios de pago</span>
        </a>
        @endif
    </div>
@endif
