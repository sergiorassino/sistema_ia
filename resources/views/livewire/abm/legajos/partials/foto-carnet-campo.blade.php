{{-- Control de subida de foto carnet (en la solapa donde esté el campo). --}}
@php
    $label = $label ?? ($etiquetaFotoCarnet ?? 'Foto carnet');
@endphp
<div class="sm:col-span-2 space-y-3"
     wire:key="foto-carnet-upload"
     x-on:change="
        const input = $event.target;
        if (input && input.type === 'file') {
            revokeLocalFotoPreview();
            const file = input.files?.[0];
            if (file && /^image\/(jpe?g|png)$/i.test(file.type)) {
                localFotoPreview = URL.createObjectURL(file);
                $wire.set('removeFotoCarnet', false);
            }
        }
     ">
    <div>
        <label class="form-label">{{ $label }}</label>
        @if ($puedeEditar ?? false)
            <input wire:model="fotoCarnetUpload" type="file" accept="image/jpeg,image/png"
                   class="form-input mt-1.5 @error('fotoCarnetUpload') border-red-400 @enderror"
                   x-on:livewire-upload-error.window="
                       if ($event.detail?.property === 'fotoCarnetUpload') {
                           revokeLocalFotoPreview();
                           $wire.onFotoCarnetUploadFailed();
                       }
                   ">
            <p wire:loading wire:target="fotoCarnetUpload" class="mt-1 text-xs font-medium text-primary-700">
                Subiendo archivo… espere a que termine antes de pulsar Guardar.
            </p>
            @error('fotoCarnetUpload') <p class="form-error">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-neutral-500">JPG/PNG · máx. 2&nbsp;MB. Para quitarla usá el botón del panel de la foto y Guardar.</p>
        @else
            <p class="mt-1 text-sm text-neutral-500">Solo consulta.</p>
        @endif
    </div>
</div>
