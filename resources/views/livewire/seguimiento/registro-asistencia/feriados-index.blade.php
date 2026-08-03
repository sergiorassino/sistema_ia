<div>
<div class="se-page max-w-6xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Registro de asistencia</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Feriados</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Los feriados son por nivel
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('seguimiento.registro-asistencia') }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    Volver al registro
                </a>
                <button type="button" wire:click="openCreate"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    + Nuevo feriado
                </button>
            </div>
        </div>
    </section>

    <div class="se-card overflow-hidden">
        <div class="se-toolbar-pocos-campos border-b border-accent-100 bg-white px-5 py-3">
            <div class="w-full max-w-sm">
                <label for="fer-busqueda" class="form-label">Buscar por nombre</label>
                <input id="fer-busqueda"
                       type="search"
                       wire:model.live.debounce.300ms="busqueda"
                       placeholder="Nombre del feriado…"
                       class="form-input mt-1.5" />
            </div>
        </div>

        <div class="w-full overflow-x-auto se-grid-angosta-wrap">
            <div class="gf min-w-[28rem]">
                <div class="gf-head">
                    <div class="gf-th w-36">Fecha</div>
                    <div class="gf-th flex-1 min-w-[12rem]">Nombre</div>
                    <div class="gf-th-right w-40">Acciones</div>
                </div>

                @forelse ($registros as $f)
                    <div class="gf-row gf-row-hover" wire:key="feriado-{{ $f->id }}">
                        <div class="gf-td w-36 tabular-nums">
                            {{ $f->fechaFeriado?->format('d/m/Y') ?? '—' }}
                        </div>
                        <div class="gf-td flex-1 min-w-[12rem] font-medium">{{ $f->nombre }}</div>
                        <div class="gf-td-right w-40 flex items-center justify-end gap-1">
                            <button type="button"
                                    wire:click="openEdit({{ $f->id }})"
                                    class="rounded-lg border border-accent-200 bg-white px-2.5 py-1 text-xs font-semibold text-primary-700 hover:bg-accent-50">
                                Editar
                            </button>
                            <button type="button"
                                    x-on:click="seSwalConfirmar('¿Eliminar este feriado?', 'Confirmar').then(ok => ok && $wire.eliminar({{ $f->id }}))"
                                    class="rounded-lg border border-red-200 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">
                                Borrar
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-neutral-500">
                        No hay feriados cargados para este nivel.
                    </div>
                @endforelse
            </div>
        </div>

        @if ($registros->hasPages())
            <div class="se-matriz-list-footer">
                {{ $registros->links('vendor.pagination.se-compact') }}
            </div>
        @endif
    </div>

    @teleport('body')
        <div>
            @if ($showModal)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog" aria-modal="true" aria-labelledby="fer-modal-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
                    <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                        <div class="shrink-0 border-b border-accent-200 px-5 py-3">
                            <p id="fer-modal-titulo" class="text-sm font-bold text-neutral-900">
                                {{ $editId ? 'Editar feriado' : 'Nuevo feriado' }}
                            </p>
                        </div>
                        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                            <div>
                                <label for="fer-fecha" class="form-label">Fecha</label>
                                <input id="fer-fecha" type="date" wire:model="fechaFeriado" class="form-input mt-1.5" />
                                @error('fechaFeriado') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="fer-nombre" class="form-label">Nombre</label>
                                <input id="fer-nombre" type="text" wire:model="nombre" maxlength="120" class="form-input mt-1.5" />
                                @error('nombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex shrink-0 justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                            <button type="button" wire:click="$set('showModal', false)"
                                    class="rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 hover:bg-accent-50">
                                Cancelar
                            </button>
                            <button type="button" wire:click="save"
                                    class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport
</div>

@script
<script>
    $wire.on('se-swal-exito', (e) => { window.seSwalExito?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
    $wire.on('se-swal-error', (e) => { window.seSwalError?.(e.mensaje ?? e[0]?.mensaje ?? ''); });
</script>
@endscript
</div>
