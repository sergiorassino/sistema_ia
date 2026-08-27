{{-- Menú de Administración — navegación lateral. Ver layouts/administracion.blade.php --}}
        
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
                    if (! \Illuminate\Support\Facades\Route::has('listados.estudiantes-formato')) {
                        throw new \RuntimeException("Sidebar: falta la ruta 'listados.estudiantes-formato'.");
                    }
                @endphp
                <a href="{{ route('listados.estudiantes-formato') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => request()->routeIs('listados.estudiantes-formato', 'listados.estudiantes-formato.pdf'),
                   ])
                   title="Listados de Estudiantes con Formato v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                    <span class="truncate">Listados de Estudiantes con Formato</span>
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

        {{-- Comunicación institucional --}}
        @if(tienePermiso(3) || tienePermiso(43) || tienePermiso(4) || tienePermiso(8) || tienePermiso(5) || tienePermiso(78))
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
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'comunicaciones.') && ! in_array(($route ?? ''), ['comunicaciones.nuevo', 'comunicaciones.grupos', 'comunicaciones.revision', 'comunicaciones.auditoria'], true),
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

                @if(tienePermiso(4))
                <a href="{{ route('comunicaciones.grupos') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'comunicaciones.grupos',
                   ])
                   title="Mis grupos de destinatarios v1.0">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="truncate">Mis grupos</span>
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

                @if(tienePermiso(78))
                <a href="{{ route('emails-masivos.index') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'emails-masivos.'),
                   ])
                   title="{{ seSidebarTooltip('Enviar Correo Masivo a Estudiantes', 78) }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Correo masivo estudiantes</span>
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

            @if (\App\Support\PermisosCuotas::muestraGrupoGestionAranceles())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.gestionCuotas && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('gestionCuotas')"
                    title="Gestión de aranceles">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Gestión de aranceles</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.gestionCuotas ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.gestionCuotas && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosCuotas::puedeArancelesPorEstudiante())
                <a href="{{ route('cuotas.index') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'cuotas.')
                           && ($route ?? '') !== 'cuotas.tipos-beca'
                           && ($route ?? '') !== 'cuotas.asignacion-becas'
                           && ! in_array($route ?? '', ['cuotas.resumen-becas-por-nivel', 'cuotas.resumen-becas-por-nivel.csv', 'cuotas.solicitud-ayuda-familiar', 'cuotas.solicitud-ayuda-familiar.pdf'], true)
                           && ! str_starts_with($route ?? '', 'cuotas.importes.')
                           && ($route ?? '') !== 'cuotas.plantillas'
                           && ($route ?? '') !== 'cuotas.generacion-masiva'
                           && ($route ?? '') !== 'cuotas.facturacion-masiva-afip'
                           && ($route ?? '') !== 'cuotas.eliminacion-masiva'
                           && ($route ?? '') !== 'cuotas.edicion-generadas'
                           && ($route ?? '') !== 'cuotas.cancelar-todas-reservas'
                           && ($route ?? '') !== 'cuotas.libro-aranceles'
                           && ($route ?? '') !== 'cuotas.libro-aranceles.pdf'
                           && ($route ?? '') !== 'cuotas.listado-pagos-por-fecha'
                           && ($route ?? '') !== 'cuotas.listado-pagos-por-fecha.pdf'
                           && ($route ?? '') !== 'cuotas.listado-estudiantes-por-cuota'
                           && ($route ?? '') !== 'cuotas.listado-estudiantes-por-cuota.pdf'
                           && ($route ?? '') !== 'cuotas.consulta-afip-comprobante'
                           && ($route ?? '') !== 'cuotas.siro-subida'
                           && ($route ?? '') !== 'cuotas.siro-subida.archivo'
                           && ($route ?? '') !== 'cuotas.siro-descarga'
                           && ($route ?? '') !== 'cuotas.siro-descarga.detalle',
                   ])
                   title="Buscar estudiante y gestionar cuotas">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span class="truncate">Aranceles por estudiante</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeConsultaAfipComprobante())
                <a href="{{ route('cuotas.consulta-afip-comprobante') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.consulta-afip-comprobante',
                   ])
                   title="Consultar factura o nota de crédito en AFIP por número">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 4h.01"/>
                    </svg>
                    <span class="truncate">Consulta AFIP</span>
                </a>
                @endif
            </div>
            @endif

            @if (\App\Support\PermisosCuotas::muestraGrupoGestionMasiva())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.gestionMasiva && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('gestionMasiva')"
                    title="Gestión masiva">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Gestión masiva</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.gestionMasiva ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.gestionMasiva && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosCuotas::puedePlantillas())
                <a href="{{ route('cuotas.plantillas') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.plantillas',
                   ])
                   title="Plantillas de cuotas del año lectivo activo">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="truncate">Crear / Editar Cuotas</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeImportesPorCurso())
                <a href="{{ route('cuotas.importes.index') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => str_starts_with($route ?? '', 'cuotas.importes.'),
                   ])
                   title="Importes y bonificaciones o intereses por curso">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                    <span class="truncate">Importes por curso</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeGeneracionMasiva())
                <a href="{{ route('cuotas.generacion-masiva') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.generacion-masiva',
                   ])
                   title="Generar cuotas para estudiantes regulares por curso">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="truncate">Generación masiva de cuotas</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeEliminacionMasiva())
                <a href="{{ route('cuotas.eliminacion-masiva') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.eliminacion-masiva',
                   ])
                   title="Eliminar cuotas generadas sin pagos por curso">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span class="truncate">Eliminar Masivamente Cuotas Generadas</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeEdicionCuotasGeneradas())
                <a href="{{ route('cuotas.edicion-generadas') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.edicion-generadas',
                   ])
                   title="Edición masiva de importes y vencimientos de cuotas generadas con filtros">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="truncate">Edición Masiva de Cuotas Generadas</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeCancelarTodasReservas())
                <a href="{{ route('cuotas.cancelar-todas-reservas') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.cancelar-todas-reservas',
                   ])
                   title="Poner en cero importe y saldo de todas las reservas sin pago del ciclo activo">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <span class="truncate">Cancelar todas las Reservas</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeFacturacionMasivaAfip())
                <a href="{{ route('cuotas.facturacion-masiva-afip') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.facturacion-masiva-afip',
                   ])
                   title="Facturación masiva AFIP por devengamiento">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Facturación AFIP</span>
                </a>
                @endif
            </div>
            @endif

            @if (\App\Support\PermisosCuotas::muestraGrupoResumenes())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.resumenes && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('resumenes')"
                    title="Resúmenes de aranceles">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Resúmenes</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.resumenes ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.resumenes && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosCuotas::puedeLibroAranceles())
                <a href="{{ route('cuotas.libro-aranceles') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['cuotas.libro-aranceles', 'cuotas.libro-aranceles.pdf'], true),
                   ])
                   title="Libro de aranceles por curso (PDF apaisado)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <span class="truncate">Libro de aranceles</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeListadoPagosPorFecha())
                <a href="{{ route('cuotas.listado-pagos-por-fecha') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['cuotas.listado-pagos-por-fecha', 'cuotas.listado-pagos-por-fecha.pdf'], true),
                   ])
                   title="Pagos recibidos entre dos fechas (PDF)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span class="truncate">Listado de pagos por fecha</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeListadoEstudiantesPorCuota())
                <a href="{{ route('cuotas.listado-estudiantes-por-cuota') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['cuotas.listado-estudiantes-por-cuota', 'cuotas.listado-estudiantes-por-cuota.pdf'], true),
                   ])
                   title="Estudiantes con cuotas generadas (PDF apaisado)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">Listado de estudiantes por cuota</span>
                </a>
                @endif
            </div>
            @endif

            @if (\App\Support\PermisosCuotas::muestraGrupoBecas())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.becas && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('becas')"
                    title="Becas">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Becas</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.becas ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.becas && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosCuotas::puedeTiposBeca())
                <a href="{{ route('cuotas.tipos-beca') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.tipos-beca',
                   ])
                   title="Tipos de beca y porcentaje de descuento">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span class="truncate">Tipos de Beca</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeAsignacionBecas())
                <a href="{{ route('cuotas.asignacion-becas') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'cuotas.asignacion-becas',
                   ])
                   title="Asignar beca a alumnos por curso o búsqueda individual">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">Asignación de Becas</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeResumenBecasPorNivel())
                <a href="{{ route('cuotas.resumen-becas-por-nivel') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['cuotas.resumen-becas-por-nivel', 'cuotas.resumen-becas-por-nivel.csv'], true),
                   ])
                   title="Cantidad de becas otorgadas por tipo y nivel pedagógico">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V7a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Resumen de Becas por Nivel</span>
                </a>
                @endif
                @if (\App\Support\PermisosCuotas::puedeSolicitudAyudaFamiliar())
                <a href="{{ route('cuotas.solicitud-ayuda-familiar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['cuotas.solicitud-ayuda-familiar', 'cuotas.solicitud-ayuda-familiar.pdf'], true),
                   ])
                   title="Buscar estudiante e imprimir solicitud de ayuda familiar">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="truncate">Solicitud de Ayuda Familiar</span>
                </a>
                @endif
            </div>
            @endif

            {{-- Gestión de mora --}}
            @if (\App\Support\Mora\PermisosMora::muestraGrupoGestionMora())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.gestionMora && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('gestionMora')"
                    title="Gestión de mora">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Gestión de mora</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.gestionMora ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.gestionMora && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\Mora\PermisosMora::puedeEstadoDeudaFamiliar())
                <a href="{{ route('mora.estado-deuda-familiar') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', [
                           'mora.estado-deuda-familiar',
                           'mora.estado-deuda-familiar.pdf',
                           'mora.estado-deuda-familiar.listado-pdf',
                           'mora.estado-deuda-familiar.listado-excel',
                       ], true),
                   ])
                   title="Listado de familias y estado de deuda">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="truncate">Estado de Deuda Familiar</span>
                </a>
                @endif
                @if (\App\Support\Mora\PermisosMora::puedeEstadoDeudaEstudiante())
                <a href="{{ route('mora.estado-deuda-estudiante') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', [
                           'mora.estado-deuda-estudiante',
                           'mora.estado-deuda-estudiante.pdf',
                           'mora.estado-deuda-estudiante.listado-pdf',
                           'mora.estado-deuda-estudiante.listado-excel',
                       ], true),
                   ])
                   title="Listado de estudiantes y estado de deuda (con o sin familia)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span class="truncate">Estado de Deuda por Estudiante</span>
                </a>
                @endif
                @if (\App\Support\Mora\PermisosMora::puedeGestionMorosos())
                <a href="{{ route('mora.gestion-morosos') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => in_array($route ?? '', ['mora.gestion-morosos', 'mora.gestion-morosos.pdf'], true),
                   ])
                   title="Filtros y listado de deuda (PDF)">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span class="truncate">Gestión de Morosos</span>
                </a>
                @endif
            </div>
            @endif

            @if (\App\Support\PermisosMediosPago::muestraGrupo())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.mediosPago && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('mediosPago')"
                    title="Medios de pago">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">Medios de pago</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.mediosPago ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.mediosPago && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosMediosPago::muestraSubgrupoSiro())
                <button type="button"
                        class="se-sidebar-subgroupbtn w-full flex items-center gap-2 pr-2.5 py-1.5 text-[10px] font-semibold uppercase tracking-wide rounded-md transition-colors mt-1"
                        :class="(groups.mediosPagoSiro && !sidebarCollapsed) ? 'is-open' : ''"
                        @click="toggleGroup('mediosPagoSiro')"
                        title="SIRO">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">SIRO</span>
                    <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                         :class="groups.mediosPagoSiro ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div class="space-y-0.5 se-sidebar-subgroup-items se-sidebar-group-items"
                     x-show="groups.mediosPagoSiro && !sidebarCollapsed"
                     x-collapse
                     x-cloak>
                    @if (\App\Support\PermisosCuotas::puedeSiroSubidaBaseDeuda())
                    <a href="{{ route('cuotas.siro-subida') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => in_array($route ?? '', ['cuotas.siro-subida', 'cuotas.siro-subida.archivo'], true),
                       ])
                       title="Generar archivo de base de deuda para subir a SIRO">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span class="truncate">Subida base de deuda</span>
                    </a>
                    @endif
                    @if (\App\Support\PermisosCuotas::puedeSiroDescargaRendicion())
                    <a href="{{ route('cuotas.siro-descarga') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => in_array($route ?? '', ['cuotas.siro-descarga', 'cuotas.siro-descarga.detalle'], true),
                       ])
                       title="Descargar planillas de rendición SIRO e impactar pagos">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span class="truncate">Descarga rendición</span>
                    </a>
                    @endif
                    @if (\App\Support\PermisosCuotas::puedeSiroCuponesVencidos())
                    <a href="{{ route('cuotas.siro-cupones-vencidos') }}"
                       @class([
                           'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                           'is-active shadow-sm' => in_array($route ?? '', ['cuotas.siro-cupones-vencidos', 'cuotas.siro-cupones-vencidos.archivo'], true),
                       ])
                       title="Actualizar vencimiento de cupones impagos y subir a SIRO">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span class="truncate">Actualizar cupones vencidos y subir</span>
                    </a>
                    @endif
                </div>
                @endif
            </div>
            @endif

            @if (\App\Support\PermisosArca::muestraGrupoArca())
            <div class="mt-4"></div>
            <button type="button"
                    class="se-sidebar-groupbtn w-full flex items-center gap-2 px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide rounded-md transition-colors"
                    :class="(groups.arca && !sidebarCollapsed) ? 'is-open' : ''"
                    @click="toggleGroup('arca')"
                    title="ARCA">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span x-show="!sidebarCollapsed" x-cloak class="se-sidebar-group-label min-w-0 flex-1 truncate text-left">ARCA</span>
                <svg x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 transition-transform"
                     :class="groups.arca ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="mt-1 space-y-0.5 se-sidebar-group-items"
                 x-show="groups.arca && !sidebarCollapsed"
                 x-collapse
                 x-cloak>
                @if (\App\Support\PermisosArca::puedeConsultaCuitPorDni())
                <a href="{{ route('arca.consulta-cuit-dni') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'arca.consulta-cuit-dni',
                   ])
                   title="Consultar CUIT o CUIL por DNI en ARCA">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 4h.01"/>
                    </svg>
                    <span class="truncate">Consulta CUIT por DNI</span>
                </a>
                @endif
                @if (\App\Support\PermisosArca::puedeEditarObservacionFactura())
                <a href="{{ route('arca.observacion-factura') }}"
                   @class([
                       'se-sidebar-link flex items-center gap-2 px-2.5 py-2 text-[13px] rounded-md transition-colors',
                       'is-active shadow-sm' => ($route ?? '') === 'arca.observacion-factura',
                   ])
                   title="Editar observación del impreso de factura AFIP">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="truncate">Editar Observación Factura</span>
                </a>
                @endif
            </div>
            @endif

        @include('layouts.partials.sidebar-grupo-docentes-usuarios')
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

            </div>

        @endif
