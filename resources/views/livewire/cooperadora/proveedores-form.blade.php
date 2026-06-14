<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $id ? 'Editar proveedor' : 'Nuevo proveedor' }}</h2>
            </div>
            <a href="{{ route('cooperadora.proveedores') }}" class="btn-secondary shrink-0">Volver</a>
        </div>
    </section>

    <div class="se-card max-w-2xl">
        <form wire:submit="guardar" class="space-y-4 p-5 sm:p-6">
            <div>
                <label class="se-label">Nombre</label>
                <input type="text" wire:model="nombre" class="se-input w-full" maxlength="200">
                @error('nombre') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="se-label">CUIT</label>
                    <input type="text" wire:model="cuit" class="se-input w-full">
                </div>
                <div>
                    <label class="se-label">Teléfono</label>
                    <input type="text" wire:model="telefono" class="se-input w-full">
                </div>
            </div>
            <div>
                <label class="se-label">Email</label>
                <input type="email" wire:model="email" class="se-input w-full">
                @error('email') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="se-label">Dirección</label>
                <input type="text" wire:model="direccion" class="se-input w-full">
            </div>
            <div>
                <label class="se-label">Observaciones</label>
                <textarea wire:model="observaciones" rows="3" class="se-input w-full"></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="activo" class="rounded border-accent-300 text-primary-600"> Activo
            </label>
            <div class="flex justify-end gap-2">
                <a href="{{ route('cooperadora.proveedores') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
