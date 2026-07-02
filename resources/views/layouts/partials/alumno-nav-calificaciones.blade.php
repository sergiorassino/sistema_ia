@php
    $verNotasOffAlumno = \App\Support\EntoVerNotasOff::consultaEstudianteBloqueada();
    $mensajeVerNotasOffAlumno = \App\Support\EntoVerNotasOff::mensajeConsultaEstudianteBloqueada();
@endphp

@if (\App\Support\Alumnos\PortalFamiliaBoletinPrimEpq::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaBoletinPrimEpq::items() as $itemBoletinEpq)
        <a href="{{ $itemBoletinEpq['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
           title="{{ $itemBoletinEpq['titulo'] }} (se abre en una nueva pestaña)">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if ($itemBoletinEpq['cara'] === 'portada')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 13l4 4L19 7"/>
                @endif
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ $itemBoletinEpq['titulo'] }}</span>
        </a>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::habilitadoEnMenu())
    <a href="{{ \App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::urlPdf() }}"
       target="_blank"
       rel="noopener noreferrer"
       class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
       title="{{ \App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::tituloMenu() }} (se abre en una nueva pestaña)">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ \App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::tituloMenu() }}</span>
    </a>
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinIpe::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaBoletinIpe::itemsEtapa() as $itemBoletinIpe)
        <a href="{{ $itemBoletinIpe['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
           title="{{ $itemBoletinIpe['titulo'] }} (se abre en una nueva pestaña)">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ $itemBoletinIpe['titulo'] }}</span>
        </a>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaInformeProgresoInicial::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaInformeProgresoInicial::itemsEtapa() as $itemInformeInicial)
        <a href="{{ $itemInformeInicial['url'] }}"
           @if ($verNotasOffAlumno)
               @click.prevent="window.seSwalAviso(@js($mensajeVerNotasOffAlumno), 'Consulta de calificaciones')"
           @else
               target="_blank"
               rel="noopener noreferrer"
           @endif
           class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
           title="{{ $itemInformeInicial['titulo'] }}{{ $verNotasOffAlumno ? '' : ' (se abre en una nueva pestaña)' }}">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span x-show="!sidebarCollapsed" x-cloak class="truncate">{{ $itemInformeInicial['titulo'] }}</span>
        </a>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinIpe::consultaSecundariaVisible())
    <a href="{{ se_route_url('alumnos.calificaciones') }}"
       target="_blank"
       rel="noopener noreferrer"
       class="se-sidebar-link flex items-center gap-2 px-2.5 py-2 rounded-md transition-colors"
       title="Consulta de Calificaciones (se abre en una nueva pestaña)">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span x-show="!sidebarCollapsed" x-cloak class="truncate">Consulta de Calificaciones</span>
    </a>
@endif
