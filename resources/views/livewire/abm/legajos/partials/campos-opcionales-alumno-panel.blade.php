{{--
    Campos de la plantilla «alumno» reutilizables en solapa Alumno u otras (p. ej. Otros).
    N° Legajo, Libro y Folio comparten la misma grilla para misma estética.
--}}

@if($showFieldEnTab('cuil'))
<div>
    <label class="form-label">CUIL</label>
    <input wire:model="cuil" type="text" maxlength="13" placeholder="Ej: 20-12345678-9" class="form-input">
</div>
@endif

@if($showFieldEnTab('fechnaci'))
<div>
    <label class="form-label">Fecha de nacimiento</label>
    <input wire:model="fechnaci" type="date" class="form-input @error('fechnaci') border-red-400 @enderror">
    @error('fechnaci') <p class="form-error">{{ $message }}</p> @enderror
</div>
@endif

@if($showFieldEnTab('sexo'))
    @include('livewire.abm.legajos.partials.select-sexo', ['sexosOpciones' => $sexosOpciones ?? collect()])
@endif

@if($showFieldEnTab('nacion'))
<div>
    <label class="form-label">Nacionalidad</label>
    <input wire:model="nacion" type="text" maxlength="20" class="form-input">
</div>
@endif

@if($showFieldEnTab('idFamilias'))
<div>
    <label class="form-label">Familia</label>
    <select wire:model="idFamilias"
            @disabled(! ($puedeGestionarFamilias ?? false))
            class="form-select @error('idFamilias') border-red-400 @enderror @if(! ($puedeGestionarFamilias ?? false)) bg-accent-50 text-neutral-600 @endif">
        @foreach ($familias as $f)
            <option value="{{ $f->id }}">{{ $f->apellido }}{{ $f->responsable ? ' – ' . $f->responsable : '' }}</option>
        @endforeach
    </select>
    @error('idFamilias') <p class="form-error">{{ $message }}</p> @enderror
    @if (! ($puedeGestionarFamilias ?? false))
        <p class="mt-1 text-xs text-neutral-500">Solo consulta. Para crear, editar o asignar familias use la solapa Familia (con permiso de gestión).</p>
    @endif
</div>
@endif

@if($showFieldEnTab('pwrd'))
<div>
    <label class="form-label">Contraseña (autogestión)</label>
    <input wire:model="pwrd" type="text" maxlength="50" autocomplete="new-password"
           class="form-input @error('pwrd') border-red-400 @enderror">
    @error('pwrd') <p class="form-error">{{ $message }}</p> @enderror
    @if($id ?? null)
        <p class="mt-1 text-xs text-neutral-500">Dejá vacío para mantener la contraseña actual.</p>
    @endif
</div>
@endif

@if($showFieldEnTab('fotoCarnet'))
    @include('livewire.abm.legajos.partials.foto-carnet-campo', ['label' => $etiquetaFotoCarnet ?? 'Foto carnet'])
@endif

@if($showFieldEnTab('legajo') || $showFieldEnTab('libro') || $showFieldEnTab('folio'))
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:col-span-2">
    @if($showFieldEnTab('legajo'))
    <div>
        <label class="form-label">N° Legajo</label>
        <input wire:model="legajo" type="text" maxlength="10" class="form-input">
    </div>
    @endif
    @if($showFieldEnTab('libro'))
    <div>
        <label class="form-label">Libro</label>
        <input wire:model="libro" type="text" maxlength="10" class="form-input">
    </div>
    @endif
    @if($showFieldEnTab('folio'))
    <div>
        <label class="form-label">Folio</label>
        <input wire:model="folio" type="text" maxlength="10" class="form-input">
    </div>
    @endif
</div>
@endif
