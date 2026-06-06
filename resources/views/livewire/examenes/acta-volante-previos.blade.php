{{-- Actas volantes de examen (previas): una hoja PDF por materia del plan + condición de adeudo. --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Exámenes · Previas</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Actas volantes de examen</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Materias adeudadas inscriptas a examen
                    (<code class="rounded bg-white/15 px-1 text-xs">inscri = 1</code>,
                    <code class="rounded bg-white/15 px-1 text-xs">apro = 1</code>)
                </p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    @if (session('status'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
            {{ session('status') }}
        </div>
    @endif

    <livewire:examenes.materias-adeudadas-preparacion-panel
        modulo="acta_volante"
        wire:key="prep-panel-acta-volante-{{ $prepTick ?? 0 }}" />

    @if ($preparacionLista ?? false)
        <div class="se-card px-5 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="se-section-title">Actas a imprimir</p>
                    <p class="mt-1 text-sm text-neutral-600">
                        Cada fila es una combinación de materia del plan y condición de adeudo con alumnos inscriptos.
                        Se imprime una hoja PDF por acta seleccionada.
                    </p>
                </div>
                <span class="se-pill tabular-nums">
                    {{ $cantidadSeleccionadas }} de {{ $actas->count() }} seleccionadas
                </span>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="seleccionarTodasActas"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Todas
                </button>
                <button type="button" wire:click="quitarTodasActas"
                        class="inline-flex items-center rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-sm font-semibold text-neutral-700 shadow-sm transition hover:bg-accent-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Ninguna
                </button>
            </div>

            @if ($actas->isEmpty())
                <p class="mt-4 text-sm text-neutral-600">
                    No hay actas pendientes: no existen calificaciones adeudadas con inscripción a examen en este nivel.
                </p>
            @else
                <div class="mt-4 overflow-x-auto rounded-xl border border-accent-200">
                    <table class="min-w-full divide-y divide-accent-200 text-sm">
                        <thead class="bg-accent-50">
                            <tr>
                                <th scope="col" class="w-10 px-3 py-3">
                                    <span class="sr-only">Seleccionar</span>
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Materia (plan)</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Curso (plan)</th>
                                <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Condición</th>
                                <th scope="col" class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Inscriptos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($actas as $acta)
                                <tr class="hover:bg-accent-50/80" wire:key="acta-previa-{{ $acta->clave }}">
                                    <td class="px-3 py-2.5 text-center">
                                        <input type="checkbox"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                               wire:model.live="actasSeleccionadas"
                                               value="{{ $acta->clave }}">
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-neutral-800">{{ $acta->materiaLabel }}</td>
                                    <td class="px-4 py-2.5 text-neutral-700">{{ $acta->cursoLabel }}</td>
                                    <td class="px-4 py-2.5 text-neutral-700">
                                        <span class="font-mono text-xs text-neutral-500">{{ $acta->condAdeuda !== '' ? $acta->condAdeuda : '—' }}</span>
                                        <span class="text-neutral-600"> · {{ $acta->condicionLabel }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-neutral-700">{{ $acta->cantidadAlumnos }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($pdfUrl)
            <div class="se-card space-y-4 px-5 py-5">
                <p class="text-sm text-neutral-600">
                    Listado de alumnos inscriptos a examen por materia y condición.
                    Las columnas Escrito, Oral y Prom quedan en blanco para completar en la mesa.
                </p>
                <a href="{{ $pdfUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir actas volantes (PDF)
                </a>
            </div>
        @else
            <div class="se-card px-5 py-8">
                <p class="text-center text-sm text-neutral-600 sm:text-left">
                    @if ($actas->isEmpty())
                        No hay actas disponibles para imprimir en este nivel.
                    @else
                        Seleccioná al menos una acta para generar el PDF.
                    @endif
                </p>
            </div>
        @endif
    @endif
</div>
