<div>
    <div class="se-page max-w-6xl">
        <section class="se-hero">
            <div class="se-hero-inner">
                <div class="min-w-0 space-y-2">
                    <p class="se-eyebrow">Horarios</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Profesores presentes</h2>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                    </p>
                </div>
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="text-sm text-neutral-700">
                    Listado de docentes con hora cátedra en el día y franja elegidos, para los cursos y secciones
                    seleccionados. Cada docente aparece una sola vez, con el horario en el que está presente ese día.
                </p>
            </div>

            <div class="bg-white px-5 py-4 space-y-6">
                <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="se-ppres-dia" class="form-label">Día de la semana</label>
                        <select id="se-ppres-dia" wire:model.live="dia" class="form-select mt-1.5">
                            @foreach ($dias as $num => $label)
                                <option value="{{ $num }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="se-ppres-hi" class="form-label">Horario de inicio</label>
                        <input id="se-ppres-hi"
                               type="time"
                               wire:model.live="horaInicio"
                               class="form-input mt-1.5 w-full rounded-xl border border-accent-200 px-3 py-2 text-sm text-neutral-800">
                    </div>
                    <div>
                        <label for="se-ppres-hf" class="form-label">Horario de fin</label>
                        <input id="se-ppres-hf"
                               type="time"
                               wire:model.live="horaFin"
                               class="form-input mt-1.5 w-full rounded-xl border border-accent-200 px-3 py-2 text-sm text-neutral-800">
                    </div>
                </div>

                <div>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="se-section-title">Cursos y secciones</p>
                            <p class="mt-1 text-sm text-neutral-600">
                                Marcá los cursos involucrados. El listado solo incluye docentes de esos cursos.
                            </p>
                        </div>
                        @if ($cursos->isNotEmpty())
                            <span class="se-pill tabular-nums">
                                {{ $cantidadSeleccionados }} de {{ $cursos->count() }} seleccionados
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button"
                                wire:click="abrirModalCurso"
                                @disabled($cursos->isEmpty())
                                class="inline-flex items-center justify-center rounded-xl border border-primary-500 bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            Elegir cursos…
                        </button>
                        @if ($cursos->isEmpty())
                            <span class="text-sm text-neutral-600">No hay cursos en este nivel y ciclo lectivo.</span>
                        @elseif ($cantidadSeleccionados > 0)
                            <button type="button"
                                    wire:click="quitarTodosCursos"
                                    class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
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

                <div class="flex flex-wrap gap-2 border-t border-accent-100 pt-4">
                    <button type="button"
                            wire:click="emitirListado"
                            wire:loading.attr="disabled"
                            @disabled(! $puedeEmitir)
                            class="btn-primary inline-flex items-center gap-2 disabled:cursor-not-allowed disabled:opacity-50">
                        Emitir listado
                    </button>
                    @if ($pdfUrl)
                        <a class="inline-flex items-center gap-2 rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-accent-50"
                           target="_blank"
                           rel="noopener noreferrer"
                           href="{{ $pdfUrl }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Imprimir PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if ($emitido)
            <div class="se-card overflow-hidden">
                <div class="flex flex-wrap items-center justify-center gap-2 border-b border-accent-200 bg-white px-5 py-3 se-toolbar-pocos-campos">
                    <p class="se-section-title">Listado</p>
                    @if ($resultado['ok'] ?? false)
                        <span class="se-pill tabular-nums">
                            {{ (int) ($resultado['cantidadDocentes'] ?? 0) === 1 ? '1 docente' : ((int) ($resultado['cantidadDocentes'] ?? 0)).' docentes' }}
                        </span>
                    @endif
                </div>

                @if (! empty($resultado['error']))
                    <p class="px-5 py-6 text-center text-sm text-red-700">{{ $resultado['error'] }}</p>
                @else
                    <div class="w-full overflow-x-auto se-grid-angosta-wrap px-5 py-3">
                        <table class="se-matriz-list-tabla se-grid-pocos-campos">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-left">Docente</th>
                                    <th scope="col" class="text-left">Horario presente</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($resultado['filas'] ?? []) as $i => $fila)
                                    <tr wire:key="ppres-fila-{{ $i }}">
                                        <td class="font-medium text-neutral-800">{{ $fila['docente'] }}</td>
                                        <td class="tabular-nums text-neutral-700">{{ $fila['horario'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-8 text-center text-sm text-neutral-500">
                                            No hay docentes con clase en ese día, horario y cursos.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @teleport('body')
        <div>
            @if ($modalCursoAbierto)
                <div class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
                     role="dialog" aria-modal="true" aria-labelledby="ppres-modal-curso-titulo">
                    <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm" wire:click="cerrarModalCurso"></div>

                    <div class="relative z-10 my-auto flex w-full max-w-lg max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),30rem)]">
                        <div class="border-b border-accent-200 bg-accent-50/60 px-4 py-2.5 sm:px-5 sm:py-3">
                            <p id="ppres-modal-curso-titulo" class="text-sm font-bold text-neutral-900">Elegir cursos</p>
                            <p class="mt-0.5 text-[11px] leading-snug text-neutral-600">
                                Marcá uno o varios cursos del ciclo lectivo actual. Las selecciones fuera de la vista se mantienen al confirmar.
                            </p>
                        </div>

                        <div class="border-b border-accent-100 bg-white px-4 py-2 sm:px-5 sm:py-2.5">
                            <label for="ppres-modal-curso-filtro" class="form-label">Filtrar por nombre</label>
                            <input id="ppres-modal-curso-filtro"
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
                                <label wire:key="ppres-modal-curso-{{ $c['id'] }}"
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
        </div>
    @endteleport

    @script
    <script>
        $wire.on('se-swal-error', (event) => {
            const mensaje = event?.mensaje ?? event?.[0]?.mensaje ?? 'No se pudo emitir el listado.';
            if (typeof window.seSwalError === 'function') {
                window.seSwalError(mensaje);
            }
        });
    </script>
    @endscript
</div>
