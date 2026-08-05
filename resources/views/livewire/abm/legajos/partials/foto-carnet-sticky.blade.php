{{-- Preview sticky de foto carnet (siempre visible al editar si hay foto o el campo está activo). --}}
<aside class="w-full shrink-0 border-t border-accent-200 bg-accent-50/40 px-5 py-5 lg:sticky lg:top-4 lg:w-52 lg:self-start lg:border-l lg:border-t-0 sm:px-6"
       wire:key="foto-carnet-sticky"
       x-on:legajo-foto-carnet-cleared.window="revokeLocalFotoPreview()">
    <p class="mb-3 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{{ $etiquetaFotoCarnet ?? 'Foto carnet' }}</p>
    <div class="mx-auto flex aspect-[3/4] w-36 max-w-full items-center justify-center overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-sm lg:w-full">
        <img x-show="localFotoPreview && ! $wire.removeFotoCarnet"
             x-cloak
             :src="localFotoPreview"
             alt="Vista previa foto"
             class="h-full w-full object-cover">
        <img x-show="! localFotoPreview && ! $wire.removeFotoCarnet && @js((bool) $fotoCarnetUrl)"
             x-cloak
             src="{{ $fotoCarnetUrl }}"
             alt="Foto carnet"
             class="h-full w-full object-cover"
             x-on:error="$el.classList.add('hidden'); $refs.fotoBroken?.classList.remove('hidden')">
        <div x-ref="fotoBroken"
             class="hidden flex flex-col items-center gap-2 px-3 text-center text-amber-700">
            <span class="text-xs leading-snug">No se pudo mostrar la foto. Probá recargar o volver a subirla.</span>
        </div>
        <div x-show="(! localFotoPreview && ($wire.removeFotoCarnet || @js(! $fotoCarnetUrl))) || $wire.removeFotoCarnet"
             class="flex flex-col items-center gap-2 px-3 text-center text-neutral-400">
            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
            <span class="text-xs">{{ $removeFotoCarnet ? 'Se quitará al guardar' : 'Sin foto' }}</span>
        </div>
    </div>

    @if ($puedeEditar ?? false)
        <div class="mt-3 space-y-2">
            @if ($removeFotoCarnet && trim((string) $fotoCarnetPath) !== '')
                <button type="button"
                        wire:click="deshacerQuitarFotoCarnet"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                    Conservar foto
                </button>
                <p class="text-center text-[11px] text-neutral-500">Confirmá con Guardar legajo.</p>
            @elseif ($fotoCarnetUrl || trim((string) $fotoCarnetPath) !== '' || $fotoCarnetUpload)
                <button type="button"
                        wire:click="marcarQuitarFotoCarnet"
                        x-on:click="revokeLocalFotoPreview()"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-50">
                    Quitar foto
                </button>
            @endif
        </div>
    @endif
</aside>
