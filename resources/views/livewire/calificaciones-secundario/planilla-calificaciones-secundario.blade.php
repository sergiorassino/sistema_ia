{{-- Planilla de calificaciones por curso: una, varias o todas las materias (PDF). --}}
<div class="mx-auto w-full max-w-4xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Secundario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Planilla de calificaciones</h2>
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

    <div class="se-card px-5 py-5">
        <label for="se-planilla-curso" class="se-section-title">Curso</label>
        <select id="se-planilla-curso" wire:model.live="cursoId" class="form-select mt-2 w-full max-w-md">
            <option value="">— Seleccione —</option>
            @foreach ($cursos as $c)
                <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
            @endforeach
        </select>
    </div>

    @if ($cursoId)
        <div class="se-card px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="se-section-title">Materias a incluir</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Marcá una o más materias del curso. Cada materia genera su propia hoja en el mismo PDF.
                    </p>
                </div>
                <span class="se-pill tabular-nums">
                    {{ $cantidadMateriasSeleccionadas }} de {{ $materias->count() }} seleccionadas
                </span>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button"
                        wire:click="seleccionarTodasMaterias"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Todas
                </button>
                <button type="button"
                        wire:click="quitarTodasMaterias"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Ninguna
                </button>
            </div>

            @if ($materias->isEmpty())
                <p class="mt-4 text-sm text-neutral-600">Este curso no tiene materias cargadas en el ciclo lectivo.</p>
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
            <p class="text-sm text-neutral-700">
                <span class="font-semibold text-neutral-900">{{ $cursoLabel ?? '—' }}</span>
                <span class="mx-1.5 text-neutral-400">·</span>
                <span class="font-semibold text-neutral-900">{{ $etiquetaMaterias }}</span>
            </p>
            <p class="text-sm text-neutral-600">
                @if ($cantidadMateriasSeleccionadas > 1)
                    Se generará un PDF con {{ $cantidadMateriasSeleccionadas }} planillas (una hoja A4 por materia), en orden de materia del curso.
                @else
                    Todos los estudiantes del curso entran en <strong>una sola hoja</strong> A4; el alto de fila se ajusta automáticamente.
                @endif
                Los bloques en gris indican módulos desaprobados (ninguna nota del bloque alcanza 7).
                El docente se obtiene de la asignación en profesores por curso (<code class="text-xs">ppc</code>).
            </p>
            <a href="{{ $pdfUrl }}"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir planilla{{ $cantidadMateriasSeleccionadas > 1 ? 's' : '' }} (PDF)
            </a>
        </div>
    @elseif ($cursoId)
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                Seleccioná al menos una materia para generar la planilla en PDF.
            </p>
        </div>
    @else
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                Seleccioná un curso y al menos una materia para generar la planilla en PDF.
            </p>
        </div>
    @endif
</div>
