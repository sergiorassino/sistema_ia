{{-- Foto carnet en actualización de datos (tenant foto_carnet + solapa del legajo). --}}
@if ($fotoCarnetHabilitada ?? false)
    @php
        $etiqueta = $etiquetaFotoCarnet ?? 'Foto carnet';
    @endphp
    <section class="se-card mb-4 p-4 sm:p-5"
             aria-label="{{ $etiqueta }}"
             wire:key="act-datos-foto-carnet"
             x-data="{
                 localFotoPreview: null,
                 fotoSubiendo: false,
                 revokeLocalFotoPreview() {
                     if (this.localFotoPreview) {
                         URL.revokeObjectURL(this.localFotoPreview);
                         this.localFotoPreview = null;
                     }
                 },
                 esUploadFotoCarnet(detail) {
                     const prop = detail?.propertyName ?? detail?.name ?? '';
                     return prop === 'fotoCarnetUpload';
                 }
             }"
             x-on:act-datos-foto-carnet-cleared.window="revokeLocalFotoPreview()"
             x-on:livewire-upload-start.window="if (esUploadFotoCarnet($event.detail)) fotoSubiendo = true"
             x-on:livewire-upload-finish.window="if (esUploadFotoCarnet($event.detail)) fotoSubiendo = false"
             x-on:livewire-upload-cancel.window="if (esUploadFotoCarnet($event.detail)) { fotoSubiendo = false; revokeLocalFotoPreview(); }"
             x-on:livewire-upload-error.window="if (esUploadFotoCarnet($event.detail)) { fotoSubiendo = false; revokeLocalFotoPreview(); $wire.onFotoCarnetUploadFailed(); }">
        <p class="se-section-title mb-4">{{ $etiqueta }}</p>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="se-legajo-foto-sticky se-legajo-foto-sticky--inline shrink-0">
                <div class="se-legajo-foto-sticky__frame">
                    <img x-show="localFotoPreview && ! $wire.removeFotoCarnet"
                         x-cloak
                         :src="localFotoPreview"
                         alt="Vista previa foto"
                         class="se-legajo-foto-sticky__img">
                    <img x-show="! localFotoPreview && ! $wire.removeFotoCarnet && @js((bool) $fotoCarnetUrl)"
                         x-cloak
                         src="{{ $fotoCarnetUrl }}"
                         alt="{{ $etiqueta }}"
                         class="se-legajo-foto-sticky__img">
                    <div x-show="(! localFotoPreview && ($wire.removeFotoCarnet || @js(! $fotoCarnetUrl))) || $wire.removeFotoCarnet"
                         class="se-legajo-foto-sticky__empty">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span>{{ $removeFotoCarnet ? 'Se quitará al guardar' : 'Sin foto' }}</span>
                    </div>
                </div>
                @unless ($bloqueado)
                    <div class="se-legajo-foto-sticky__actions">
                        @if ($removeFotoCarnet && trim((string) $fotoCarnetPath) !== '')
                            <button type="button"
                                    wire:click="deshacerQuitarFotoCarnet"
                                    class="se-legajo-foto-sticky__btn se-legajo-foto-sticky__btn--keep">
                                Conservar foto
                            </button>
                        @elseif ($fotoCarnetUrl || trim((string) $fotoCarnetPath) !== '' || $fotoCarnetUpload)
                            <button type="button"
                                    wire:click="marcarQuitarFotoCarnet"
                                    x-on:click="revokeLocalFotoPreview()"
                                    class="se-legajo-foto-sticky__btn se-legajo-foto-sticky__btn--remove">
                                Quitar foto
                            </button>
                        @endif
                    </div>
                @endunless
            </div>

            <div class="min-w-0 flex-1 space-y-2"
                 x-on:change="
                    const input = $event.target;
                    if (input && input.type === 'file') {
                        revokeLocalFotoPreview();
                        const file = input.files?.[0];
                        if (file && /^image\/(jpe?g|png)$/i.test(file.type)) {
                            localFotoPreview = URL.createObjectURL(file);
                            fotoSubiendo = true;
                            $wire.set('removeFotoCarnet', false);
                        }
                    }
                 ">
                <label class="form-label" for="campo-fotoCarnetUpload">{{ $etiqueta }}</label>
                @unless ($bloqueado)
                    <input id="campo-fotoCarnetUpload"
                           wire:model="fotoCarnetUpload"
                           type="file"
                           accept="image/jpeg,image/png"
                           class="form-input mt-1.5 @error('fotoCarnetUpload') border-red-400 @enderror">
                    <p wire:loading wire:target="fotoCarnetUpload" class="mt-1 text-xs font-medium text-primary-700">
                        Subiendo archivo… espere a que termine antes de pulsar Guardar.
                    </p>
                    <p x-show="fotoSubiendo" x-cloak class="mt-1 text-xs font-medium text-primary-700">
                        Subiendo archivo… el botón Guardar permanecerá bloqueado hasta que termine.
                    </p>
                    @error('fotoCarnetUpload') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-neutral-500">JPG/PNG · máx. 2&nbsp;MB al subir (se comprime al guardar).</p>
                @else
                    <p class="mt-1 text-sm text-neutral-500">Solo consulta.</p>
                @endunless
            </div>
        </div>
    </section>
@endif
