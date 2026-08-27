<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Dirección</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Proyectos extracurriculares</h2>
                    <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Aprobación y comunicación</p>
                </div>
                <a href="{{ route('calendarioEscolar') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                    Ver calendario
                </a>
            </div>
        </section>

        @if (! $tablasOk)
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                {{ $mensajeTabla }}
            </div>
        @else
            <div class="se-toolbar">
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <input type="search"
                           wire:model.live.debounce.400ms="buscar"
                           placeholder="Buscar actividad…"
                           class="form-input w-full pl-9"
                           autocomplete="off"
                           aria-label="Buscar proyecto">
                </div>
                <select wire:model.live="filtroEstado" class="form-input w-full sm:max-w-[11rem]" aria-label="Estado">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendientes</option>
                    <option value="aprobado">Aprobados</option>
                </select>
                <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                    {{ $registros->total() }} registros
                </p>
            </div>

            <div class="se-card overflow-hidden">
                @if ($registros->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-neutral-500">
                        No hay proyectos en este ciclo.
                    </div>
                @else
                    <div class="w-full overflow-x-auto">
                        <div class="flex justify-start">
                            <div class="gf min-w-[52rem]">
                                <div class="gf-head">
                                    <div class="gf-th min-w-[10rem] flex-1">Actividad</div>
                                    <div class="gf-th min-w-[8rem]">Proponente</div>
                                    <div class="gf-th min-w-[9rem]">Fechas</div>
                                    <div class="gf-th w-28">Estado</div>
                                    <div class="gf-th-right w-64">Acciones</div>
                                </div>
                                @foreach ($registros as $reg)
                                    <div class="gf-row gf-row-hover" wire:key="ext-ges-{{ $reg->id }}">
                                        <div class="gf-td min-w-[10rem] flex-1 font-semibold text-neutral-900">{{ $reg->nombre }}</div>
                                        <div class="gf-td min-w-[8rem] text-neutral-700">
                                            {{ $reg->proponente?->nombre_completo ?? '—' }}
                                        </div>
                                        <div class="gf-td min-w-[9rem] text-neutral-700">
                                            {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::textoResumenFechas($reg) ?: '—' }}
                                        </div>
                                        <div class="gf-td w-28">
                                            <span @class([
                                                'se-pill',
                                                'bg-emerald-100 text-emerald-800' => $reg->estaAprobada(),
                                                'bg-amber-100 text-amber-900' => $reg->estaPendiente(),
                                            ])>
                                                {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::etiquetaEstado((string) $reg->estado) }}
                                            </span>
                                        </div>
                                        <div class="gf-td w-64">
                                            <div class="flex flex-wrap justify-end gap-1.5">
                                                <button type="button" wire:click="verDetalle({{ (int) $reg->id }})"
                                                        class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                                                    Ver
                                                </button>
                                                @if ($reg->estaPendiente())
                                                    <button type="button"
                                                            class="inline-flex items-center rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-primary-700"
                                                            x-on:click="window.seSwalConfirmar('¿Aprobar este proyecto? Pasará al calendario escolar.', 'Aprobar').then(ok => ok && $wire.aprobar({{ (int) $reg->id }}))">
                                                        Aprobar
                                                    </button>
                                                @else
                                                    <button type="button"
                                                            class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-50"
                                                            x-on:click="window.seSwalConfirmar('¿Volver el proyecto a pendiente? Saldrá del calendario.', 'Confirmar').then(ok => ok && $wire.volverAPendiente({{ (int) $reg->id }}))">
                                                        Volver a pendiente
                                                    </button>
                                                    <button type="button"
                                                            class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50"
                                                            x-on:click="window.seSwalConfirmar('Se enviará un comunicado a organizadores, docentes del curso (ppc) y preceptores. ¿Continuar?', 'Comunicar involucrados').then(ok => ok && $wire.comunicar({{ (int) $reg->id }}))">
                                                        Comunicar
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @if ($registros->hasPages())
                        <div class="se-matriz-list-footer">
                            {{ $registros->links('vendor.pagination.se-compact') }}
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>

    @teleport('body')
        @if ($detalle)
            <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                 role="dialog" aria-modal="true" aria-labelledby="ext-detalle-title">
                <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarDetalle"></div>
                <div class="relative z-10 my-auto flex w-full max-w-2xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                    <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                        <h3 id="ext-detalle-title" class="text-lg font-bold text-neutral-900">Detalle del proyecto</h3>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        @include('livewire.proyectos-extracurriculares.partials.detalle-actividad', ['actividad' => $detalle])
                    </div>
                    <div class="shrink-0 flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-3">
                        @if ($detalle->estaPendiente())
                            <button type="button"
                                    class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
                                    x-on:click="window.seSwalConfirmar('¿Aprobar este proyecto? Pasará al calendario escolar.', 'Aprobar').then(ok => ok && $wire.aprobar({{ (int) $detalle->id }}))">
                                Aprobar
                            </button>
                        @else
                            <button type="button"
                                    class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-white"
                                    x-on:click="window.seSwalConfirmar('Se enviará un comunicado a organizadores, docentes del curso (ppc) y preceptores. ¿Continuar?', 'Comunicar involucrados').then(ok => ok && $wire.comunicar({{ (int) $detalle->id }}))">
                                Comunicar involucrados
                            </button>
                        @endif
                        <button type="button" wire:click="cerrarDetalle"
                                class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-neutral-700 ring-1 ring-accent-200 hover:bg-accent-50">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endteleport

    @script
    <script>
        $wire.on('se-swal-exito', (e) => window.seSwalExito(e.mensaje));
        $wire.on('se-swal-error', (e) => window.seSwalError(e.mensaje));
    </script>
    @endscript
</div>
