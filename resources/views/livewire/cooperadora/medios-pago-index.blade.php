<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Cooperadora</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Medios de pago</h2>
                    <p class="text-sm text-white/80">Opciones para ingresos y egresos.</p>
                </div>
                <button type="button" wire:click="abrirNuevo" class="btn-primary shrink-0">+ Nuevo medio</button>
            </div>
        </section>

        <div class="w-full overflow-x-auto se-grid-angosta-wrap">
            <div class="gf min-w-[36rem]">
                <div class="gf-head">
                    <div class="gf-th flex-1">Nombre</div>
                    <div class="gf-th w-16 text-center">Ord.</div>
                    <div class="gf-th w-20 text-center">Activo</div>
                    <div class="gf-th-right w-40">Acciones</div>
                </div>
                @forelse ($medios as $medio)
                    <div class="gf-row gf-row-hover" wire:key="medio-{{ $medio->id }}">
                        <div class="gf-td flex-1 font-medium">{{ $medio->nombre }}</div>
                        <div class="gf-td w-16 text-center">{{ $medio->orden }}</div>
                        <div class="gf-td w-20 text-center">{{ $medio->activo ? 'Sí' : 'No' }}</div>
                        <div class="gf-td-actions w-40">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="abrirEditar({{ $medio->id }})" class="btn-secondary btn-sm">Editar</button>
                                <button type="button" wire:click="eliminar({{ $medio->id }})" class="btn-danger btn-sm">Borrar</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="gf-empty">No hay medios de pago cargados.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($showModal)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 wire:key="coop-medio-pago-modal">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModal"></div>
                <div class="relative z-10 my-auto w-full max-w-lg max-h-[calc(100dvh-1.75rem)] overflow-y-auto rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
                     @click.stop>
                    <div class="border-b border-accent-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ $editId ? 'Editar medio de pago' : 'Nuevo medio de pago' }}</h3>
                    </div>
                    <form wire:submit="guardar" class="space-y-4 p-5">
                        <div>
                            <label class="se-label">Nombre</label>
                            <input type="text" wire:model="nombre" class="se-input w-full" maxlength="80">
                            @error('nombre') <p class="se-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="se-label">Orden</label>
                                <input type="number" wire:model="orden" class="se-input w-full" min="0">
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="activo" class="rounded border-accent-300 text-primary-600"> Activo
                                </label>
                            </div>
                        </div>
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
