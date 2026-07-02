{{-- SFQ — curso → grilla alumnos × informes pedagógicos / Bellas Artes. --}}
@php
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
@endphp
<div>
<div class="se-page mx-auto w-full space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Inicial · SFQ</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de calificaciones</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::urlInicio() }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver
            </a>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="min-w-0 flex-1">
            <label for="se-sfq-ini-curso" class="form-label">Curso</label>
            <select id="se-sfq-ini-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full max-w-xl">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($cursoId)
        <div class="se-card p-0">
            <div class="border-b border-accent-200 bg-primary-600 px-5 py-3 text-center">
                <p class="text-sm font-bold uppercase tracking-wide text-white">Estudiantes del curso</p>
            </div>
            <div class="w-full overflow-x-auto px-3 pb-3 pt-2">
                <div class="gf gf-sfq-carga-ini" style="--se-sfq-col-icon: {{ $anchoColumnaIcono }};">
                    <div class="gf-head">
                        <div class="gf-th gf-th--idx">#</div>
                        <div class="gf-th gf-th--apellido">Apellido</div>
                        <div class="gf-th gf-th--nombre">Nombre</div>
                        <div class="gf-th gf-th--dni">DNI</div>
                        @foreach ($columnasGrilla as $columna)
                            <div class="gf-th gf-th--stack" title="{{ $etiquetasColumna[$columna] ?? $columna }}">
                                @foreach (\App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo::encabezadoColumna($columna) as $linea)
                                    <span class="gf-th-line">{{ $linea }}</span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    @forelse ($filas as $i => $fila)
                        @php($mat = $fila['matricula'])
                        <div class="gf-row" wire:key="sfq-ini-mat-{{ $mat->id }}">
                            <div class="gf-td gf-td--idx">{{ $i + 1 }}</div>
                            <div class="gf-td gf-td--apellido">{{ $mat->legajo?->apellido ?? '—' }}</div>
                            <div class="gf-td gf-td--nombre">{{ $mat->legajo?->nombre ?? '—' }}</div>
                            <div class="gf-td gf-td--dni">{{ $mat->legajo?->dni ?? '—' }}</div>
                            @foreach ($columnasGrilla as $columna)
                                <div class="gf-td gf-td--icon">
                                    @if (\App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo::esColumnaObservacionesGrilla($columna))
                                        <a href="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::route('observaciones', ['matricula' => $mat->id, 'curso' => $cursoId]) }}"
                                           @class([
                                               'inline-flex h-8 w-8 items-center justify-center rounded-lg border shadow-sm transition',
                                               'border-amber-400 bg-amber-50 hover:bg-amber-100' => $fila['observaciones'],
                                               'border-accent-200 bg-white hover:border-amber-400 hover:bg-accent-50' => ! $fila['observaciones'],
                                           ])
                                           title="Observaciones pedagógicas y Bellas Artes">
                                            <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                    @else
                                        <a href="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::route('indicadores', ['matricula' => $mat->id, 'campo' => $columna, 'curso' => $cursoId]) }}"
                                           @class([
                                               'inline-flex h-8 w-8 items-center justify-center rounded-lg border shadow-sm transition',
                                               'border-primary-400 bg-primary-50 hover:bg-primary-100' => $fila['ic'][$columna] ?? false,
                                               'border-accent-200 bg-white hover:border-primary-400 hover:bg-accent-50' => ! ($fila['ic'][$columna] ?? false),
                                           ])
                                           title="{{ $etiquetasColumna[$columna] ?? $columna }}">
                                            @if (\App\Support\CalificacionesInicial\Sfq\CalificacionesInicialSfqCatalogo::esCampoIcPedagogico($columna))
                                                <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                                </svg>
                                            @else
                                                <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4"/>
                                                </svg>
                                            @endif
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="gf-row">
                            <div class="gf-td w-full py-10 text-center text-sm text-neutral-500">
                                No hay matrículas regulares en este curso.
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>

    @include('livewire.partials.modal-carga-notas-off', [
        'modalWireKey' => 'modal-notas-off-ini-index',
        'modalTituloId' => 'modal-notas-off-ini-index-titulo',
    ])
</div>
