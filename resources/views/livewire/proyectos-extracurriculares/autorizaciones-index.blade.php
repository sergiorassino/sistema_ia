<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">Proyectos extracurriculares</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Autorizaciones y notificaciones</h2>
                    <p class="text-sm text-white/80">{{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}</p>
                </div>
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
                           placeholder="Buscar actividad o lugar…"
                           class="form-input w-full pl-9"
                           autocomplete="off"
                           aria-label="Buscar actividad">
                </div>
                <p class="shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">
                    {{ $registros->total() }} actividades aprobadas
                </p>
            </div>

            <div class="se-card overflow-hidden">
                @if ($registros->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-neutral-500">
                        No hay actividades aprobadas en este ciclo.
                    </div>
                @else
                    <div class="w-full overflow-x-auto">
                        <div class="flex justify-start">
                            <div class="gf gf-ext-proy gf-ext-proy--autorizaciones">
                                <div class="gf-head">
                                    <div class="gf-th gf-col-actividad">Actividad</div>
                                    <div class="gf-th gf-col-fechas">Fechas</div>
                                    <div class="gf-th gf-col-lugar">Lugar</div>
                                    <div class="gf-th gf-col-grupo">Grupo</div>
                                    <div class="gf-th-right gf-col-acciones">Documentos</div>
                                </div>
                                @foreach ($registros as $reg)
                                    <div class="gf-row gf-row-hover" wire:key="ext-aut-{{ $reg->id }}">
                                        <div class="gf-td gf-col-actividad font-semibold text-neutral-900">{{ $reg->nombre }}</div>
                                        <div class="gf-td gf-col-fechas text-neutral-700">
                                            {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::textoResumenFechas($reg) ?: '—' }}
                                        </div>
                                        <div class="gf-td gf-col-lugar text-neutral-700">{{ $reg->lugar ?: '—' }}</div>
                                        <div class="gf-td gf-col-grupo text-neutral-700">
                                            @if ($reg->tipo_grupo === \App\Models\ExtActividad::TIPO_GRUPO_CURSOS)
                                                {{ \App\Support\ProyectosExtracurriculares\ExtActividadesService::textoGrupoInvolucrado($reg) ?: '—' }}
                                            @else
                                                {{ (int) $reg->alumnos_count }} {{ (int) $reg->alumnos_count === 1 ? 'alumno' : 'alumnos' }}
                                            @endif
                                        </div>
                                        <div class="gf-td gf-col-acciones">
                                            <div class="flex flex-wrap justify-end gap-1.5">
                                                <a href="{{ route('proyectosExtracurriculares.autorizaciones.alumnos', $reg->id) }}"
                                                   class="inline-flex items-center rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-primary-700">
                                                    Autorizaciones
                                                </a>
                                                <a href="{{ route('proyectosExtracurriculares.notificaciones.alumnos', $reg->id) }}"
                                                   class="inline-flex items-center rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-primary-700 ring-1 ring-accent-200 hover:bg-accent-50">
                                                    Notificaciones
                                                </a>
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
</div>
