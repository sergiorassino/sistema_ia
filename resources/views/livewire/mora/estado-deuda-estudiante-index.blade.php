@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Mora\EstadoDeudaEstudianteListado;
    use App\Support\Security\OpaqueRouteToken;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración · Gestión de mora</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Estado de Deuda por Estudiante</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Ciclo lectivo {{ schoolCtx()->terlecAno() }} — estudiantes matriculados en Inicial, Primario o Secundario, con o sin familia asignada.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a href="{{ $this->urlListadoPdf() }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   @class([
                       'inline-flex items-center justify-center rounded-2xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'border-white/20 bg-white text-primary-700 hover:bg-accent-50' => ! $estudiantes->isEmpty(),
                       'pointer-events-none border-white/10 bg-white/20 text-white/50' => $estudiantes->isEmpty(),
                   ])
                   title="Listado PDF con los filtros actuales">
                    Exportar PDF
                </a>
                <a href="{{ $this->urlListadoExcel() }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   @class([
                       'inline-flex items-center justify-center rounded-2xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'border-white/20 bg-white text-primary-700 hover:bg-accent-50' => ! $estudiantes->isEmpty(),
                       'pointer-events-none border-white/10 bg-white/20 text-white/50' => $estudiantes->isEmpty(),
                   ])
                   title="Listado Excel con los filtros actuales">
                    Exportar Excel
                </a>
            </div>
        </div>
    </section>

    <div class="se-toolbar mb-4 sm:items-end" x-data x-init="$nextTick(() => $refs.moraBuscarEst?.focus())">
        <div class="flex-1 max-w-xl">
            <label for="mora-buscar-estudiante" class="form-label">Búsqueda</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input wire:model.live.debounce.400ms="search"
                       id="mora-buscar-estudiante"
                       type="search"
                       x-ref="moraBuscarEst"
                       autofocus
                       placeholder="Apellido, nombre o DNI del estudiante; familia o responsable..."
                       class="form-input pl-9"
                       autocomplete="off">
            </div>
        </div>
        <div class="w-full sm:w-56 shrink-0">
            <label for="filtro-nivel-mora-est" class="form-label">Nivel</label>
            <select id="filtro-nivel-mora-est"
                    wire:model.live="idNivel"
                    class="form-input">
                <option value="">Todos</option>
                @foreach ($niveles as $nivel)
                    <option value="{{ (int) $nivel->id }}">{{ $nivel->nivel }}</option>
                @endforeach
            </select>
        </div>
        <label for="mora-solo-deuda-est" class="inline-flex items-center gap-2 cursor-pointer sm:pb-0.5">
            <input id="mora-solo-deuda-est"
                   type="checkbox"
                   wire:model.live="soloConDeuda"
                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
            <span class="text-sm font-semibold text-neutral-700">Solo alumnos con deuda</span>
        </label>
    </div>

    @if ($estudiantes->isEmpty())
        <div class="se-card p-8 text-center text-sm text-neutral-600">
            @if (trim($search) !== '' || $idNivel !== '' || $soloConDeuda)
                No se encontraron estudiantes con ese criterio.
            @else
                No hay estudiantes matriculados en el ciclo lectivo activo.
            @endif
        </div>
    @else
        <div class="se-card se-card-mora-estudiantes overflow-hidden p-0">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf gf-vcenter gf-mora-estudiantes">
                        <div class="gf-head">
                            <div class="gf-th gf-th-mora-accion gf-th-mora-accion-label justify-center text-center" title="Estado Deuda">Estado<br>Deuda</div>
                            <div class="gf-th gf-th-mora-accion gf-th-mora-accion-label justify-center text-center" title="Diferimiento Matrícula">Difer.<br>Matr.</div>
                            <div class="gf-th gf-th-mora-accion gf-th-mora-accion-label justify-center text-center" title="Plan de Pago">Plan de<br>Pago</div>
                            <div class="gf-th gf-th-mora-estudiante">Estudiante</div>
                            <div class="gf-th gf-th-mora-dni">DNI</div>
                            <div class="gf-th gf-th-mora-curso">Curso actual</div>
                            <div class="gf-th gf-th-mora-deuda">Deuda</div>
                            <div class="gf-th gf-th-mora-familia">Familia</div>
                            <div class="gf-th gf-th-mora-responsable">Responsable</div>
                        </div>

                        @foreach ($estudiantes as $estudiante)
                            @php
                                $apellidoNombre = EstadoDeudaEstudianteListado::apellidoNombre($estudiante);
                                $curso = EstadoDeudaEstudianteListado::cursoCicloActivo($estudiante);
                                $familia = EstadoDeudaEstudianteListado::familiaAsignada($estudiante->familia);
                                $etiquetaFamilia = trim((string) ($familia?->apellido ?? ''));
                                $etiquetaResponsable = trim((string) ($familia?->responsable ?? ''));
                                $deudaEstudiante = (float) ($totalesDeuda[$estudiante->id] ?? 0);
                            @endphp
                            <div class="gf-row gf-row-hover gf-row-mora-estudiante" wire:key="estudiante-{{ $estudiante->id }}">
                                <div class="gf-td gf-td-mora-accion justify-center">
                                    <a href="{{ route('mora.estado-deuda-estudiante.pdf', ['ref' => OpaqueRouteToken::forEstadoDeudaEstudiante((int) $estudiante->id)]) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="se-mora-accion-btn"
                                       title="Estado de Deuda (PDF)"
                                       aria-label="Estado de Deuda — PDF">
                                        <svg class="h-5 w-5 text-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11l-6 6"/>
                                        </svg>
                                    </a>
                                </div>
                                <div class="gf-td gf-td-mora-accion justify-center">
                                    <button type="button"
                                            class="se-mora-accion-btn se-mora-accion-btn--diferimiento"
                                            title="Diferimiento Matrícula (próximamente)"
                                            aria-label="Diferimiento Matrícula — próximamente"
                                            disabled>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9" stroke-width="1.75"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 12h4m0 0l-2-2m2 2l-2 2"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="gf-td gf-td-mora-accion justify-center">
                                    <button type="button"
                                            class="se-mora-accion-btn"
                                            title="Plan de Pago (próximamente)"
                                            aria-label="Plan de Pago — próximamente"
                                            disabled>
                                        <svg class="h-5 w-5 text-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 11h6"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="gf-td gf-td-mora-estudiante font-medium uppercase">
                                    @if ($apellidoNombre !== '')
                                        <span class="truncate">{!! CuotasFormato::resaltarTerminoBusqueda($apellidoNombre, $search) !!}</span>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-mora-dni tabular-nums whitespace-nowrap">
                                    {{ CuotasFormato::formatearDni($estudiante->dni) }}
                                </div>
                                <div class="gf-td gf-td-mora-curso uppercase truncate" title="{{ $curso }}">
                                    {{ $curso !== '' ? $curso : '—' }}
                                </div>
                                <div class="gf-td gf-td-mora-deuda tabular-nums whitespace-nowrap {{ $deudaEstudiante > 0 ? 'se-mora-deuda se-mora-deuda--positivo' : 'se-mora-deuda' }}"
                                     title="Total adeudado (estudiante)">
                                    {{ CuotasFormato::formatearImporte($deudaEstudiante) }}
                                </div>
                                <div class="gf-td gf-td-mora-familia uppercase">
                                    @if ($etiquetaFamilia !== '')
                                        {!! CuotasFormato::resaltarTerminoBusqueda($etiquetaFamilia, $search) !!}
                                    @else
                                        <span class="text-neutral-400 italic font-normal normal-case">Sin familia</span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-mora-responsable uppercase">
                                    @if ($etiquetaResponsable !== '')
                                        {!! CuotasFormato::resaltarTerminoBusqueda($etiquetaResponsable, $search) !!}
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($estudiantes->hasPages())
                <div class="se-matriz-list-footer">
                    {{ $estudiantes->links('vendor.pagination.se-compact') }}
                </div>
            @endif
        </div>
    @endif
</div>
