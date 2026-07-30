<div class="mx-auto w-full max-w-4xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Horarios</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Impresión de horarios</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    PDF A4 apaisado: una hoja por turno y por curso o docente elegido. Podés marcar varios en el mismo PDF.
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                Volver al panel
            </a>
        </div>
    </section>

    <div class="se-card px-5 py-5 space-y-4">
        <p class="se-section-title">Tipo de listado</p>
        <div class="flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input type="radio" wire:model.live="modo" value="curso" class="text-primary-600 focus:ring-primary-500">
                Por curso
            </label>
            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input type="radio" wire:model.live="modo" value="profesor" class="text-primary-600 focus:ring-primary-500">
                Por docente
            </label>
        </div>

        @if ($modo === 'curso')
            <div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="se-section-title">Cursos</span>
                        <p class="mt-1 text-xs text-neutral-600">
                            Marcá uno o más cursos. Cada curso genera sus hojas por turno en el mismo PDF.
                        </p>
                    </div>
                    @if ($cursos->isNotEmpty())
                        <span class="se-pill tabular-nums">
                            {{ $cantidadCursosSeleccionados }} de {{ $cursos->count() }} seleccionados
                        </span>
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button"
                            wire:click="abrirModalCurso"
                            @disabled($cursos->isEmpty())
                            class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        Elegir cursos…
                    </button>
                    @if ($cursos->isEmpty())
                        <span class="text-xs text-neutral-500">No hay cursos en este contexto.</span>
                    @elseif ($cantidadCursosSeleccionados > 0)
                        <button type="button"
                                wire:click="quitarTodosCursos"
                                class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50">
                            Quitar todos
                        </button>
                    @endif
                </div>

                @if (! empty($cursosSeleccionados))
                    <div class="mt-3 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Cursos seleccionados</p>
                        <div class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-neutral-800">
                            @foreach ($cursosSeleccionados as $c)
                                <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                    <span class="break-words">{{ $c['label'] }}</span>
                                    <button type="button"
                                            wire:click="removeCurso({{ $c['id'] }})"
                                            class="shrink-0 text-neutral-400 hover:text-red-600"
                                            title="Quitar">×</button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="se-section-title">Docentes</span>
                        <p class="mt-1 text-xs text-neutral-600">
                            Marcá uno o más docentes. Cada uno genera sus hojas por turno en el mismo PDF.
                        </p>
                    </div>
                    @if ($profesores->isNotEmpty())
                        <span class="se-pill tabular-nums">
                            {{ $cantidadProfesoresSeleccionados }} de {{ $profesores->count() }} seleccionados
                        </span>
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button"
                            wire:click="abrirModalProfesor"
                            @disabled($profesores->isEmpty())
                            class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        Elegir docentes…
                    </button>
                    @if ($profesores->isEmpty())
                        <span class="text-xs text-neutral-500">No hay docentes con asignaciones en este ciclo.</span>
                    @elseif ($cantidadProfesoresSeleccionados > 0)
                        <button type="button"
                                wire:click="quitarTodosProfesores"
                                class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50">
                            Quitar todos
                        </button>
                    @endif
                </div>

                @if (! empty($profesoresSeleccionados))
                    <div class="mt-3 rounded-lg border border-accent-200 bg-white px-2 py-1.5">
                        <p class="text-[9px] font-semibold uppercase tracking-wide text-neutral-500">Docentes seleccionados</p>
                        <div class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-neutral-800">
                            @foreach ($profesoresSeleccionados as $d)
                                <span class="mr-2 inline-flex max-w-full items-baseline gap-0.5 align-top">
                                    <span class="break-words">{{ $d['label'] }}</span>
                                    <button type="button"
                                            wire:click="removeProfesor({{ $d['id'] }})"
                                            class="shrink-0 text-neutral-400 hover:text-red-600"
                                            title="Quitar">×</button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if (count($turnosPdf) > 1)
            <div class="border-t border-accent-100 pt-4">
                <label for="imp-turno-pdf" class="se-section-title">Turno del PDF (opcional)</label>
                <select id="imp-turno-pdf" wire:model.live="pdfTurnoClase" class="form-select mt-2 w-full max-w-md">
                    <option value="">Automático según curso o docente</option>
                    @foreach ($turnosPdf as $tid)
                        <option value="{{ $tid }}">{{ \App\Support\HorariosProfesores::nombreTurnoClase($tid) }}</option>
                    @endforeach
                </select>
                <p class="mt-2 max-w-xl text-xs text-neutral-600">
                    Si el PDF de un turno sale vacío o con horas que no corresponden, el curso puede no tener bien definido el turno en <strong>ABM → Cursos</strong>.
                    Elija aquí el turno del PDF para forzar reloj y filtro, o asigne el turno al curso en ABM (campo enlazado a <strong>turnos_clase</strong>).
                </p>
            </div>
        @endif
    </div>

    @if ($pdfUrl)
        <div class="se-card px-5 py-5">
            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                Abrir PDF del horario
            </a>
            @if ($modo === 'curso' && $cantidadCursosSeleccionados > 1)
                <p class="mt-2 text-xs text-neutral-600">
                    Un solo PDF con {{ $cantidadCursosSeleccionados }} cursos (cada uno con sus hojas por turno).
                </p>
            @elseif ($modo === 'profesor' && $cantidadProfesoresSeleccionados > 1)
                <p class="mt-2 text-xs text-neutral-600">
                    Un solo PDF con {{ $cantidadProfesoresSeleccionados }} docentes (cada uno con sus hojas por turno).
                    @if ($cantidadProfesoresSeleccionados > 15 && count($turnosPdf) > 1 && ! $pdfTurnoClase)
                        Para listas grandes, conviene elegir un <strong>turno del PDF</strong> arriba (menos páginas y más estable).
                    @endif
                </p>
            @endif
        </div>
    @endif

    @if (tienePermiso(\App\Support\PermisosIaCatalog::HORARIOS))
        <div class="flex flex-wrap gap-3 text-sm">
            <a href="{{ route('horarios.carga') }}" class="font-semibold text-primary-700 hover:underline">Carga</a>
            <a href="{{ route('horarios.config') }}" class="font-semibold text-primary-700 hover:underline">Configuración</a>
        </div>
    @endif

    @teleport('body')
        <div>
            @if ($modalCursoAbierto)
                <div class="fixed inset-0 z-[90] flex items-center justify-center px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
                     aria-labelledby="hor-modal-curso-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalCurso"></div>

                    <div class="relative z-10 flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),30rem)]">
                        <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                            <p id="hor-modal-curso-titulo" class="text-sm font-bold text-neutral-900">Elegir cursos</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">
                                Marcá uno o varios cursos del ciclo lectivo actual. Las selecciones fuera de la vista se mantienen al confirmar.
                            </p>
                        </div>

                        <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                            <label for="hor-modal-curso-filtro" class="form-label">Filtrar por nombre</label>
                            <input id="hor-modal-curso-filtro"
                                   type="text"
                                   wire:model.live.debounce.300ms="modalCursoFiltro"
                                   placeholder="Texto del curso…"
                                   class="form-input mt-1.5" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button"
                                        wire:click="modalCursoSeleccionarTodosVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                                    Marcar visibles
                                </button>
                                <button type="button"
                                        wire:click="modalCursoQuitarVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                                    Desmarcar visibles
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                            @forelse ($modalCursoLista as $c)
                                <label wire:key="hor-modal-curso-{{ $c['id'] }}"
                                       class="flex cursor-pointer items-center gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                                    <input type="checkbox"
                                           wire:model="modalCursoMarcados"
                                           value="{{ $c['id'] }}"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                    <span class="text-sm font-semibold leading-tight text-neutral-900">{{ $c['label'] }}</span>
                                </label>
                            @empty
                                <p class="py-8 text-center text-sm text-neutral-500">No hay cursos que coincidan con el filtro.</p>
                            @endforelse
                        </div>

                        <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                            <button type="button"
                                    wire:click="cerrarModalCurso"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="aplicarModalCurso"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                                Aplicar selección
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($modalProfesorAbierto)
                <div class="fixed inset-0 z-[90] flex items-center justify-center px-4 py-3 sm:px-6 sm:py-4" role="dialog" aria-modal="true"
                     aria-labelledby="hor-modal-prof-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalProfesor"></div>

                    <div class="relative z-10 flex w-full max-w-xl max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),34rem)]">
                        <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                            <p id="hor-modal-prof-titulo" class="text-sm font-bold text-neutral-900">Elegir docentes</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">
                                Marcá uno o varios docentes con asignación en el ciclo actual.
                            </p>
                        </div>

                        <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                            <label for="hor-modal-prof-filtro" class="form-label">Filtrar listado</label>
                            <input id="hor-modal-prof-filtro"
                                   type="text"
                                   wire:model.live.debounce.400ms="modalProfesorFiltro"
                                   placeholder="Apellido o nombre…"
                                   class="form-input mt-1.5" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button"
                                        wire:click="modalProfesorSeleccionarTodosVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 transition hover:bg-accent-50">
                                    Marcar visibles
                                </button>
                                <button type="button"
                                        wire:click="modalProfesorQuitarVisibles"
                                        class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-accent-50">
                                    Desmarcar visibles
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1 sm:px-5">
                            @forelse ($modalProfesorLista as $p)
                                <label wire:key="hor-modal-prof-{{ $p['id'] }}"
                                       class="flex cursor-pointer items-center gap-2 border-b border-accent-100 py-1 last:border-b-0 hover:bg-accent-50/60">
                                    <input type="checkbox"
                                           wire:model="modalProfesorMarcados"
                                           value="{{ $p['id'] }}"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                    <span class="text-sm font-semibold leading-tight text-neutral-900">{{ $p['label'] }}</span>
                                </label>
                            @empty
                                <p class="py-8 text-center text-sm text-neutral-500">No hay docentes que coincidan con el filtro.</p>
                            @endforelse
                        </div>

                        <div class="flex flex-col gap-2 border-t border-accent-200 bg-accent-50/40 px-4 py-2.5 sm:flex-row sm:justify-end sm:px-5 sm:py-3">
                            <button type="button"
                                    wire:click="cerrarModalProfesor"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-accent-50 sm:w-auto">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="aplicarModalProfesor"
                                    class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 sm:w-auto">
                                Aplicar selección
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endteleport
</div>
