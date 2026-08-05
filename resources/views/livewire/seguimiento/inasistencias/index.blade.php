<div class="se-page max-w-6xl">
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
                <p class="se-eyebrow">Seguimiento</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Inasistencias</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="se-inas-curso" class="form-label">Curso</label>
                <select id="se-inas-curso" wire:model.live="idCurso" class="form-select mt-1.5">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-inas-alumno" class="form-label">Alumno</label>
                <select id="se-inas-alumno" wire:model.live="idMatricula" class="form-select mt-1.5" @disabled(! $idCurso)>
                    <option value="">— Seleccione —</option>
                    @foreach ($alumnos as $a)
                        @php
                            $idAlumno = (int) $a->id;
                            $etiquetaAlumno = trim(($a->apellido ?? '').', '.($a->nombre ?? '')).($a->dni ? ' · DNI '.$a->dni : '');
                            $teaAlumno = $teaPendientesPorMatricula[$idAlumno] ?? [];
                            if ($teaAlumno !== []) {
                                $etiquetaAlumno .= ' ⚠ TEA pendiente';
                            }
                        @endphp
                        <option value="{{ $a->id }}">{{ $etiquetaAlumno }}</option>
                    @endforeach
                </select>
                @if ($idCurso && $alumnos->isEmpty())
                    <p class="mt-1.5 text-xs text-amber-800">No hay matrículas para ese curso en el año actual.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($matricula)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-neutral-900">
                                {{ $matricula->legajo?->apellido }}, {{ $matricula->legajo?->nombre }}
                            </p>
                            @if ($teaPendientes !== [])
                                <x-tea-aviso-pendiente
                                    destacado
                                    :matricula="(int) $matricula->id"
                                    :curso="(int) ($matricula->idCursos ?? 0) ?: null"
                                    :pendientes="$teaPendientes"
                                />
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            {{ $matricula->curso?->nombreParaListado() ?? '—' }} · Matrícula #{{ $matricula->id }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if (tenantSecretariaInformeInasistenciasHabilitada())
                        <x-pdf-post
                            :action="route('seguimiento.inasistencias.informe.pdf')"
                            :fields="array_filter([
                                'matricula' => $matricula->id,
                                'tipo' => $tipoFiltroActivo,
                                'desde' => $fechaDesdeFiltro ?: null,
                                'hasta' => $fechaHastaFiltro ?: null,
                            ])"
                            button-class="btn-secondary btn-sm">
                            Informe PDF
                        </x-pdf-post>
                        @endif
                        <x-nav-contexto-estudiante
                            destino="seguimiento.inasistencias.create"
                            :alcance="\App\Support\Navegacion\ContextoEstudianteSesion::SEGUIMIENTO_INASISTENCIAS"
                            :matricula="$matricula->id"
                            class="inline">
                            <span class="btn-primary btn-sm">+ Nueva inasistencia</span>
                        </x-nav-contexto-estudiante>
                    </div>
                </div>
            </div>

            <div class="border-b border-accent-200 bg-white px-5 py-3">
                <div class="flex flex-wrap items-end gap-x-3 gap-y-2">
                    <div class="w-[9.25rem] shrink-0">
                        <label for="se-inas-desde" class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-neutral-500">Desde</label>
                        <input id="se-inas-desde"
                               type="date"
                               wire:model.live="fechaDesdeFiltro"
                               min="{{ $fechaMinimaFiltro }}"
                               max="{{ $fechaMaximaFiltro }}"
                               class="form-input w-full rounded-lg px-2 py-1.5 text-xs text-neutral-700">
                    </div>
                    <div class="w-[9.25rem] shrink-0">
                        <label for="se-inas-hasta" class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-neutral-500">Hasta</label>
                        <input id="se-inas-hasta"
                               type="date"
                               wire:model.live="fechaHastaFiltro"
                               min="{{ $fechaMinimaFiltro }}"
                               max="{{ $fechaMaximaFiltro }}"
                               class="form-input w-full rounded-lg px-2 py-1.5 text-xs text-neutral-700">
                    </div>
                    <div class="w-[11.5rem] min-w-[9.5rem] shrink-0 sm:w-44">
                        <label for="se-inas-tipo" class="mb-0.5 block text-[10px] font-medium uppercase tracking-wide text-neutral-500">Tipo</label>
                        <select id="se-inas-tipo"
                                wire:model.live="idTipoFiltro"
                                class="form-select w-full rounded-lg px-2 py-1.5 text-xs text-neutral-700">
                            <option value="">— Todos —</option>
                            @foreach ($tiposInasistencia as $tipo)
                                <option value="{{ $tipo->id }}">{{ $tipo->concepto }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if ($filtroFechasActivo || $tipoFiltroActivo)
                    <p class="mt-1.5 text-[11px] leading-snug text-neutral-500">
                        @if ($filtroFechasActivo)
                            Período: {{ $etiquetaPeriodoFiltro }}@if ($tipoFiltroActivo)<span class="text-neutral-400"> · </span>@endif
                        @endif
                        @if ($tipoFiltroActivo)
                            {{ trim($etiquetaTipoFiltro, '()') }}
                        @endif
                    </p>
                @endif
            </div>

            @if ($resumen)
                <div class="space-y-2 border-b border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Justificadas</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-primary-700 sm:text-lg">{{ $resumen->formatear($resumen->justificadas) }}</p>
                        </div>
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Injustificadas</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-neutral-800 sm:text-lg">{{ $resumen->formatear($resumen->injustificadas) }}</p>
                        </div>
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Lleg. tarde 1/4</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-neutral-800 sm:text-lg">{{ $resumen->formatear($resumen->llegadasTardeCuarto) }}</p>
                        </div>
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Lleg. tarde 1/2</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-neutral-800 sm:text-lg">{{ $resumen->formatear($resumen->llegadasTardeMedio) }}</p>
                        </div>
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Retiro anticipado</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-neutral-800 sm:text-lg">{{ $resumen->formatear($resumen->retirosAnticipados) }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 lg:max-w-md">
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Total</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-neutral-900 sm:text-lg">{{ $resumen->formatear($resumen->totalClase()) }}</p>
                        </div>
                        <div class="rounded-xl border border-accent-200 bg-white px-2.5 py-2 shadow-sm">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Educación física</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-neutral-800 sm:text-lg">{{ $resumen->formatear($resumen->educacionFisica) }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <table class="min-w-[680px] border-collapse sm:min-w-full">
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header w-28">Fecha</th>
                                <th class="table-header w-48">Tipo</th>
                                <th class="table-header w-24">Cantidad</th>
                                <th class="table-header w-28">Justificada</th>
                                <th class="table-header">Observaciones</th>
                                <th class="table-header w-44 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @forelse ($inasistencias as $i)
                                <tr class="transition-colors hover:bg-accent-50/60">
                                    <td class="table-cell font-mono">{{ $i->fecha?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="table-cell">{{ $i->etiquetaTipo() }}</td>
                                    <td class="table-cell font-mono">
                                        @if ($i->cantidad !== null)
                                            {{ number_format((float) $i->cantidad, 2, ',', '') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        @if (($i->just ?? '') === 'J')
                                            <span class="se-pill bg-primary-50 text-primary-800">Sí</span>
                                        @elseif (strtoupper(trim((string) ($i->just ?? ''))) === 'I')
                                            <span class="se-pill bg-neutral-100 text-neutral-700">No</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        <div class="line-clamp-2">{{ $i->obs ?? '—' }}</div>
                                    </td>
                                    <td class="table-cell whitespace-nowrap">
                                        <div class="flex flex-nowrap items-center justify-end gap-1.5">
                                            <a class="btn-secondary btn-sm shrink-0" href="{{ route('seguimiento.inasistencias.edit', ['id' => $i->id]) }}">
                                                Editar
                                            </a>
                                            <button type="button" wire:click="confirmDelete({{ $i->id }})" class="btn-danger btn-sm shrink-0">
                                                Borrar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="table-cell py-12 text-center text-sm text-neutral-500">
                                        @if ($tipoFiltroActivo && $filtroFechasActivo)
                                            Sin inasistencias de este tipo en el período seleccionado.
                                        @elseif ($tipoFiltroActivo)
                                            Sin inasistencias de este tipo en el año actual.
                                        @elseif ($filtroFechasActivo)
                                            Sin inasistencias en el período seleccionado.
                                        @else
                                            Sin inasistencias registradas para esta matrícula en el año actual.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="se-card px-5 py-10">
            <p class="text-center text-sm text-neutral-600">
                Seleccioná un curso y un alumno para ver y gestionar sus inasistencias del año actual.
            </p>
        </div>
    @endif

    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl border border-accent-200 bg-white shadow-xl" @click.stop>
                <div class="border-b border-accent-200 px-6 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="mb-1 text-base font-semibold text-neutral-900">Confirmar borrado</h3>
                            <p class="text-sm text-neutral-600">{{ $deleteInfo }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-accent-200 bg-accent-50/60 px-6 py-4">
                    <button type="button" wire:click="$set('showDeleteConfirm', false)" class="btn-secondary">Cancelar</button>
                    <button type="button" wire:click="delete" wire:loading.attr="disabled" class="btn-danger">
                        <span wire:loading.remove wire:target="delete">Borrar</span>
                        <span wire:loading wire:target="delete">Borrando…</span>
                    </button>
                </div>
            </div>
            </div>
    @endif
</div>
