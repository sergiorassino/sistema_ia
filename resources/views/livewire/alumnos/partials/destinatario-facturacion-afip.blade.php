<section class="se-card p-4 sm:p-5" aria-labelledby="seccion-facturacion-afip">
    <p id="seccion-facturacion-afip" class="se-section-title mb-4">Destinatario de facturación ARCA</p>
    <p class="mb-4 text-xs text-neutral-500">
        Persona a cuyo nombre se emitirán las facturas. Ambos campos son obligatorios.
    </p>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label class="form-label" for="campo-respAdmiNom">Apellidos y nombres *</label>
            <input id="campo-respAdmiNom"
                   wire:model.live.blur="respAdmiNom"
                   type="text"
                   maxlength="100"
                   class="form-input mt-1"
                   @disabled($bloqueado)>
            @error('respAdmiNom') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="form-label" for="campo-respAdmiDni">DNI *</label>
            <input id="campo-respAdmiDni"
                   wire:model.live.blur="respAdmiDni"
                   type="text"
                   inputmode="numeric"
                   maxlength="11"
                   class="form-input mt-1 tabular-nums"
                   @disabled($bloqueado)>
            @error('respAdmiDni') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
