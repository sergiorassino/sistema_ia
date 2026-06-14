<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Configuración</h2>
                <p class="text-sm text-white/80">Datos institucionales para recibos y órdenes de pago, y descuento por hermanos.</p>
            </div>
        </div>
    </section>

    <div class="se-card max-w-3xl">
        <form wire:submit="guardar" class="space-y-5 p-5 sm:p-6">
            <div>
                <label class="se-label">Nombre institucional</label>
                <input type="text" wire:model="nombreInstitucion" class="se-input w-full" maxlength="200">
                @error('nombreInstitucion') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="se-label">Dirección</label>
                <input type="text" wire:model="direccion" class="se-input w-full" maxlength="200">
                @error('direccion') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="se-label">Localidad</label>
                    <input type="text" wire:model="localidad" class="se-input w-full" maxlength="120">
                    @error('localidad') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="se-label">Teléfono</label>
                    <input type="text" wire:model="telefono" class="se-input w-full" maxlength="80">
                    @error('telefono') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="max-w-xs">
                <label class="se-label">Descuento hermanos (%)</label>
                <input type="number" step="0.01" min="0" max="100" wire:model="descuentoHermanoPct" class="se-input w-full">
                <p class="mt-1 text-xs text-neutral-500">Se aplica cuando hay dos o más hermanos matriculados en el ciclo activo.</p>
                @error('descuentoHermanoPct') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
