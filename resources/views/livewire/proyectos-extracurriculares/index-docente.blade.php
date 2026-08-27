<div>
    <div class="se-page">
        @if (session('success'))
            <div class="se-soft-card border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Autogestión docente</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Proyectos extracurriculares</h2>
                    <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Presentación a dirección</p>
                </div>
                @if ($tablasOk)
                    <a href="{{ route('portalDocente.proyectosExtracurriculares.create') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50">
                        Nuevo proyecto
                    </a>
                @endif
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
                <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                    {{ $registros->total() }} registros
                </p>
            </div>

            <div class="se-card overflow-hidden">
                @if ($registros->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-neutral-500">
                        Todavía no presentó proyectos en este ciclo.
                    </div>
                @else
                    <div class="w-full overflow-x-auto">
                        <div class="gf gf-ext-proy">
                            <div class="gf-head">
                                <div class="gf-th gf-col-actividad">Actividad</div>
                                <div class="gf-th gf-col-fechas">Fechas</div>
                                <div class="gf-th gf-col-estado">Estado</div>
                                <div class="gf-th-right gf-col-acciones">Acciones</div>
                            </div>
                            @foreach ($registros as $reg)
                                <div class="gf-row gf-row-hover" wire:key="ext-doc-{{ $reg->id }}">
                                    <div class="gf-td gf-col-actividad font-semibold text-neutral-900">{{ $reg->nombre }}</div>
                                    <div class="gf-td gf-col-fechas text-neutral-700">
                                        {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::textoResumenFechas($reg) ?: '—' }}
                                    </div>
                                    <div class="gf-td gf-col-estado">
                                        <span @class([
                                            'se-pill',
                                            'bg-emerald-100 text-emerald-800' => $reg->estaAprobada(),
                                            'bg-amber-100 text-amber-900' => $reg->estaPendiente(),
                                        ])>
                                            {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::etiquetaEstado((string) $reg->estado) }}
                                        </span>
                                    </div>
                                    <div class="gf-td gf-col-acciones">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a href="{{ route('portalDocente.proyectosExtracurriculares.edit', ['ref' => \App\Support\Security\OpaqueRouteToken::forExtActividad((int) $reg->id)]) }}"
                                               class="inline-flex items-center rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-accent-200 transition hover:bg-accent-50">
                                                {{ $reg->estaAprobada() ? 'Ver' : 'Editar' }}
                                            </a>
                                            @if ($reg->estaPendiente())
                                                <button type="button"
                                                        class="inline-flex items-center rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 transition hover:bg-red-50"
                                                        x-on:click="window.seSwalConfirmar('¿Eliminar este proyecto pendiente?', 'Eliminar').then(ok => ok && $wire.eliminar({{ (int) $reg->id }}))">
                                                    Eliminar
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
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

    @script
    <script>
        $wire.on('se-swal-exito', (e) => window.seSwalExito(e.mensaje));
        $wire.on('se-swal-error', (e) => window.seSwalError(e.mensaje));
    </script>
    @endscript
</div>
