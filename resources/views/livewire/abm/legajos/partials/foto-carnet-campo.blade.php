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
            if (file && (! file.type || /^image\//i.test(file.type))) {
                localFotoPreview = URL.createObjectURL(file);
                fotoSubiendo = true;
                $wire.set('removeFotoCarnet', false);
            }
        }
     ">
    <div>
        <label class="form-label">{{ $label }}</label>
        @if ($puedeEditar ?? false)
            @include('livewire.abm.legajos.partials.foto-carnet-upload-controles', ['inputIdPrefix' => 'foto-carnet-abm'])
            <p wire:loading wire:target="fotoCarnetUpload" class="mt-1 text-xs font-medium text-primary-700">
                Subiendo archivo… espere a que termine antes de pulsar Guardar.
            </p>
            <p x-show="fotoSubiendo" x-cloak class="mt-1 text-xs font-medium text-primary-700">
                Subiendo archivo… el botón Guardar permanecerá bloqueado hasta que termine.
            </p>
            @error('fotoCarnetUpload') <p class="form-error">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-neutral-500">En el celular, <span class="font-semibold text-neutral-600">Tomar foto</span> abre la cámara. JPG/PNG · máx. 8&nbsp;MB al subir (se comprime al guardar). El tamaño en pantalla no cambia.</p>
        @else
            <p class="mt-1 text-sm text-neutral-500">Solo consulta.</p>
        @endif
    </div>
</div>
