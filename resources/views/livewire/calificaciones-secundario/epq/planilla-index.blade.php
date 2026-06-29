{{-- Planilla de calificaciones EPQ secundario — curso, luego materias; una hoja PDF por espacio curricular. --}}

<div class="mx-auto w-full max-w-4xl space-y-6">

    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">{{ $modoPortalDocente ? 'Portal docente · Secundario EPQ' : 'Calificaciones · Secundario EPQ' }}</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Planilla de calificaciones</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            @if ($modoPortalDocente)
                <a href="{{ route('portalDocente.home') }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver al panel
                </a>
            @endif
        </div>
    </section>

    <div class="se-card px-5 py-5">
        <p class="se-section-title">Curso</p>
        <p class="mt-1 text-sm text-neutral-600">
            Elegí el curso. Luego podrás marcar uno, varios o todos los espacios curriculares de ese curso.
        </p>
        <div class="mt-4 max-w-xl">
            <label for="se-epq-sec-planilla-curso" class="form-label">Curso</label>
            <select id="se-epq-sec-planilla-curso"
                    wire:model.live="cursoId"
                    class="form-select mt-1.5 w-full">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
        @if ($cursos->isEmpty())
            <p class="mt-4 text-sm text-neutral-600">No hay cursos disponibles en este ciclo lectivo.</p>
        @endif
    </div>

    @if ($cursoId)
        <div class="se-card px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="se-section-title">Espacios curriculares</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Curso: <span class="font-semibold text-neutral-800">{{ $etiquetaCurso }}</span>.
                        Cada materia marcada genera su propia hoja con informes, cuatrimestres y nota final.
                    </p>
                </div>
                <span class="se-pill tabular-nums">
                    {{ $cantidadSeleccionados }} de {{ $materias->count() }} seleccionados
                </span>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button"
                        wire:click="seleccionarTodasMaterias"
                        @disabled($materias->isEmpty())
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    Todas
                </button>
                <button type="button"
                        wire:click="quitarTodasMaterias"
                        @disabled($materias->isEmpty())
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    Ninguna
                </button>
            </div>

            @if ($materias->isEmpty())
                <p class="mt-4 text-sm text-neutral-600">No hay espacios curriculares disponibles para este curso.</p>
            @else
                <div class="mt-4 rounded-xl border border-accent-200 bg-accent-50/30 p-3 se-grid-angosta-wrap">
                    <div class="flex w-max min-w-[16rem] max-w-full flex-col gap-1">
                        @foreach ($materias as $m)
                            <label class="inline-flex cursor-pointer items-start gap-2.5 rounded-lg border border-transparent px-2 py-1.5 text-sm text-neutral-800 transition hover:border-accent-200 hover:bg-white">
                                <input type="checkbox"
                                       class="mt-0.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                       wire:model.live="materiasSeleccionadas"
                                       value="{{ $m->id }}">
                                <span class="font-medium">{{ $m->materia }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if ($pdfUrl)
            <div class="se-card space-y-4 px-5 py-5">
                <p class="text-sm text-neutral-700">
                    <span class="font-semibold text-neutral-900">{{ $etiquetaCurso }}</span>
                    @if ($etiquetaMaterias !== '')
                        · {{ $etiquetaMaterias }}
                    @endif
                </p>
                <p class="text-sm text-neutral-600">
                    @if ($cantidadSeleccionados > 1)
                        Se generará un PDF con {{ $cantidadSeleccionados }} planillas (una por espacio curricular), en orden de materia.
                    @else
                        Planilla del espacio curricular seleccionado con alumnos que tienen calificaciones cargadas.
                    @endif
                </p>
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir planilla (PDF)
                </a>
            </div>
        @elseif ($materias->isNotEmpty())
            <div class="se-card px-5 py-8">
                <p class="text-center text-sm text-neutral-600 sm:text-left">
                    Seleccioná al menos un espacio curricular para generar la planilla en PDF.
                </p>
            </div>
        @endif
    @elseif ($cursos->isNotEmpty())
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                Seleccioná un curso para ver los espacios curriculares disponibles.
            </p>
        </div>
    @endif

</div>
