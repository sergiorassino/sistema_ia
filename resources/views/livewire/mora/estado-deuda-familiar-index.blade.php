@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Mora\EstadoDeudaFamiliarListado;
    use App\Support\Security\OpaqueRouteToken;
@endphp

<div class="se-page max-w-[90rem] mx-auto">
    <section class="se-hero mb-6">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-1">
                <p class="se-eyebrow">Administración · Gestión de mora</p>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Estado de Deuda Familiar</h1>
                <p class="text-sm text-white/80 max-w-2xl">
                    Ciclo lectivo {{ schoolCtx()->terlecAno() }} — familias con estudiantes matriculados en Inicial, Primario o Secundario.
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar mb-4" x-data x-init="$nextTick(() => $refs.moraBuscar?.focus())">
        <div class="relative flex-1 max-w-xl">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input wire:model.live.debounce.400ms="search"
                   type="search"
                   x-ref="moraBuscar"
                   autofocus
                   placeholder="Búsqueda rápida: familia, responsable, apellido, nombre o DNI del estudiante..."
                   class="form-input pl-9"
                   autocomplete="off">
        </div>
    </div>

    @if ($familias->isEmpty())
        <div class="se-card p-8 text-center text-sm text-neutral-600">
            @if (trim($search) !== '')
                No se encontraron familias con ese criterio.
            @else
                No hay familias con estudiantes matriculados en el ciclo lectivo activo.
            @endif
        </div>
    @else
        <div class="se-card se-card-mora-familias overflow-hidden p-0">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="gf gf-vcenter gf-mora-familias min-w-[58rem]">
                        <div class="gf-head">
                            <div class="gf-th gf-th-mora-accion gf-th-mora-accion-label justify-center text-center" title="Estado Deuda">Estado<br>Deuda</div>
                            <div class="gf-th gf-th-mora-accion gf-th-mora-accion-label justify-center text-center" title="Diferimiento Matrícula">Difer.<br>Matr.</div>
                            <div class="gf-th gf-th-mora-accion gf-th-mora-accion-label justify-center text-center" title="Plan de Pago">Plan de<br>Pago</div>
                            <div class="gf-th gf-th-mora-familia">Familia</div>
                            <div class="gf-th gf-th-mora-responsable">Responsable</div>
                            <div class="gf-th gf-th-mora-estudiantes gf-th-mora-estudiantes-label flex-1 min-w-[22rem]">ESTUDIANTES (Curso Actual)</div>
                        </div>

                        @foreach ($familias as $familia)
                            @php
                                $estudiantes = $familia->legajos;
                                $etiquetaFamilia = trim((string) ($familia->apellido ?? ''));
                                $etiquetaResponsable = trim((string) ($familia->responsable ?? ''));
                            @endphp
                            <div class="gf-row gf-row-hover gf-row-mora-familia" wire:key="familia-{{ $familia->id }}">
                                <div class="gf-td gf-td-mora-accion justify-center">
                                    <a href="{{ route('mora.estado-deuda-familiar.pdf', ['ref' => OpaqueRouteToken::forEstadoDeudaFamiliar((int) $familia->id)]) }}"
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
                                <div class="gf-td gf-td-mora-familia font-medium uppercase">
                                    <span class="inline-flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                        @if ($etiquetaFamilia !== '')
                                            <span>{!! CuotasFormato::resaltarTerminoBusqueda($etiquetaFamilia, $search) !!}</span>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                        <span class="se-mora-deuda tabular-nums whitespace-nowrap {{ ($totalesDeuda['porFamilia'][$familia->id] ?? 0) > 0 ? 'se-mora-deuda--positivo' : '' }}"
                                              title="Total adeudado (familia)">
                                            {{ CuotasFormato::formatearImporte($totalesDeuda['porFamilia'][$familia->id] ?? 0) }}
                                        </span>
                                    </span>
                                </div>
                                <div class="gf-td gf-td-mora-responsable uppercase">
                                    @if ($etiquetaResponsable !== '')
                                        {!! CuotasFormato::resaltarTerminoBusqueda($etiquetaResponsable, $search) !!}
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </div>
                                <div class="gf-td gf-td-mora-estudiantes flex-1 min-w-[22rem] !p-0">
                                    @if ($estudiantes->isEmpty())
                                        <p class="px-2 py-2 text-neutral-500 italic">No hay registros para mostrar</p>
                                    @else
                                        <div class="se-mora-estudiantes-list">
                                            @foreach ($estudiantes as $estudiante)
                                                @php
                                                    $apellidoNombre = EstadoDeudaFamiliarListado::apellidoNombre($estudiante);
                                                    $curso = EstadoDeudaFamiliarListado::cursoCicloActivo($estudiante);
                                                    $deudaEstudiante = (float) ($totalesDeuda['porLegajo'][$estudiante->id] ?? 0);
                                                @endphp
                                                <div class="se-mora-estudiante-row" wire:key="estudiante-{{ $familia->id }}-{{ $estudiante->id }}">
                                                    <span class="se-mora-estudiante-nombre uppercase truncate" title="{{ $apellidoNombre }}">
                                                        <span class="inline-flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                            @if ($apellidoNombre !== '')
                                                                <span class="truncate">{!! CuotasFormato::resaltarTerminoBusqueda($apellidoNombre, $search) !!}</span>
                                                            @else
                                                                <span class="text-neutral-400">—</span>
                                                            @endif
                                                            <span class="se-mora-deuda tabular-nums whitespace-nowrap {{ $deudaEstudiante > 0 ? 'se-mora-deuda--positivo' : '' }}"
                                                                  title="Total adeudado (estudiante)">
                                                                {{ CuotasFormato::formatearImporte($deudaEstudiante) }}
                                                            </span>
                                                        </span>
                                                    </span>
                                                    <span class="se-mora-estudiante-dni tabular-nums whitespace-nowrap">
                                                        {{ CuotasFormato::formatearDni($estudiante->dni) }}
                                                    </span>
                                                    <span class="se-mora-estudiante-curso uppercase truncate" title="{{ $curso }}">
                                                        {{ $curso !== '' ? $curso : '—' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($familias->hasPages())
                <div class="border-t border-accent-200 bg-accent-50/70 px-4 py-3">
                    {{ $familias->links('vendor.pagination.se') }}
                </div>
            @endif
        </div>
    @endif
</div>
