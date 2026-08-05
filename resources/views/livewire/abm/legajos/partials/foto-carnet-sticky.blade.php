{{-- Preview sticky de foto carnet (siempre visible al editar si hay foto o el campo está activo). --}}
<aside class="se-legajo-foto-sticky"
       wire:key="foto-carnet-sticky"
       x-on:legajo-foto-carnet-cleared.window="revokeLocalFotoPreview()">
    <p class="se-legajo-foto-sticky__label">{{ $etiquetaFotoCarnet ?? 'Foto carnet' }}</p>
    <div class="se-legajo-foto-sticky__frame">
        <img x-show="localFotoPreview && ! $wire.removeFotoCarnet"
             x-cloak
             :src="localFotoPreview"
             alt="Vista previa foto"
             class="se-legajo-foto-sticky__img">
        <img x-show="! localFotoPreview && ! $wire.removeFotoCarnet && @js((bool) $fotoCarnetUrl)"
             x-cloak
             src="{{ $fotoCarnetUrl }}"
             alt="Foto carnet"
             class="se-legajo-foto-sticky__img"
             x-on:error="$el.classList.add('hidden'); $refs.fotoBroken?.classList.remove('hidden')">
        <div x-ref="fotoBroken"
             class="hidden se-legajo-foto-sticky__empty se-legajo-foto-sticky__empty--warn">
            <span>No se pudo mostrar la foto. Probá recargar o volver a subirla.</span>
        </div>
        <div x-show="(! localFotoPreview && ($wire.removeFotoCarnet || @js(! $fotoCarnetUrl))) || $wire.removeFotoCarnet"
             class="se-legajo-foto-sticky__empty">
            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
            <span>{{ $removeFotoCarnet ? 'Se quitará al guardar' : 'Sin foto' }}</span>
        </div>
    </div>

    @if ($puedeEditar ?? false)
        <div class="se-legajo-foto-sticky__actions">
            @if ($removeFotoCarnet && trim((string) $fotoCarnetPath) !== '')
                <button type="button"
                        wire:click="deshacerQuitarFotoCarnet"
                        class="se-legajo-foto-sticky__btn se-legajo-foto-sticky__btn--keep">
                    Conservar foto
                </button>
                <p class="se-legajo-foto-sticky__hint">Confirmá con Guardar legajo.</p>
            @elseif ($fotoCarnetUrl || trim((string) $fotoCarnetPath) !== '' || $fotoCarnetUpload)
                <button type="button"
                        wire:click="marcarQuitarFotoCarnet"
                        x-on:click="revokeLocalFotoPreview()"
                        class="se-legajo-foto-sticky__btn se-legajo-foto-sticky__btn--remove">
                    Quitar foto
                </button>
            @endif
        </div>
    @endif
</aside>
