{{-- Planilla resumen por uno o más cursos (todas las materias, PDF). --}}

<div class="mx-auto w-full max-w-4xl space-y-6">

    <section class="se-hero">

        <div class="se-hero-inner">

            <div class="min-w-0 space-y-2">

                <p class="se-eyebrow">Calificaciones · Secundario</p>

                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Planilla resumen de calificaciones</h2>

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

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>

                <p class="se-section-title">Cursos a incluir</p>

                <p class="mt-1 text-sm text-neutral-600">

                    Marcá uno o más cursos. Cada curso genera su propia planilla en el mismo PDF.

                </p>

            </div>

            <span class="se-pill tabular-nums">

                {{ $cantidadSeleccionados }} de {{ $cursos->count() }} seleccionados

            </span>

        </div>



        <div class="mt-4 flex flex-wrap gap-2">

            <button type="button"

                    wire:click="seleccionarTodosCursos"

                    class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">

                Todos

            </button>

            <button type="button"

                    wire:click="quitarTodosCursos"

                    class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">

                Ninguno

            </button>

        </div>



        @if ($cursos->isEmpty())

            <p class="mt-4 text-sm text-neutral-600">No hay cursos en este nivel y ciclo lectivo.</p>

        @else

            <div class="mt-4 max-h-72 overflow-y-auto rounded-xl border border-accent-200 bg-accent-50/30 p-3">

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">

                    @foreach ($cursos as $c)

                        <label class="inline-flex cursor-pointer items-center gap-2.5 rounded-lg border border-transparent px-2 py-1.5 text-sm text-neutral-800 transition hover:border-accent-200 hover:bg-white">

                            <input type="checkbox"

                                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"

                                   wire:model.live="cursosSeleccionados"

                                   value="{{ $c->Id }}">

                            <span class="font-medium">{{ $c->nombreParaListado() }}</span>

                        </label>

                    @endforeach

                </div>

            </div>

        @endif

    </div>



    @if ($pdfUrl)

        <div class="se-card space-y-4 px-5 py-5">

            <p class="text-sm text-neutral-700">

                <span class="font-semibold text-neutral-900">{{ $etiquetaCursos }}</span>

            </p>

            <p class="text-sm text-neutral-600">

                @if ($cantidadSeleccionados > 1)

                    Se generará un PDF con {{ $cantidadSeleccionados }} planillas (una por curso), en orden de curso.

                @else

                    Planilla resumen del curso: todas las materias, mejor nota por módulo, JIS, promedios, coloquios y pie con reprobadas, inasistencias, amonestaciones, inas. a ed. física, promedio general y previas.

                @endif

                Gris = recuperatorio; rojo = nota &lt; 7.

            </p>

            <a href="{{ $pdfUrl }}"

               target="_blank"

               rel="noopener noreferrer"

               class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">

                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"

                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>

                </svg>

                Imprimir planilla resumen (PDF)

            </a>

        </div>

    @else

        <div class="se-card px-5 py-8">

            <p class="text-center text-sm text-neutral-600 sm:text-left">

                Seleccioná al menos un curso para generar la planilla resumen en PDF.

            </p>

        </div>

    @endif

</div>

