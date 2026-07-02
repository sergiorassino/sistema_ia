{{--
    Celda de nota primario: input (teclado) + desplegable compacto (notas permitidas según escala de la materia).
    El guardado sigue vía focusout en el input (app.js).
--}}
@props([
    'id',
    'value' => '',
    'wireKey' => null,
    'inputClass' => 'rounded border border-accent-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
    'notasPermitidasActiva' => false,
    'notasPermitidasLista' => [],
    'incluirOpcionVacia' => true,
    'soloLectura' => false,
])

@php
    $valor = trim((string) $value);
@endphp

@if ($notasPermitidasActiva && $notasPermitidasLista !== [])
    <div class="se-calif-prim-nota-combo flex min-w-0 items-stretch">
        <input type="text"
               id="{{ $id }}"
               maxlength="15"
               autocomplete="off"
               value="{{ $valor }}"
               data-se-calif-prim-allowed='@json($notasPermitidasLista)'
               @readonly($soloLectura)
               @if ($wireKey) wire:key="{{ $wireKey }}" @endif
               @class([
                   'se-calif-prim-nota-input min-w-0 flex-1',
                   $inputClass,
                   'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
               ]) />
        @if (! $soloLectura)
        <div class="se-calif-prim-nota-picker shrink-0"
             data-se-calif-prim-nota-picker-for="{{ $id }}">
            <button type="button"
                    class="se-calif-prim-nota-picker-btn"
                    aria-label="Elegir nota"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    title="Elegir nota (Enter o flecha abajo)">
                <svg class="se-calif-prim-nota-picker-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="se-calif-prim-nota-picker-menu hidden"
                 role="listbox"
                 aria-label="Notas permitidas"
                 data-se-calif-prim-nota-menu-for="{{ $id }}"
                 hidden>
                @if ($incluirOpcionVacia)
                    <button type="button"
                            role="option"
                            class="se-calif-prim-nota-picker-option se-calif-prim-nota-picker-option--vacia @if($valor === '') is-selected @endif"
                            aria-selected="{{ $valor === '' ? 'true' : 'false' }}"
                            data-nota="">(vacío)</button>
                @endif
                @foreach ($notasPermitidasLista as $nota)
                    <button type="button"
                            role="option"
                            class="se-calif-prim-nota-picker-option @if($valor === $nota) is-selected @endif"
                            aria-selected="{{ $valor === $nota ? 'true' : 'false' }}"
                            data-nota="{{ $nota }}">{{ $nota }}</button>
                @endforeach
            </div>
        </div>
        @endif
    </div>
@else
    <input type="text"
           id="{{ $id }}"
           maxlength="15"
           autocomplete="off"
           value="{{ $valor }}"
           @readonly($soloLectura)
           @if ($wireKey) wire:key="{{ $wireKey }}" @endif
           @class([
               $inputClass,
               'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
           ]) />
@endif
