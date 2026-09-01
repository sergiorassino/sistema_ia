@php
    $verNotasOffAlumno = \App\Support\EntoVerNotasOff::consultaEstudianteBloqueada();
    $mensajeVerNotasOffAlumno = \App\Support\EntoVerNotasOff::mensajeConsultaEstudianteBloqueada();
@endphp

@if (\App\Support\Alumnos\PortalFamiliaBoletinPrimEpq::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaBoletinPrimEpq::items() as $itemBoletinEpq)
        <x-alumno-nav-calificaciones-link
            :url="$itemBoletinEpq['url']"
            :titulo="$itemBoletinEpq['titulo']"
            :bloqueada="$verNotasOffAlumno"
            :mensaje="$mensajeVerNotasOffAlumno">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                @if ($itemBoletinEpq['cara'] === 'portada')
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 13l4 4L19 7"/>
                @endif
            </svg>
        </x-alumno-nav-calificaciones-link>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::habilitadoEnMenu())
    <x-alumno-nav-calificaciones-link
        :url="\App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::urlPdf()"
        :titulo="\App\Support\Alumnos\PortalFamiliaBoletinEpqSecundario::tituloMenu()"
        :bloqueada="$verNotasOffAlumno"
        :mensaje="$mensajeVerNotasOffAlumno">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </x-alumno-nav-calificaciones-link>
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinIpe::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaBoletinIpe::itemsEtapa() as $itemBoletinIpe)
        <x-alumno-nav-calificaciones-link
            :url="$itemBoletinIpe['url']"
            :titulo="$itemBoletinIpe['titulo']"
            :bloqueada="$verNotasOffAlumno"
            :mensaje="$mensajeVerNotasOffAlumno">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </x-alumno-nav-calificaciones-link>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinInicialSfq::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaBoletinInicialSfq::items() as $itemInformeSfq)
        <x-alumno-nav-calificaciones-link
            :url="$itemInformeSfq['url']"
            :titulo="$itemInformeSfq['titulo']"
            :bloqueada="$verNotasOffAlumno"
            :mensaje="$mensajeVerNotasOffAlumno">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </x-alumno-nav-calificaciones-link>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaInformeProgresoInicial::habilitadoEnMenu())
    @foreach (\App\Support\Alumnos\PortalFamiliaInformeProgresoInicial::itemsEtapa() as $itemInformeInicial)
        <x-alumno-nav-calificaciones-link
            :url="$itemInformeInicial['url']"
            :titulo="$itemInformeInicial['titulo']"
            :bloqueada="$verNotasOffAlumno"
            :mensaje="$mensajeVerNotasOffAlumno">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </x-alumno-nav-calificaciones-link>
    @endforeach
@elseif (\App\Support\Alumnos\PortalFamiliaBoletinIpe::consultaSecundariaVisible())
    <x-alumno-nav-calificaciones-link
        :url="se_route_url('alumnos.calificaciones')"
        titulo="Consulta de Calificaciones"
        :bloqueada="$verNotasOffAlumno"
        :mensaje="$mensajeVerNotasOffAlumno">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </x-alumno-nav-calificaciones-link>
@endif
