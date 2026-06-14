<div class="se-page" x-on:cooperadora-abrir-pdf.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nuevo egreso</h2>
            </div>
            <a href="{{ route('cooperadora.egresos') }}" class="btn-secondary shrink-0">Volver</a>
        </div>
    </section>

    <div class="se-card max-w-2xl">
        <form wire:submit="guardar" class="space-y-4 p-5 sm:p-6">
            <div>
                <label class="se-label">Proveedor</label>
                <select wire:model="idProveedor" class="se-input w-full">
                    <option value="">— Seleccione —</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                    @endforeach
                </select>
                @error('idProveedor') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="se-label">Fecha</label>
                    <input type="date" wire:model="fecha" class="se-input w-full">
                    @error('fecha') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="se-label">Importe</label>
                    <input type="number" step="0.01" wire:model="importe" class="se-input w-full" min="0.01">
                    @error('importe') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="se-label">Concepto</label>
                <textarea wire:model="concepto" rows="3" class="se-input w-full"></textarea>
                @error('concepto') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="se-label">Medio de pago</label>
                <select wire:model="idMedioPago" class="se-input w-full">
                    <option value="">— Seleccione —</option>
                    @foreach ($mediosPago as $medio)
                        <option value="{{ $medio->id }}">{{ $medio->nombre }}</option>
                    @endforeach
                </select>
                @error('idMedioPago') <p class="se-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="se-label">Aclaración / firmante</label>
                <input type="text" wire:model="firmante" class="se-input w-full" maxlength="120">
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('cooperadora.egresos') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Registrar y emitir orden</button>
            </div>
        </form>
    </div>

    @script
    <script>
        $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
    </script>
    @endscript
</div>
