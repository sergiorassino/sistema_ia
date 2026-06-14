<div class="se-page" x-on:cooperadora-abrir-pdf.window="window.open($event.detail.url, '_blank', 'noopener,noreferrer')">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Cooperadora</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Nuevo ingreso</h2>
                <p class="text-sm text-white/80">
                    @if ($modo === 'por_alumno') Cuota social o matrícula por alumno
                    @elseif ($modo === 'uniforme') Venta de uniforme asociada al alumno
                    @else Ingreso eventual (donación, canon cantina, etc.)
                    @endif
                </p>
            </div>
            <a href="{{ route('cooperadora.ingresos') }}" class="btn-secondary shrink-0">Volver</a>
        </div>
    </section>

    <div class="se-card max-w-3xl">
        <form wire:submit="guardar" class="space-y-5 p-5 sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="se-label">Rubro</label>
                    <select wire:model.live="idRubro" class="se-input w-full">
                        <option value="">— Seleccione —</option>
                        @foreach ($rubros as $rubro)
                            <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                        @endforeach
                    </select>
                    @error('idRubro') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="se-label">Ítem</label>
                    <select wire:model.live="idItem" class="se-input w-full" @disabled($items->isEmpty())>
                        <option value="">— Seleccione —</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->nombre }} — ${{ number_format((float) $item->precio, 2, ',', '.') }}</option>
                        @endforeach
                    </select>
                    @error('idItem') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            @if (in_array($modo, ['por_alumno', 'uniforme'], true))
                <div>
                    <label class="se-label">Buscar alumno</label>
                    <input type="search" wire:model.live.debounce.400ms="search" class="se-input w-full" placeholder="Apellido, nombre, DNI o legajo">
                    @if ($legajos && $legajos->isNotEmpty() && ! $idLegajo)
                        <ul class="mt-2 divide-y divide-accent-200 rounded-xl border border-accent-200 bg-white">
                            @foreach ($legajos as $leg)
                                <li class="flex items-center justify-between gap-2 px-3 py-2">
                                    <span class="text-sm">{{ $leg->apellido }}, {{ $leg->nombre }} <span class="text-neutral-500">DNI {{ $leg->dni }}</span></span>
                                    <button type="button" wire:click="seleccionarLegajo({{ $leg->id }})" class="btn-secondary btn-sm">Seleccionar</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @error('idLegajo') <p class="se-field-error">{{ $message }}</p> @enderror
                    @if ($legajoSel)
                        <p class="mt-2 text-sm text-primary-700">Alumno: <strong>{{ $legajoSel->apellido }}, {{ $legajoSel->nombre }}</strong>
                            @if ($etiquetaCurso) · Curso: {{ $etiquetaCurso }} @endif
                        </p>
                    @endif
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="se-label">Señor / pagador</label>
                    <input type="text" wire:model="pagadorNombre" class="se-input w-full">
                    @error('pagadorNombre') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="se-label">Fecha</label>
                    <input type="date" wire:model="fecha" class="se-input w-full">
                    @error('fecha') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="se-label">Importe bruto</label>
                    <input type="number" step="0.01" wire:model.live="importeBruto" wire:change="recalcularImporte" class="se-input w-full" min="0">
                </div>
                <div>
                    <label class="se-label">Descuento hermanos %</label>
                    <input type="text" wire:model="descuentoPct" class="se-input w-full" readonly>
                </div>
                <div>
                    <label class="se-label">Importe a cobrar</label>
                    <input type="number" step="0.01" wire:model="importe" class="se-input w-full" min="0.01">
                    @error('importe') <p class="se-field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="se-label">Concepto (opcional)</label>
                <textarea wire:model="concepto" rows="2" class="se-input w-full" placeholder="Si se deja vacío se arma automáticamente"></textarea>
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

            <div class="flex justify-end gap-2">
                <a href="{{ route('cooperadora.ingresos') }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Registrar y emitir recibo</button>
            </div>
        </form>
    </div>

    @script
    <script>
        $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
    </script>
    @endscript
</div>
