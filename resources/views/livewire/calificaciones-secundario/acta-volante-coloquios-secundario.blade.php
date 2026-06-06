{{-- Actas volantes de coloquio: una hoja PDF por materia con alumnos elegibles. --}}
<div class="mx-auto w-full max-w-4xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Secundario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Actas volantes de coloquio</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    <div class="se-card px-5 py-5 space-y-4">
        <div>
            <span class="form-label">Período de coloquio</span>
            <div class="mt-2 flex flex-wrap gap-2">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition
                    {{ $campoActivo === 'dic' ? 'border-primary-500 bg-primary-600 text-white' : 'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' }}">
                    <input type="radio" class="sr-only" name="se-acta-periodo" value="dic" wire:model.live="periodo">
                    Diciembre
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition
                    {{ $campoActivo === 'feb' ? 'border-primary-500 bg-primary-600 text-white' : 'border-accent-200 bg-white text-neutral-700 hover:bg-accent-50' }}">
                    <input type="radio" class="sr-only" name="se-acta-periodo" value="feb" wire:model.live="periodo">
                    Febrero
                </label>
            </div>
            <p class="mt-2 text-xs text-neutral-500">
                Condición en el acta: <strong>{{ $condicionLabel }}</strong>.
                En febrero no se incluyen quienes ya aprobaron en diciembre (Dic ≥ 7).
            </p>
        </div>

        <div class="border-t border-accent-200 pt-4">
            <label for="se-acta-curso" class="se-section-title">Curso</label>
            <select id="se-acta-curso" wire:model.live="cursoId" class="form-select mt-2 w-full max-w-md">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($cursoId)
        <div class="se-card px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="se-section-title">Materias a incluir</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Solo se listan materias con alumnos regulares elegibles para rendir coloquio en {{ strtolower($condicionLabel) }}.
                        Se imprime una hoja por cada materia seleccionada.
                    </p>
                </div>
                <span class="se-pill tabular-nums">
                    {{ $cantidadMateriasSeleccionadas }} de {{ $materias->count() }} seleccionadas
                </span>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="seleccionarTodasMaterias"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Todas
                </button>
                <button type="button" wire:click="quitarTodasMaterias"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Ninguna
                </button>
            </div>

            @if ($materias->isEmpty())
                <p class="mt-4 text-sm text-neutral-600">
                    No hay materias con alumnos elegibles para coloquio en este curso y período.
                </p>
            @else
                <div class="mt-4 max-h-72 overflow-y-auto rounded-xl border border-accent-200 bg-accent-50/30 p-3">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($materias as $m)
                            <label class="inline-flex cursor-pointer items-center gap-2.5 rounded-lg border border-transparent px-2 py-1.5 text-sm text-neutral-800 transition hover:border-accent-200 hover:bg-white">
                                <input type="checkbox"
                                       class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                       wire:model.live="materiasSeleccionadas"
                                       value="{{ $m->id }}">
                                <span class="font-medium">{{ trim((string) ($m->materia ?? '')) !== '' ? $m->materia : ('ID ' . $m->id) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($pdfUrl)
        <div class="se-card space-y-4 px-5 py-5">
            <p class="text-sm text-neutral-600">
                @if ($cursoLabel)
                    <strong>{{ $cursoLabel }}</strong> —
                @endif
                Listado de alumnos <strong>regulares</strong> con algún módulo desaprobado o con <strong>TEA</strong> (recuperan todas las materias del curso).
                Las columnas Escrito, Oral y Prom quedan en blanco para completar en el examen.
            </p>
            <a href="{{ $pdfUrl }}"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir actas volantes (PDF)
            </a>
        </div>
    @else
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                @if (! $cursoId)
                    Seleccioná un curso para ver las materias con alumnos elegibles.
                @elseif ($materias->isEmpty())
                    Este curso no tiene materias con alumnos elegibles para coloquio en el período elegido.
                @else
                    Seleccioná al menos una materia para generar las actas volantes en PDF.
                @endif
            </p>
        </div>
    @endif
</div>
