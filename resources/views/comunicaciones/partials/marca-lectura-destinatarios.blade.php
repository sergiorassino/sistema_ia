@props([
    'lectura',
    /** clara: burbuja enviada (fondo claro) · oscura: burbuja recibida (fondo primary) */
    'variante' => 'clara',
    'idMensaje' => null,
])

@if (($lectura['total'] ?? 0) > 0)
    @php
        $clasesMarca = [
            'inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide',
            'rounded-md px-1 py-0.5 transition',
            'text-primary-700 hover:bg-primary-50' => $variante === 'clara' && $lectura['estado'] === 'leido',
            'text-amber-800 hover:bg-amber-50' => $variante === 'clara' && $lectura['estado'] === 'parcial',
            'text-neutral-500 hover:bg-neutral-100' => $variante === 'clara' && $lectura['estado'] === 'no_leido',
            'text-white/95 hover:bg-white/15' => $variante === 'oscura' && $lectura['estado'] === 'leido',
            'text-amber-200 hover:bg-white/10' => $variante === 'oscura' && $lectura['estado'] === 'parcial',
            'text-white/70 hover:bg-white/10' => $variante === 'oscura' && $lectura['estado'] === 'no_leido',
        ];
        if ($idMensaje) {
            $clasesMarca['cursor-pointer'] = true;
        }
    @endphp

    @if ($idMensaje)
        <button type="button"
                wire:click="abrirDetalleLectura({{ (int) $idMensaje }})"
                wire:loading.attr="disabled"
                wire:target="abrirDetalleLectura"
                title="{{ $lectura['titulo'] }}"
                @class($clasesMarca)>
            @include('comunicaciones::partials.marca-lectura-destinatarios-icono', ['estado' => $lectura['estado']])
            <span wire:loading.remove wire:target="abrirDetalleLectura">{{ $lectura['etiqueta'] }}</span>
            <span wire:loading wire:target="abrirDetalleLectura">…</span>
        </button>
    @else
        <span title="{{ $lectura['titulo'] }}" @class($clasesMarca)>
            @include('comunicaciones::partials.marca-lectura-destinatarios-icono', ['estado' => $lectura['estado']])
            <span>{{ $lectura['etiqueta'] }}</span>
        </span>
    @endif
@endif
