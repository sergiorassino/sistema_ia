<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Cooperadora</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Rubros de ingreso</h2>
                    <p class="text-sm text-white/80">Cuotas, donaciones, uniformes y otros rubros.</p>
                </div>
                <button type="button" wire:click="abrirNuevo" class="btn-primary shrink-0">+ Nuevo rubro</button>
            </div>
        </section>

        <div class="w-full overflow-x-auto">
            <div class="flex justify-start">
            <div class="gf min-w-[44rem]">
                <div class="gf-head">
                    <div class="gf-th flex-1">Nombre</div>
                    <div class="gf-th w-28">Tipo</div>
                    <div class="gf-th w-24 text-center">Dto. hermanos</div>
                    <div class="gf-th w-20 text-center">Anual</div>
                    <div class="gf-th w-16 text-center">Ord.</div>
                    <div class="gf-th w-20 text-center">Activo</div>
                    <div class="gf-th-right w-40">Acciones</div>
                </div>
                @forelse ($rubros as $rubro)
                    <div class="gf-row gf-row-hover">
                        <div class="gf-td flex-1 font-medium">{{ $rubro->nombre }}</div>
                        <div class="gf-td w-28 text-xs">{{ $rubro->etiquetaTipo() }}</div>
                        <div class="gf-td w-24 text-center text-xs">
                            {{ $rubro->aplicaDescuentoHermano() ? 'Sí' : '—' }}
                        </div>
                        <div class="gf-td w-20 text-center">{{ $rubro->es_anual ? 'Sí' : '—' }}</div>
                        <div class="gf-td w-16 text-center">{{ $rubro->orden }}</div>
                        <div class="gf-td w-20 text-center">{{ $rubro->activo ? 'Sí' : 'No' }}</div>
                        <div class="gf-td-actions w-40">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="abrirEditar({{ $rubro->id }})" class="btn-secondary btn-sm">Editar</button>
                                <button type="button"
                                        class="btn-danger btn-sm"
                                        title="Eliminar rubro"
                                        x-on:click="window.seSwalConfirmar(
                                            @js('¿Eliminar el rubro «'.$rubro->nombre.'»? Esta acción no se puede deshacer.'),
                                            'Eliminar rubro',
                                            { confirmButtonText: 'Sí, eliminar' }
                                        ).then((ok) => { if (ok) $wire.eliminar({{ $rubro->id }}); })">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="gf-empty">No hay rubros cargados.</div>
                @endforelse
            </div>
            </div>
        </div>
    </div>

    @if ($showModal)
        @teleport('body')
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog"
                 aria-modal="true"
                 wire:key="coop-rubro-modal">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModal"></div>
                <div class="relative z-10 my-auto w-full max-w-lg max-h-[calc(100dvh-1.75rem)] overflow-y-auto rounded-2xl bg-white shadow-xl ring-1 ring-black/5"
                     @click.stop>
                    <div class="border-b border-accent-200 px-5 py-4">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ $editId ? 'Editar rubro' : 'Nuevo rubro' }}</h3>
                    </div>
                    <form wire:submit="guardar" class="space-y-4 p-5">
                        <div>
                            <label class="se-label">Nombre</label>
                            <input type="text" wire:model="nombre" class="se-input w-full">
                            @error('nombre') <p class="se-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="se-label">Tipo</label>
                            <select wire:model.live="tipo" class="se-input w-full">
                                @foreach (\App\Models\CoopRubroIngreso::etiquetasTipo() as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($tipo === 'origen_estudiantes')
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="aplicaDescuentoHermano" class="rounded border-accent-300 text-primary-600">
                                Aplica descuento por hermanos
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="esAnual" class="rounded border-accent-300 text-primary-600">
                                Ítems anuales (precio por año lectivo)
                            </label>
                        @endif
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

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje, titulo }) => window.seSwalExito(mensaje, titulo ?? 'Listo'));
        $wire.on('se-swal-error', ({ mensaje, titulo }) => window.seSwalError(mensaje, titulo ?? 'Error'));
        $wire.on('se-swal-aviso', ({ mensaje, titulo }) => window.seSwalAviso(mensaje, titulo ?? 'Atención'));
    </script>
    @endscript
</div>
