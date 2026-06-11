<div>
    <div class="se-page max-w-6xl mx-auto">
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
                 class="se-soft-card mb-4 flex items-center gap-3 border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <svg class="h-5 w-5 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <section class="se-hero mb-6">
            <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Viajes / Salidas educativas</p>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Gestión de salidas</h1>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
                <a href="{{ route('viajes.salidas.create') }}"
                   class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-100">
                    + Nuevo viaje
                </a>
            </div>
        </section>

        <div class="se-toolbar mb-4" x-data x-init="$nextTick(() => $refs.buscarViajes?.focus())">
            <div class="relative flex-1 max-w-md">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       x-ref="buscarViajes"
                       placeholder="Buscar por título…"
                       class="form-input pl-9"
                       autocomplete="off">
            </div>
        </div>

        <div class="se-card overflow-hidden p-2 sm:p-3">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf min-w-[56rem]">
                        <div class="gf-head">
                            <div class="gf-th w-48">Título</div>
                            <div class="gf-th w-28">Desde</div>
                            <div class="gf-th w-28">Hasta</div>
                            <div class="gf-th flex-1 min-w-[10rem]">Descripción</div>
                            <div class="gf-th-right w-56">Acciones</div>
                        </div>

                        @forelse ($viajes as $viaje)
                            <div class="gf-row gf-row-hover" wire:key="viaje-{{ $viaje->id }}">
                                <div class="gf-td w-48 font-medium">{{ $viaje->titulo }}</div>
                                <div class="gf-td w-28 tabular-nums">
                                    {{ $viaje->desde?->format('d/m/Y') ?? '—' }}
                                </div>
                                <div class="gf-td w-28 tabular-nums">
                                    {{ $viaje->hasta?->format('d/m/Y') ?? '—' }}
                                </div>
                                <div class="gf-td flex-1 min-w-[10rem] text-neutral-600">
                                    <span class="line-clamp-2 text-xs">{{ strip_tags((string) $viaje->texto) }}</span>
                                </div>
                                <div class="gf-td-actions w-56">
                                    <a href="{{ route('viajes.salidas.imprimir', $viaje->id) }}"
                                       class="btn-secondary btn-sm">
                                        Imprimir
                                    </a>
                                    <a href="{{ route('viajes.salidas.edit', $viaje->id) }}"
                                       class="btn-secondary btn-sm">
                                        Editar
                                    </a>
                                    <button type="button"
                                            x-on:click="seSwalConfirmar(@js('¿Confirma eliminar este viaje?')).then(ok => ok && $wire.eliminarViaje({{ $viaje->id }}))"
                                            class="btn-danger btn-sm">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="gf-empty">No hay viajes registrados.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        $wire.on('se-swal-exito', ({ mensaje }) => {
            if (typeof seSwalExito === 'function') {
                seSwalExito(mensaje ?? 'Operación completada.');
            }
        });
        $wire.on('se-swal-error', ({ mensaje }) => {
            if (typeof seSwalError === 'function') {
                seSwalError(mensaje ?? 'No se pudo completar la operación.');
            }
        });
    </script>
    @endscript
</div>
