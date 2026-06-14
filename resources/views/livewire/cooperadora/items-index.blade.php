<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Cooperadora</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Ítems de ingreso</h2>
                    <p class="text-sm text-white/80">Matrícula, cuotas mensuales, donaciones, uniformes, etc.</p>
                </div>
                @if ($rubroSel)
                    <button type="button" wire:click="abrirNuevo" class="btn-primary shrink-0">+ Nuevo ítem</button>
                @endif
            </div>
        </section>

        <div class="se-toolbar flex-wrap gap-3">
            <div class="min-w-[14rem] flex-1">
                <label class="se-label">Rubro</label>
                <select wire:model.live="idRubro" class="se-input w-full">
                    <option value="">— Seleccione —</option>
                    @foreach ($rubros as $rubro)
                        <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @if ($rubroSel?->es_anual)
                <div class="flex flex-wrap items-end gap-2">
                    <label class="flex items-center gap-2 rounded-xl border border-accent-200 bg-white px-3 py-2 text-sm">
                        <input type="checkbox" wire:model.live="mostrarHistorial" class="rounded border-accent-300 text-primary-600">
                        Mostrar historial
                    </label>
                    <button type="button" wire:click="generarItemsAnio" class="btn-secondary btn-sm">
                        Generar ítems {{ $anioVigente }}
                    </button>
                </div>
            @endif
        </div>

        <div class="w-full overflow-x-auto se-grid-angosta-wrap">
            <div class="gf min-w-[42rem]">
                <div class="gf-head">
                    <div class="gf-th flex-1">Nombre</div>
                    <div class="gf-th w-20 text-center">Año</div>
                    <div class="gf-th w-28 text-right">Precio</div>
                    <div class="gf-th w-16 text-center">Ord.</div>
                    <div class="gf-th w-20 text-center">Activo</div>
                    <div class="gf-th-right w-28">Acciones</div>
                </div>
                @forelse ($items as $item)
                    <div class="gf-row gf-row-hover">
                        <div class="gf-td flex-1 font-medium">{{ $item->nombre }}</div>
                        <div class="gf-td w-20 text-center">{{ $item->anio ?? '—' }}</div>
                        <div class="gf-td w-28 text-right tabular-nums">${{ number_format((float) $item->precio, 2, ',', '.') }}</div>
                        <div class="gf-td w-16 text-center">{{ $item->orden }}</div>
                        <div class="gf-td w-20 text-center">{{ $item->activo ? 'Sí' : 'No' }}</div>
                        <div class="gf-td-actions w-28">
                            <button type="button" wire:click="abrirEditar({{ $item->id }})" class="btn-secondary btn-sm">Editar</button>
                        </div>
                    </div>
                @empty
                    <div class="gf-empty">
                        @if ($rubroSel)
                            No hay ítems para este rubro{{ $rubroSel->es_anual && ! $mostrarHistorial ? ' en el año '.$anioVigente : '' }}.
                        @else
                            Seleccione un rubro.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($showModal)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3" role="dialog" aria-modal="true" wire:key="coop-item-modal">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModal"></div>
                <div class="relative z-10 my-auto w-full max-w-lg rounded-2xl bg-white shadow-xl ring-1 ring-black/5" @click.stop>
                    <div class="border-b border-accent-200 px-5 py-4">
                        <h3 class="text-lg font-semibold">{{ $editId ? 'Editar ítem' : 'Nuevo ítem' }}</h3>
                    </div>
                    <form wire:submit="guardar" class="space-y-4 p-5">
                        <div>
                            <label class="se-label">Nombre</label>
                            <input type="text" wire:model="nombre" class="se-input w-full">
                            @error('nombre') <p class="se-field-error">{{ $message }}</p> @enderror
                        </div>
                        @if ($rubroSel?->es_anual)
                            <div>
                                <label class="se-label">Año</label>
                                <input type="number" wire:model="anio" class="se-input w-full" min="2000" max="2100">
                                @error('anio') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="se-label">Precio</label>
                                <input type="number" step="0.01" wire:model="precio" class="se-input w-full" min="0">
                                @error('precio') <p class="se-field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="se-label">Orden</label>
                                <input type="number" wire:model="orden" class="se-input w-full" min="0">
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="activo" class="rounded border-accent-300 text-primary-600"> Activo
                        </label>
                        <div class="flex justify-end gap-2 border-t border-accent-100 pt-4">
                            <button type="button" wire:click="cerrarModal" class="btn-secondary">Cancelar</button>
                            <button type="submit" class="btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
