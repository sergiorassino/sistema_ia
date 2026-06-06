@php
    $col = $campo['columna'];
    $label = $campo['etiqueta'] ?: \App\Support\ProfesorLegajoFieldCatalog::label($col);
@endphp
@switch($col)
    @case('cuil')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="cuil" type="text" maxlength="13" class="form-input"></div>
        @break
    @case('sexo')
        @include('livewire.abm.legajos.partials.select-sexo', ['label' => $label, 'sexosOpciones' => $sexosOpciones ?? collect()])
        @break
    @case('email')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="email" type="email" maxlength="100" class="form-input @error('email') border-red-400 @enderror">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('emailInsti')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="emailInsti" type="email" maxlength="100" class="form-input @error('emailInsti') border-red-400 @enderror">
            @error('emailInsti') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('callenum')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="callenum" type="text" maxlength="200" class="form-input"></div>
        @break
    @case('barrio')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="barrio" type="text" maxlength="100" class="form-input"></div>
        @break
    @case('telefono')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="telefono" type="text" maxlength="30" class="form-input"></div>
        @break
    @case('celular')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="celular" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('nacion')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="nacion" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('estacivi')
        @include('livewire.abm.legajos-profesor.partials.select-estado-civil', ['label' => $label, 'estadosCivilesOpciones' => $estadosCivilesOpciones ?? collect()])
        @break
    @case('legJunta')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="legJunta" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('legEscuela')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="legEscuela" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('fechnaci')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="fechnaci" type="date" class="form-input @error('fechnaci') border-red-400 @enderror">
            @error('fechnaci') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('titulo')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="titulo" type="text" maxlength="250" class="form-input"></div>
        @break
    @case('numreg')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="numreg" type="text" maxlength="30" class="form-input"></div>
        @break
    @case('apto')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="apto" type="date" class="form-input @error('apto') border-red-400 @enderror">
            @error('apto') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('incapac')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="incapac" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('escalafonD')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="escalafonD" type="date" class="form-input @error('escalafonD') border-red-400 @enderror">
            @error('escalafonD') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('escalafonE')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="escalafonE" type="date" class="form-input @error('escalafonE') border-red-400 @enderror">
            @error('escalafonE') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('cargo')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="cargo" type="text" maxlength="30" class="form-input"></div>
        @break
    @case('obs')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <textarea wire:model="obs" rows="3" class="form-input resize-y"></textarea></div>
        @break
    @default
        <div class="sm:col-span-2">
            <label class="form-label">{{ $label }}</label>
            <input type="text" wire:model.live="profesorExtras.{{ $col }}" maxlength="4000" class="form-input">
        </div>
        @break
@endswitch
