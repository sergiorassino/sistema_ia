@props([
    'idLegajos',
    'etiqueta' => 'Volver',
])

<x-nav-contexto-estudiante
    destino="cuotas.estudiante"
    :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::CUOTAS_GESTION"
    :id-legajos="$idLegajos"
    :vista-cuotas="\App\Support\Navegacion\ContextoEstudianteSesion::etiquetaVistaCuotas(\App\Support\Navegacion\ContextoEstudianteSesion::CUOTAS_GESTION)"
    tag="a"
    {{ $attributes }}>
    {{ $etiqueta }}
</x-nav-contexto-estudiante>
