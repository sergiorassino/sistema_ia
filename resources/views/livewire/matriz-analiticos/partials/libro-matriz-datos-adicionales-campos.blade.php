{{-- Campos de analiticodatos — layout horizontal (etiqueta a la derecha). --}}
@php
    $idPrefijo = $idPrefijo ?? '';
@endphp
<div class="se-analitico-datos-form space-y-3">
    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}analCohorte" class="se-analitico-datos-label">Cohorte</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}analCohorte"
                   type="text"
                   wire:model="analCohorte"
                   maxlength="30"
                   class="se-analitico-datos-input se-analitico-datos-input--corto @error('analCohorte') border-red-400 @enderror">
            @error('analCohorte') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}analObservaciones" class="se-analitico-datos-label">Observaciones</label>
        <div class="se-analitico-datos-field">
            <textarea id="{{ $idPrefijo }}analObservaciones"
                      wire:model="analObservaciones"
                      rows="4"
                      class="se-analitico-datos-input se-analitico-datos-textarea @error('analObservaciones') border-red-400 @enderror"></textarea>
            @error('analObservaciones') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <div class="se-analitico-datos-label-wrap">
            <label for="{{ $idPrefijo }}analParaCompletar" class="se-analitico-datos-label">Leyenda (Para completar…)</label>
        </div>
        <div class="se-analitico-datos-field">
            <textarea id="{{ $idPrefijo }}analParaCompletar"
                      wire:model="analParaCompletar"
                      rows="4"
                      class="se-analitico-datos-input se-analitico-datos-textarea @error('analParaCompletar') border-red-400 @enderror"></textarea>
            @error('analParaCompletar') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}analValidez" class="se-analitico-datos-label">Validez</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}analValidez"
                   type="text"
                   wire:model="analValidez"
                   maxlength="50"
                   class="se-analitico-datos-input @error('analValidez') border-red-400 @enderror">
            @error('analValidez') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}serie" class="se-analitico-datos-label">Serie</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}serie"
                   type="text"
                   wire:model="serie"
                   maxlength="6"
                   class="se-analitico-datos-input se-analitico-datos-input--corto @error('serie') border-red-400 @enderror">
            @error('serie') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}numero" class="se-analitico-datos-label">Número</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}numero"
                   type="text"
                   wire:model="numero"
                   maxlength="20"
                   class="se-analitico-datos-input se-analitico-datos-input--corto @error('numero') border-red-400 @enderror">
            @error('numero') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}analLibroFolio" class="se-analitico-datos-label">Libro Folio</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}analLibroFolio"
                   type="text"
                   wire:model="analLibroFolio"
                   maxlength="50"
                   class="se-analitico-datos-input @error('analLibroFolio') border-red-400 @enderror">
            @error('analLibroFolio') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}analFechaEmision" class="se-analitico-datos-label">Fecha Emision</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}analFechaEmision"
                   type="date"
                   wire:model="analFechaEmision"
                   class="se-analitico-datos-input se-analitico-datos-input--fecha @error('analFechaEmision') border-red-400 @enderror">
            <p class="se-analitico-datos-hint">DD/MM/AAAA</p>
            @error('analFechaEmision') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="se-analitico-datos-row">
        <label for="{{ $idPrefijo }}analParaPre" class="se-analitico-datos-label">Para presentar a:</label>
        <div class="se-analitico-datos-field">
            <input id="{{ $idPrefijo }}analParaPre"
                   type="text"
                   wire:model="analParaPre"
                   maxlength="200"
                   class="se-analitico-datos-input @error('analParaPre') border-red-400 @enderror">
            @error('analParaPre') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
