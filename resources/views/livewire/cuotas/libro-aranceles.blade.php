<div class="se-page max-w-5xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Resúmenes</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Libro de aranceles</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Impresión por curso en A4 apaisado
                </p>
            </div>
        </div>
    </section>

    @if ($cursos->isEmpty())
        <div class="se-card p-6 text-sm text-neutral-600">
            No hay cursos cargados para el ciclo lectivo activo.
        </div>
    @else
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-700">
                        Elija los cursos a incluir en el libro. Cada curso comenzará en una página nueva.
                    </p>
                    <span class="se-pill shrink-0 tabular-nums">
                        {{ $cantidadSeleccionados }} de {{ $cursos->count() }} seleccionados
                    </span>
                </div>
            </div>

            <div class="border-b border-accent-200 bg-white px-4 py-3 sm:px-5">
                <label for="filtro-cursos-libro" class="form-label">Buscar curso</label>
                <input id="filtro-cursos-libro"
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
                                <section wire:key="nivel-libro-{{ $bloqueNivel['idNivel'] }}"
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
                                            <li wire:key="curso-libro-{{ $cursoItem['id'] }}">
                                                <label class="flex cursor-pointer items-center gap-2 py-1.5 transition-colors hover:bg-accent-50/70">
                                                    <input type="checkbox"
                                                           wire:model.live="cursosSeleccionados"
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
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Seleccionados</p>
                    <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto">
                        @foreach ($cursosSeleccionadosResumen as $chip)
                            <span class="inline-flex max-w-full items-center gap-1 rounded-lg border border-primary-200 bg-white px-2 py-1 text-xs font-medium leading-snug text-neutral-800">
                                <span class="truncate">{{ $chip['label'] }}</span>
                                <button type="button"
                                        wire:click="quitarCurso({{ $chip['id'] }})"
                                        class="shrink-0 rounded p-0.5 text-neutral-400 transition hover:bg-accent-100 hover:text-neutral-700"
                                        title="Quitar">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="se-card mt-4 overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <p class="text-sm text-neutral-700">Opciones de impresión</p>
            </div>
            <div class="px-4 py-4 sm:px-5">
                <label for="pagina-inicial-libro" class="form-label">Número de página inicial</label>
                <input id="pagina-inicial-libro"
                       type="number"
                       min="1"
                       max="9999"
                       wire:model.live="paginaInicial"
                       class="form-input max-w-xs tabular-nums" />
                <p class="mt-1.5 text-xs text-neutral-500">
                    Indique con qué número de página debe comenzar el PDF (útil para continuar un libro ya impreso).
                </p>
            </div>

            @if ($pdfUrl !== '#')
                <div class="border-t border-accent-200 bg-accent-50/60 px-4 py-4 sm:px-5">
                    <p class="mb-3 text-sm text-neutral-600">
                        Se generará un PDF con {{ $cantidadSeleccionados }}
                        {{ $cantidadSeleccionados === 1 ? 'curso' : 'cursos' }}
                        (cada uno en página nueva, con encabezado institucional).
                    </p>
                    <a href="{{ $pdfUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Imprimir libro de aranceles (PDF)
                    </a>
                </div>
            @else
                <div class="border-t border-accent-200 px-4 py-6 sm:px-5">
                    <p class="text-center text-sm text-neutral-600 sm:text-left">
                        Seleccione al menos un curso para generar el PDF.
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
