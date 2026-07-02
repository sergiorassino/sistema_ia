@php
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
    $modalWireKey = $modalWireKey ?? 'modal-notas-off-carga';
    $modalTituloId = $modalTituloId ?? 'modal-notas-off-titulo';
@endphp

@if ($mostrarModalNotasOff)
    @teleport('body')
    <div class="fixed inset-0 z-[1100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="{{ $modalTituloId }}"
         wire:key="{{ $modalWireKey }}">
        <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
             wire:click="aceptarAvisoCargaNotasOff"
             aria-hidden="true"></div>

        <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]"
             @click.stop>
            <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                <h3 id="{{ $modalTituloId }}" class="text-base font-bold text-neutral-900">Carga de calificaciones</h3>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                <p class="text-sm text-neutral-700 leading-relaxed whitespace-pre-line">{{ $mensajeNotasOff }}</p>
                <p class="mt-3 text-xs text-neutral-500">
                    Podrá consultar las calificaciones en modo solo lectura.
                </p>
            </div>
            <div class="shrink-0 flex justify-end border-t border-accent-200 bg-accent-50 px-5 py-3">
                <button type="button"
                        wire:click="aceptarAvisoCargaNotasOff"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Aceptar
                </button>
            </div>
        </div>
    </div>
    @endteleport
@endif
