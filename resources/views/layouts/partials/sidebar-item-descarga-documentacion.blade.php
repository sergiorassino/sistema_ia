@if (tienePermiso(\App\Support\PermisosIaCatalog::DESCARGA_DOCUMENTACION_CURSO))
    @php
        if (! \Illuminate\Support\Facades\Route::has('listados.documentacion-curso')) {
            throw new \RuntimeException("Sidebar: falta la ruta 'listados.documentacion-curso'.");
        }
    @endphp
    <a href="{{ route('listados.documentacion-curso') }}"
       @class([
           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
           'is-active shadow-sm' => request()->routeIs('listados.documentacion-curso', 'listados.documentacion-curso.zip'),
       ])
       title="{{ seSidebarTooltip('Descarga de documentación por curso v1.0', \App\Support\PermisosIaCatalog::DESCARGA_DOCUMENTACION_CURSO) }}">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        <span class="truncate">Descarga de documentación</span>
    </a>
@endif
