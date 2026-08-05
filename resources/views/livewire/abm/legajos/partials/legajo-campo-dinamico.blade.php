{{-- Campo único del legajo (modo parametrizado, orden desde campos_legajo). --}}
@php
    $col = $campo['columna'];
    $label = $campo['etiqueta'] ?: \App\Support\Listados\ListadoCursoPdfFieldCatalog::legajoColumnLabel($col);
@endphp
@switch($col)
    @case('cuil')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="cuil" type="text" maxlength="13" placeholder="Ej: 20-12345678-9" class="form-input"></div>
        @break
    @case('fechnaci')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="fechnaci" type="date" class="form-input @error('fechnaci') border-red-400 @enderror">
            @error('fechnaci') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('sexo')
        @include('livewire.abm.legajos.partials.select-sexo', ['label' => $label, 'sexosOpciones' => $sexosOpciones ?? collect()])
        @break
    @case('nacion')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="nacion" type="text" maxlength="20" class="form-input"></div>
        @break
    @case('tipoalumno')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="tipoalumno" type="number" class="form-input"></div>
        @break
    @case('idFamilias')
        <div><label class="form-label">{{ $label }}</label>
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
        @break
    @case('legajo')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="legajo" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('libro')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="libro" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('folio')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="folio" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('pwrd')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="pwrd" type="text" maxlength="50" autocomplete="new-password"
                   class="form-input @error('pwrd') border-red-400 @enderror">
            @error('pwrd') <p class="form-error">{{ $message }}</p> @enderror
            @if($id ?? null)
                <p class="mt-1 text-xs text-neutral-500">Dejá vacío para mantener la contraseña actual.</p>
            @endif
        </div>
        @break
    @case('fotoCarnet')
        @include('livewire.abm.legajos.partials.foto-carnet-campo', ['label' => $label])
        @break
    @case('callenum')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="callenum" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('barrio')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="barrio" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('localidad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="localidad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('codpos')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="codpos" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('telefono')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="telefono" type="text" maxlength="60" class="form-input"></div>
        @break
    @case('email')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="email" type="text" maxlength="100" class="form-input @error('email') border-red-400 @enderror">
            @error('email') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('ln_ciudad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ln_ciudad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('ln_depto')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ln_depto" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('ln_provincia')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ln_provincia" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('ln_pais')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ln_pais" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('nombremad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="nombremad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('dnimad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="dnimad" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('fechnacmad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="fechnacmad" type="date" class="form-input"></div>
        @break
    @case('nacionmad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="nacionmad" type="text" maxlength="20" class="form-input"></div>
        @break
    @case('estacivimad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="estacivimad" type="text" maxlength="20" class="form-input"></div>
        @break
    @case('vivemad')
        <div><label class="form-label">{{ $label }}</label>
            <select wire:model="vivemad" class="form-select">
                <option value="">—</option>
                <option value="si">Sí</option>
                <option value="no">No</option>
            </select></div>
        @break
    @case('ocupacmad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ocupacmad" type="text" maxlength="30" class="form-input"></div>
        @break
    @case('domimad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="domimad" type="text" maxlength="100" class="form-input"></div>
        @break
    @case('telemad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="telemad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('telecelmad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="telecelmad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('emailmad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="emailmad" type="text" maxlength="50" class="form-input @error('emailmad') border-red-400 @enderror">
            @error('emailmad') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('nombrepad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="nombrepad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('dnipad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="dnipad" type="text" maxlength="10" class="form-input"></div>
        @break
    @case('fechnacpad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="fechnacpad" type="date" class="form-input"></div>
        @break
    @case('nacionpad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="nacionpad" type="text" maxlength="20" class="form-input"></div>
        @break
    @case('estacivipad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="estacivipad" type="text" maxlength="20" class="form-input"></div>
        @break
    @case('vivepad')
        <div><label class="form-label">{{ $label }}</label>
            <select wire:model="vivepad" class="form-select">
                <option value="">—</option>
                <option value="si">Sí</option>
                <option value="no">No</option>
            </select></div>
        @break
    @case('ocupacpad')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ocupacpad" type="text" maxlength="30" class="form-input"></div>
        @break
    @case('domipad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="domipad" type="text" maxlength="100" class="form-input"></div>
        @break
    @case('telepad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="telepad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('telecelpad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="telecelpad" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('emailpad')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="emailpad" type="text" maxlength="50" class="form-input @error('emailpad') border-red-400 @enderror">
            @error('emailpad') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('nombretut')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="nombretut" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('dnitut')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="dnitut" type="text" inputmode="numeric" class="form-input"></div>
        @break
    @case('teletut')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="teletut" type="text" maxlength="20" class="form-input"></div>
        @break
    @case('emailtut')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="emailtut" type="text" maxlength="50" class="form-input @error('emailtut') border-red-400 @enderror">
            @error('emailtut') <p class="form-error">{{ $message }}</p> @enderror</div>
        @break
    @case('respAdmiNom')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="respAdmiNom" type="text" maxlength="100" class="form-input"></div>
        @break
    @case('respAdmiDni')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="respAdmiDni" type="text" inputmode="numeric" class="form-input"></div>
        @break
    @case('escori')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="escori" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('destino')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="destino" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('parroquia')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="parroquia" type="text" maxlength="50" class="form-input"></div>
        @break
    @case('ec_padres')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="ec_padres" type="text" maxlength="30" class="form-input"></div>
        @break
    @case('vivecon')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="vivecon" type="text" maxlength="200" class="form-input"></div>
        @break
    @case('hermanos')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <textarea wire:model="hermanos" rows="2" class="form-input resize-y"></textarea></div>
        @break
    @case('needes')
        <div><label class="form-label">{{ $label }}</label>
            <select wire:model="needes" class="form-select">
                <option value="">No</option>
                <option value="si">Sí</option>
            </select></div>
        @break
    @case('needes_detalle')
        @if($needes === 'si')
            <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
                <textarea wire:model="needes_detalle" rows="2" class="form-input resize-y"></textarea></div>
        @endif
        @break
    @case('certDisc')
        <div><label class="form-label">{{ $label }}</label>
            <input wire:model="certDisc" type="text" maxlength="100" class="form-input"></div>
        @break
    @case('identif')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <input wire:model="identif" type="text" maxlength="100" class="form-input"></div>
        @break
    @case('retira')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <textarea wire:model="retira" rows="2" class="form-input resize-y"></textarea></div>
        @break
    @case('emeravis')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <textarea wire:model="emeravis" rows="2" class="form-input resize-y"></textarea></div>
        @break
    @case('obs')
        <div class="sm:col-span-2"><label class="form-label">{{ $label }}</label>
            <textarea wire:model="obs" rows="3" class="form-input resize-y"></textarea></div>
        @break
    @default
        <div class="sm:col-span-2">
            <label class="form-label">{{ $label }}</label>
            <input type="text" wire:model.live="legajoExtras.{{ $col }}" maxlength="4000"
                   class="form-input @error('legajoExtras.'.$col) border-red-400 @enderror">
            @error('legajoExtras.'.$col) <p class="form-error">{{ $message }}</p> @enderror
        </div>
        @break
@endswitch
