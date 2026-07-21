@props([
    'matricula',
    'curso' => null,
    'pendientes' => [],
    'destacado' => false,
    'compacto' => false,
    'multilinea' => false,
])

@php
    use App\Support\PermisosIaCatalog;
    use App\Support\Tea\TeaInstanciasPendientes;

    $lista = is_array($pendientes) ? $pendientes : [];
@endphp

@if ($lista !== [] && TeaInstanciasPendientes::modulosActivos())
    @php
        $texto = $multilinea
            ? TeaInstanciasPendientes::textoAviso($lista)
            : ($compacto
                ? TeaInstanciasPendientes::textoAvisoCompacto($lista)
                : TeaInstanciasPendientes::textoAviso($lista));
        $titulo = TeaInstanciasPendientes::tituloAviso($lista);
        $puedeIr = tienePermiso(PermisosIaCatalog::TEA_ESTUDIANTES_GESTION);
    @endphp
    @php
        $pillBase = $destacado
            ? 'se-pill items-start gap-1.5 border-red-300 bg-red-50 px-3 py-1.5 text-sm font-bold uppercase tracking-wide text-red-700'
            : 'se-pill items-start gap-1 border-amber-200 bg-amber-50 text-[10px] font-semibold leading-snug text-amber-900';
        $pillClass = $multilinea
            ? $pillBase.' flex w-full whitespace-normal'
            : $pillBase.' inline-flex max-w-full';
        $pillTextClass = $compacto && ! $multilinea ? 'min-w-0 truncate' : 'min-w-0';
        $pillHoverClass = $destacado
            ? 'transition-colors hover:border-red-400 hover:bg-red-100'
            : 'transition-colors hover:border-amber-300 hover:bg-amber-100';
        $iconClass = $destacado
            ? 'h-5 w-5 shrink-0 text-red-600'
            : 'h-3.5 w-3.5 shrink-0 text-amber-700';
    @endphp
    @if ($puedeIr)
        <x-nav-contexto-estudiante
            destino="seguimiento.tea"
            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_TEA"
            :matricula="$matricula"
            :curso="$curso"
            tag="a"
            {{ $attributes->class([$multilinea ? 'block w-full min-w-0' : ($compacto ? 'block min-w-0 max-w-full' : 'inline-block')]) }}>
            <span @class([$pillClass, $pillHoverClass])
                  @unless($multilinea) title="{{ $titulo }}" @endunless>
                <svg @class([$iconClass]) fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span @class([$pillTextClass])>{{ $texto }}</span>
            </span>
        </x-nav-contexto-estudiante>
    @else
        <span {{ $attributes->class([$pillClass, $multilinea ? 'block w-full min-w-0' : ($compacto ? 'block min-w-0 max-w-full' : '')]) }}
              @unless($multilinea) title="{{ $titulo }}" @endunless>
            <svg @class([$iconClass]) fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <span @class([$pillTextClass])>{{ $texto }}</span>
        </span>
    @endif
@endif
