<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-3">
                <p class="se-eyebrow">Viajes / Salidas educativas</p>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Generar Excel Viaje</h2>
                    <p class="mt-2 max-w-2xl text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
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
                    Elija uno o más cursos. Luego podrá incluir o excluir alumnos antes de generar el Excel.
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
                                        <th class="px-4 py-3 text-left">Madre / responsable</th>
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
                                            <td class="px-4 py-2.5 text-neutral-600">
                                                {{ trim((string) ($alumno->nombremad ?? '')) }}
                                                @if (trim((string) ($alumno->telemad ?? '')) !== '' || trim((string) ($alumno->dnimad ?? '')) !== '')
                                                    <span class="text-neutral-400">·</span>
                                                    @if (trim((string) ($alumno->telemad ?? '')) !== '')
                                                        Tel: {{ trim((string) $alumno->telemad) }}
                                                    @endif
                                                    @if (trim((string) ($alumno->dnimad ?? '')) !== '')
                                                        DNI: {{ trim((string) $alumno->dnimad) }}
                                                    @endif
                                                @endif
                                            </td>
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
            <p class="text-sm text-neutral-500">
                El campo de adulto responsable busca datos cargados en Madre (Responsable 1), Padre (Responsable 2) y Tutor, en ese orden.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ $this->excelUrl }}"
                   @class([
                       'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'bg-primary-600 text-white hover:bg-primary-700' => $this->puedeGenerarExport(),
                       'pointer-events-none bg-neutral-200 text-neutral-400' => ! $this->puedeGenerarExport(),
                   ])>
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Generar Excel
                </a>
                <a href="{{ $this->pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   @class([
                       'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold shadow-sm transition-colors',
                       'border border-accent-200 bg-white text-primary-700 hover:bg-accent-50' => $this->puedeGenerarPdf(),
                       'pointer-events-none border border-neutral-200 bg-neutral-100 text-neutral-400' => ! $this->puedeGenerarPdf(),
                   ])>
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Generar PDF
                </a>
                @if (! $this->puedeGenerarExport())
                    <span class="text-sm text-neutral-500">Incluya al menos un alumno para descargar.</span>
                @endif
            </div>
        </div>
    @endif
</div>
