{{--
    Dos controles separados: en celular, accept=jpeg/png oculta la cámara y deja solo Galería.
    «Tomar foto» usa capture + image/* (abre la cámara). «Galería» mantiene jpeg/png.
--}}
@php
    $prefijo = $inputIdPrefix ?? 'foto-carnet';
    $idCamara = $prefijo.'-camara';
    $idGaleria = $prefijo.'-galeria';
@endphp
<div class="mt-1.5 flex flex-wrap gap-2">
    <div class="relative inline-flex">
        <span class="btn-primary btn-sm pointer-events-none select-none gap-1.5" aria-hidden="true">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
            </svg>
            Tomar foto
        </span>
        <input id="{{ $idCamara }}"
               wire:key="{{ $idCamara }}"
               wire:model="fotoCarnetUpload"
               type="file"
               accept="image/*"
               capture="environment"
               x-on:click="$el.value = ''"
               class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
               aria-label="Tomar foto con la cámara"
               @error('fotoCarnetUpload') aria-invalid="true" @enderror>
    </div>
    <div class="relative inline-flex">
        <span class="btn-secondary btn-sm pointer-events-none select-none gap-1.5" aria-hidden="true">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/>
            </svg>
            Galería
        </span>
        <input id="{{ $idGaleria }}"
               wire:key="{{ $idGaleria }}"
               wire:model="fotoCarnetUpload"
               type="file"
               accept="image/jpeg,image/png"
               x-on:click="$el.value = ''"
               class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
               aria-label="Elegir foto de la galería"
               @error('fotoCarnetUpload') aria-invalid="true" @enderror>
    </div>
</div>
