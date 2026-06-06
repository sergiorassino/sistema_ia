<div class="se-page max-w-6xl">
    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)"
             class="se-soft-card flex items-center gap-3 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ session('error') }}
        </div>
    @endif

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
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Calificaciones</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Gestión de Solicitudes de Evaluación</h2>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2 self-start">
                    <button type="button"
                            wire:click="alternarHistorial"
                            wire:loading.attr="disabled"
                            class="btn-secondary btn-sm">
                        <span wire:loading.remove wire:target="alternarHistorial">
                            {{ $mostrarHistorial ? 'Mostrar Futuras' : 'Mostrar Historial' }}
                        </span>
                        <span wire:loading wire:target="alternarHistorial">Cargando…</span>
                    </button>
                    <a href="{{ route('calificacionesSecundario.gestionSolicitudesEvaluacion.create') }}"
                       class="btn-primary btn-sm">
                        + Registrar evaluación
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="se-soft-card mb-4 border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        En este módulo no se revisan la cantidad de evaluaciones por día: si va a hacer cambios, verifique la cantidad de evaluaciones por día que le queda a cada curso.
    </div>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label for="se-gest-eval-filtro-fecha" class="form-label">Fecha</label>
                <input id="se-gest-eval-filtro-fecha" type="date" wire:model.live="filtroFecha"
                       class="form-input mt-1.5">
            </div>
            <div>
                <label for="se-gest-eval-filtro-curso" class="form-label">Curso</label>
                <select id="se-gest-eval-filtro-curso" wire:model.live="filtroIdCurso" class="form-select mt-1.5">
                    <option value="">— Todos —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-gest-eval-filtro-materia" class="form-label">Materia</label>
                <select id="se-gest-eval-filtro-materia" wire:model.live="filtroIdMateria" class="form-select mt-1.5">
                    <option value="">— Todas —</option>
                    @foreach ($materiasFiltro as $m)
                        <option value="{{ $m->id }}">
                            {{ \App\Support\SolicitudEvaluacion\SolicitudEvaluacionConsulta::etiquetaMateriaConCurso($m) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($filtrosActivos)
            <button type="button" wire:click="limpiarFiltros" class="btn-secondary btn-sm shrink-0">
                Limpiar filtros
            </button>
        @endif
    </div>

    @if ($agrupadas->isEmpty())
        <div class="se-soft-card px-5 py-10 text-center">
            <p class="text-sm text-neutral-600">
                @if ($filtrosActivos)
                    No hay evaluaciones que coincidan con los filtros seleccionados.
                @elseif ($mostrarHistorial)
                    No hay evaluaciones registradas para el año lectivo actual.
                @else
                    No hay evaluaciones programadas desde hoy en adelante.
                @endif
            </p>
            <a href="{{ route('calificacionesSecundario.gestionSolicitudesEvaluacion.create') }}"
               class="btn-primary btn-sm mt-4 inline-flex">
                Registrar evaluación
            </a>
        </div>
    @else
        @if (! $mostrarHistorial && ! $filtrosActivos)
            <p class="mb-4 text-xs text-neutral-500">
                Mostrando evaluaciones desde el {{ now()->format('d/m/Y') }} en adelante.
            </p>
        @elseif (! $mostrarHistorial && $filtrosActivos)
            <p class="mb-4 text-xs text-neutral-500">
                Evaluaciones futuras filtradas (desde el {{ now()->format('d/m/Y') }}).
            </p>
        @endif
        <div class="space-y-6">
            @foreach ($agrupadas as $fechaYmd => $evaluaciones)
                @php
                    $fechaLabel = $fechaYmd !== ''
                        ? \Carbon\Carbon::parse($fechaYmd)->format('d/m/Y')
                        : '—';
                @endphp
                <div class="se-gest-eval-dia">
                    <div class="se-gest-eval-dia__fecha">
                        <p class="text-sm font-semibold text-neutral-900">
                            {{ $fechaLabel }}
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-600">
                            {{ $evaluaciones->count() }} {{ $evaluaciones->count() === 1 ? 'evaluación' : 'evaluaciones' }}
                        </p>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <table class="se-gest-eval-dia__tabla min-w-[720px] sm:min-w-full">
                            <thead>
                                <tr>
                                    <th class="table-header w-44">Curso</th>
                                    <th class="table-header w-48">Materia</th>
                                    <th class="table-header">Temas</th>
                                    <th class="table-header w-48">Observaciones</th>
                                    <th class="table-header w-32">Registrado</th>
                                    <th class="table-header w-36 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($evaluaciones as $e)
                                    <tr wire:key="eval-{{ $e->Id }}">
                                        <td class="table-cell font-medium">
                                            {{ $e->curso?->nombreParaListado() ?? ('Curso #'.$e->idCurso) }}
                                        </td>
                                        <td class="table-cell">
                                            {{ $etiquetasMateria[(int) $e->idMateria] ?? ('Materia #'.$e->idMateria) }}
                                        </td>
                                        <td class="table-cell">{{ $e->temas ?: '—' }}</td>
                                        <td class="table-cell">{{ $e->obs ?: '—' }}</td>
                                        <td class="table-cell font-mono text-xs">
                                            {{ $e->fechregi?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="table-cell text-right">
                                            <div class="flex flex-wrap justify-end gap-1.5">
                                                <a href="{{ route('calificacionesSecundario.gestionSolicitudesEvaluacion.edit', $e->Id) }}"
                                                   class="btn-secondary btn-sm">
                                                    Editar
                                                </a>
                                                <button type="button"
                                                        wire:click="confirmDelete({{ (int) $e->Id }})"
                                                        class="btn-danger btn-sm">
                                                    Borrar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($mostrarHistorial && $paginadas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginadas->hasPages())
            <div class="mt-6">
                {{ $paginadas->links('vendor.pagination.se-compact') }}
            </div>
        @endif
    @endif

    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
                <div class="border-b border-accent-200 px-5 py-4">
                    <h3 class="text-lg font-semibold text-neutral-900">Confirmar borrado</h3>
                </div>
                <div class="px-5 py-4">
                    <p class="text-sm text-neutral-700">{{ $deleteInfo }}</p>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50 px-5 py-4">
                    <button type="button" wire:click="$set('showDeleteConfirm', false)" class="btn-secondary">
                        Cancelar
                    </button>
                    <button type="button" wire:click="delete" wire:loading.attr="disabled" class="btn-danger">
                        <span wire:loading.remove wire:target="delete">Borrar</span>
                        <span wire:loading wire:target="delete">Borrando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
