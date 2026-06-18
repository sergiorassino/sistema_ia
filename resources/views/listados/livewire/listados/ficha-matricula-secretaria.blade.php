<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Estudiantes</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $etiqueta }}</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                        @if ($implementacion === 'sanfranciscoasis')
                            · Formato con aceptación de documentos
                        @elseif ($implementacion === 'montecristo')
                            · Formato solicitud de matrícula (solo datos)
                        @endif
                    </p>
                </div>
            </div>

            @if ($paso === 2)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                        <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Incluidos</span>
                        <span class="text-xl font-bold tabular-nums">{{ $totalIncluidos }} / {{ $totalAlumnos }}</span>
                    </span>
                </div>
            @endif
        </div>
    </section>

    @if ($cursos->isEmpty())
        <div class="se-card p-8">
            <p class="text-sm font-semibold text-neutral-800">Sin cursos en este contexto</p>
            <p class="mt-1 text-sm text-neutral-600">No hay cursos cargados para el nivel y año lectivo activos.</p>
        </div>
    @elseif ($paso === 1)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">1. Seleccionar cursos</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Elija uno o más cursos. Luego podrá incluir o excluir alumnos antes de imprimir las fichas.
                </p>
            </div>

            <div class="grid gap-2 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cursos as $curso)
                    <label class="flex items-center gap-2 rounded-xl border border-accent-200 bg-white px-3 py-2.5 text-sm transition-colors hover:border-primary-400">
                        <input type="checkbox"
                               wire:model="cursosSeleccionados"
                               value="{{ $curso->Id }}"
                               class="form-checkbox h-4 w-4 shrink-0 text-primary-600">
                        <span class="truncate font-medium text-neutral-800">{{ $curso->nombreParaListado() }}</span>
                    </label>
                @endforeach
            </div>

            @error('cursosSeleccionados')
                <p class="px-5 pb-4 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap gap-3 border-t border-accent-200 bg-accent-50 px-5 py-4">
                <button type="button"
                        wire:click="continuarConAlumnos"
                        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
                    Continuar con alumnos
                </button>
            </div>
        </div>
    @else
        <div class="se-toolbar flex-wrap gap-3">
            <button type="button"
                    wire:click="volverACursos"
                    class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                ← Volver a cursos
            </button>
            <button type="button"
                    wire:click="marcarTodosAlumnos"
                    class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-accent-50">
                Marcar todos
            </button>
            <button type="button"
                    wire:click="desmarcarTodosAlumnos"
                    class="inline-flex items-center justify-center rounded-xl border border-accent-200 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-accent-50">
                Desmarcar todos
            </button>
        </div>

        @if ($alumnos->isEmpty())
            <div class="se-card p-8">
                <p class="text-sm font-semibold text-neutral-800">Sin alumnos regulares</p>
                <p class="mt-1 text-sm text-neutral-600">Los cursos seleccionados no tienen matriculados activos.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($cursosConAlumnos as $curso)
                    @php
                        $filasCurso = $alumnosPorCurso->get((string) (int) $curso->Id, collect());
                    @endphp
                    <div class="se-card overflow-hidden">
                        <div class="border-b border-accent-200 bg-white px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="se-section-title">{{ $curso->nombreParaListado() }}</p>
                                <span class="se-pill tabular-nums">{{ $filasCurso->count() }} alumno(s)</span>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-accent-50 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                                    <tr>
                                        <th class="w-12 px-4 py-3 text-left">Incl.</th>
                                        <th class="px-4 py-3 text-left">Apellido y nombres</th>
                                        <th class="px-4 py-3 text-left">DNI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-100">
                                    @foreach ($filasCurso as $alumno)
                                        @php
                                            $mid = (string) (int) $alumno->matricula_id;
                                        @endphp
                                        <tr class="hover:bg-accent-50/60">
                                            <td class="px-4 py-2.5">
                                                <input type="checkbox"
                                                       wire:model.live="alumnosIncluidos.{{ $mid }}"
                                                       class="form-checkbox h-4 w-4 text-primary-600">
                                            </td>
                                            <td class="px-4 py-2.5 font-medium text-neutral-800">
                                                {{ \App\Support\Listados\EstudiantesDatosConsulta::formatearApellidoNombre($alumno->apellido ?? '', $alumno->nombre ?? '') }}
                                            </td>
                                            <td class="px-4 py-2.5 tabular-nums text-neutral-700">{{ trim((string) ($alumno->dni ?? '')) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="se-card mt-4 p-5 sm:p-6">
            <p class="text-sm text-neutral-600">
                Puede generar un PDF único con todas las fichas incluidas o descargar un ZIP con un PDF por alumno
                (máximo {{ \App\Support\Alumnos\FichaMatriculaSecretariaLoteParams::MAX_MATRICULAS }} por descarga).
                Cada PDF se nombra como (curso y sección)_(nivel)_(apellido_nombre_compuesto)_fichaMatr.pdf
                y el ZIP como (curso y sección)_(nivel)_fichasMatr.zip.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="{{ $this->pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   @class([
                       'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'bg-primary-600 text-white hover:bg-primary-700' => $this->puedeGenerarPdf(),
                       'pointer-events-none bg-neutral-200 text-neutral-400' => ! $this->puedeGenerarPdf(),
                   ])>
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir fichas PDF
                </a>
                <a href="{{ $this->zipUrl }}"
                   @class([
                       'inline-flex items-center justify-center rounded-xl border px-5 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'border-primary-200 bg-white text-primary-700 hover:bg-accent-50' => $this->puedeGenerarZip(),
                       'pointer-events-none border-neutral-200 bg-neutral-100 text-neutral-400' => ! $this->puedeGenerarZip(),
                   ])>
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Descargar ZIP (un PDF por alumno)
                </a>
                @if (! $this->puedeGenerarPdf())
                    <span class="text-sm text-neutral-500">Incluya al menos un alumno para generar las fichas.</span>
                @endif
            </div>
        </div>
    @endif
</div>
