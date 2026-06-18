<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Listados</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        Listados de Estudiantes con Formato
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Cursos en el colegio</span>
                    <span class="text-xl font-bold tabular-nums">{{ $cursos->count() }}</span>
                </span>
                @if ($cursos->isNotEmpty())
                    <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Seleccionados para PDF</span>
                        <span class="text-xl font-bold tabular-nums">{{ count($cursosElegidos) }}</span>
                    </span>
                @endif
            </div>
        </div>
    </section>

    @if ($cursos->isEmpty())
        <div class="se-card p-8">
            <div class="flex flex-col items-center justify-center gap-4 text-center sm:flex-row sm:text-left">
                <div class="se-icon-badge h-14 w-14">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <div class="max-w-md">
                    <p class="text-sm font-semibold text-neutral-800">Sin cursos en este contexto</p>
                    <p class="mt-1 text-sm text-neutral-600">No hay cursos cargados para el nivel y año lectivo activos.</p>
                </div>
            </div>
        </div>
    @else
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-700">
                        Elija los cursos que incluirá en el PDF (un listado por curso).
                    </p>
                    <span class="se-pill shrink-0 tabular-nums">
                        {{ $cantidadSeleccionados }} de {{ $cursos->count() }} seleccionados
                    </span>
                </div>
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="filtro-cursos-formato" class="form-label">Buscar curso</label>
                <input id="filtro-cursos-formato"
                       type="search"
                       wire:model.live.debounce.300ms="filtroCursos"
                       placeholder="Nivel o nombre del curso…"
                       class="form-input max-w-xl" />
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button"
                            wire:click="seleccionarTodosCursos"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                        Todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodosCursos"
                            class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                        Ninguno
                    </button>
                </div>
            </div>

            @if ($cursosPorNivel === [])
                <div class="px-4 py-6 text-sm text-neutral-600 sm:px-5">
                    @if (trim($filtroCursos) !== '')
                        Ningún curso coincide con la búsqueda.
                    @else
                        No hay cursos para mostrar.
                    @endif
                </div>
            @else
                @php
                    $cantidadNiveles = count($cursosPorNivel);
                @endphp
                <div class="max-h-[min(65dvh,36rem)] overflow-y-auto px-3 py-2 sm:px-4">
                    <div @class([
                        'mx-auto w-full max-w-md' => $cantidadNiveles === 1,
                        'overflow-x-auto' => $cantidadNiveles > 1,
                    ])>
                        <div @class([
                            'grid w-full gap-3' => $cantidadNiveles === 1,
                            'grid min-w-full gap-3' => $cantidadNiveles > 1,
                        ])
                             @if ($cantidadNiveles > 1)
                                 style="grid-template-columns: repeat({{ $cantidadNiveles }}, minmax(12.5rem, 1fr));"
                             @endif>
                            @foreach ($cursosPorNivel as $bloqueNivel)
                                <section wire:key="formato-nivel-{{ $bloqueNivel['idNivel'] }}"
                                         class="flex min-w-0 flex-col rounded-lg border border-accent-200/90 bg-accent-50/20">
                                    <div class="border-b border-accent-200/80 px-2.5 py-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide leading-snug text-primary-800">
                                            {{ $bloqueNivel['nivelNombre'] }}
                                            <span class="font-normal text-neutral-500 tabular-nums">
                                                ({{ $bloqueNivel['seleccionados'] }}/{{ $bloqueNivel['total'] }})
                                            </span>
                                        </p>
                                        <div class="mt-1.5 flex flex-col gap-1">
                                            <button type="button"
                                                    wire:click="marcarNivel({{ $bloqueNivel['idNivel'] }})"
                                                    class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold leading-snug text-primary-800 hover:bg-accent-50">
                                                Marcar nivel
                                            </button>
                                            <button type="button"
                                                    wire:click="quitarNivel({{ $bloqueNivel['idNivel'] }})"
                                                    class="inline-flex w-full justify-center rounded-md border border-accent-200 bg-white px-2 py-1 text-xs font-semibold leading-snug text-neutral-600 hover:bg-accent-50">
                                                Quitar nivel
                                            </button>
                                        </div>
                                    </div>
                                    <ul class="min-h-0 flex-1 list-none divide-y divide-accent-100/80 px-2 py-1">
                                        @foreach ($bloqueNivel['cursos'] as $cursoItem)
                                            <li wire:key="formato-curso-{{ $cursoItem['id'] }}">
                                                <label class="flex cursor-pointer items-center gap-2 py-1.5 transition-colors hover:bg-accent-50/70">
                                                    <input type="checkbox"
                                                           wire:model.live="cursosElegidos"
                                                           value="{{ $cursoItem['id'] }}"
                                                           class="h-4 w-4 shrink-0 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                                    <span class="min-w-0 flex-1 text-sm leading-normal text-neutral-800">
                                                        {{ $cursoItem['etiqueta'] }}
                                                    </span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if ($cantidadSeleccionados > 0)
                <div class="border-t border-accent-200 bg-accent-50/40 px-4 py-3 sm:px-5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados para PDF</p>
                    <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($cursosSeleccionadosResumen as $chip)
                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium leading-snug text-neutral-800">
                                <span class="truncate">{{ $chip['label'] }}</span>
                                <button type="button"
                                        wire:click="quitarCurso({{ $chip['id'] }})"
                                        class="shrink-0 text-neutral-400 hover:text-red-600"
                                        title="Quitar"
                                        aria-label="Quitar {{ $chip['label'] }}">×</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">Modelo de listado</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Elija un formato preconfigurado. El PDF incluye estudiantes regulares matriculados, ordenados por apellido y nombre.
                </p>
            </div>

            <div class="space-y-4 px-5 py-5">
                @foreach ($modelos as $item)
                    <label @class([
                        'flex cursor-pointer flex-col gap-2 rounded-2xl border p-4 transition-colors sm:flex-row sm:items-start sm:gap-4',
                        'border-primary-500 bg-primary-50/40 ring-1 ring-primary-200' => $modelo === $item['key'],
                        'border-accent-200 bg-white hover:border-accent-300 hover:bg-accent-50/40' => $modelo !== $item['key'],
                    ])>
                        <input type="radio"
                               name="modelo-listado-formato"
                               value="{{ $item['key'] }}"
                               wire:model.live="modelo"
                               class="mt-1 h-4 w-4 shrink-0 border-accent-300 text-primary-600 focus:ring-primary-500" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-neutral-900">{{ $item['label'] }}</span>
                            <span class="mt-1 block text-sm text-neutral-600">{{ $item['descripcion'] }}</span>
                        </span>
                    </label>
                @endforeach

                @if ($modelo === \App\Support\Listados\ListadoEstudiantesFormatoCatalog::MODELO_CALENDARIO)
                    <div class="rounded-2xl border border-accent-200 bg-accent-50/50 p-4">
                        <label for="mes-listado-formato" class="form-label">
                            Mes a imprimir <span class="text-red-600">*</span>
                        </label>
                        <select id="mes-listado-formato"
                                wire:model.live="mes"
                                class="form-input max-w-xs">
                            <option value="0">Seleccione un mes…</option>
                            @foreach ($meses as $opcionMes)
                                <option value="{{ $opcionMes['valor'] }}">{{ $opcionMes['etiqueta'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-neutral-500">
                            Año del ciclo lectivo activo: {{ schoolCtx()->terlecAno() ?? '—' }}.
                            Los sábados y domingos se marcan en gris en la grilla.
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 border-t border-accent-200 bg-accent-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ $this->pdfUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       @class([
                           'btn-primary',
                           'pointer-events-none opacity-50' => ! $this->puedeGenerarPdf(),
                       ])
                       @if (! $this->puedeGenerarPdf()) tabindex="-1" aria-disabled="true" @endif>
                        Abrir PDF en pestaña nueva
                    </a>
                    @if (! $this->puedeGenerarPdf())
                        <span class="text-sm text-neutral-500">
                            @if ($cantidadSeleccionados < 1)
                                Marque al menos un curso.
                            @elseif ($modelo === \App\Support\Listados\ListadoEstudiantesFormatoCatalog::MODELO_CALENDARIO && (int) $mes < 1)
                                Seleccione el mes a imprimir.
                            @endif
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
