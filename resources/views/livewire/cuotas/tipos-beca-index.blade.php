<div class="se-page">
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="se-soft-card flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Becas</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Tipos de Beca</h2>
                <p class="text-sm text-white/80">Porcentaje de descuento sobre el arancel (tabla cuotasbecas).</p>
            </div>
            <button type="button" wire:click="openCreate"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                + Nuevo tipo
            </button>
        </div>
    </section>

    <div class="w-full overflow-x-auto se-grid-angosta-wrap">
        <div class="gf min-w-[36rem]">
            <div class="gf-head">
                <div class="gf-th flex-1 min-w-[14rem]">Nombre</div>
                <div class="gf-th w-32 text-right">Descuento</div>
                <div class="gf-th-right w-48">Acciones</div>
            </div>

            @forelse ($becas as $beca)
                @php
                    $esSistema = (int) $beca->id === \App\Support\Cuotas\GeneracionCuotaEstudianteService::BECA_CUOTA_ENTERA;
                    $etiqueta = trim((string) ($beca->nombreBeca ?? ''));
                    if ($etiqueta === '' && $esSistema) {
                        $etiqueta = 'C/E (cuota entera)';
                    }
                @endphp
                <div class="gf-row gf-row-hover">
                    <div class="gf-td flex-1 min-w-[14rem] font-medium">
                        {{ $etiqueta }}
                        @if ($esSistema)
                            <span class="ml-1 rounded-md bg-accent-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Sistema</span>
                        @endif
                    </div>
                    <div class="gf-td w-32 text-right tabular-nums">
                        {{ number_format((float) ($beca->porcentaje ?? 0), 2, ',', '') }}%
                    </div>
                    <div class="gf-td-actions w-48">
                        <button type="button" wire:click="openEdit({{ $beca->id }})" class="btn-secondary btn-sm">Editar</button>
                        @unless ($esSistema)
                            <button type="button" wire:click="confirmDelete({{ $beca->id }})" class="btn-danger btn-sm">Eliminar</button>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="gf-empty">No hay tipos de beca registrados.</div>
            @endforelse
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm"
             x-data x-init="$el.querySelector('input')?.focus()">
            <div class="w-full max-w-md rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-accent-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-neutral-900">
                        {{ $editId ? 'Editar tipo de beca' : 'Nuevo tipo de beca' }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)" class="text-neutral-400 transition hover:text-neutral-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4">
                    <div class="gf w-full">
                        <div class="gf-row @error('nombreBeca') bg-red-50 @enderror">
                            <div class="gf-label gf-label-req w-44">Nombre</div>
                            <div class="gf-cell @error('nombreBeca') gf-cell-err @enderror">
                                <input wire:model="nombreBeca" type="text" maxlength="80"
                                       placeholder="Ej: Beca 50%"
                                       class="gf-input @error('nombreBeca') gf-input-err @enderror">
                            </div>
                        </div>
                        @error('nombreBeca')
                            <div class="gf-error-row">
                                <div class="gf-error-spacer w-44"></div>
                                <div class="gf-error-msg">{{ $message }}</div>
                            </div>
                        @enderror

                        <div class="gf-row @error('porcentaje') bg-red-50 @enderror">
                            <div class="gf-label gf-label-req w-44">
                                Descuento <span class="font-normal text-neutral-400">(%)</span>
                            </div>
                            <div class="gf-cell @error('porcentaje') gf-cell-err @enderror">
                                <input wire:model="porcentaje" type="text" inputmode="decimal"
                                       placeholder="Ej: 50"
                                       class="gf-input @error('porcentaje') gf-input-err @enderror">
                            </div>
                        </div>
                        @error('porcentaje')
                            <div class="gf-error-row">
                                <div class="gf-error-spacer w-44"></div>
                                <div class="gf-error-msg">{{ $message }}</div>
                            </div>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showModal', false)" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="save">Guardar</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="px-6 py-5">
                    <div class="flex items-start gap-3">
                        @if ($deleteId)
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                            </div>
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                                <svg class="h-5 w-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                                </svg>
                            </div>
                        @endif
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-neutral-900">
                                {{ $deleteId ? 'Confirmar eliminación' : 'No se puede eliminar' }}
                            </h3>
                            <p class="text-sm text-neutral-600">{{ $deleteInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showConfirm', false)" class="btn-secondary">
                        {{ $deleteId ? 'Cancelar' : 'Cerrar' }}
                    </button>
                    @if ($deleteId)
                        <button type="button" wire:click="delete" wire:loading.attr="disabled" class="btn-danger">
                            <span wire:loading.remove wire:target="delete">Eliminar</span>
                            <span wire:loading wire:target="delete">Eliminando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
