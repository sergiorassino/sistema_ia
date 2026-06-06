<div class="se-page max-w-6xl">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Asistencia estudiantes</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Informe de Inasistencias</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Año lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            @if ($cursoId)
                <button type="button"
                        wire:click="volverACursos"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Cambiar curso
                </button>
            @endif
        </div>
    </section>

    @if (! $cursoId)
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-white px-5 py-4">
                <p class="se-section-title">Paso 1 — Elegir curso</p>
                <p class="mt-1 text-sm text-neutral-600">
                    Seleccione el curso para ver los estudiantes y generar el informe PDF (mismo formato que en la autogestión del alumno).
                </p>
            </div>

            <div class="w-full overflow-x-auto px-4 pb-4 pt-2 se-grid-angosta-wrap">
                <table class="se-grid-pocos-campos w-auto table-auto divide-y divide-accent-200 text-sm">
                    <thead class="bg-accent-50/80">
                        <tr>
                            <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            <th scope="col" class="py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($cursos as $c)
                            <tr class="hover:bg-accent-50/60" wire:key="informe-curso-{{ $c->Id }}">
                                <td class="py-2 align-middle font-medium text-neutral-800">
                                    {{ $c->nombreParaListado() }}
                                </td>
                                <td class="py-2 text-right align-middle">
                                    <button type="button"
                                            wire:click="elegirCurso({{ $c->Id }})"
                                            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                        Elegir curso
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-5 py-10 text-center text-sm text-neutral-500">
                                    No hay cursos en este nivel y ciclo lectivo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Paso 2 — Estudiantes</p>
                <p class="text-sm font-semibold text-neutral-900">
                    {{ $cursoActivo?->nombreParaListado() ?? 'Curso' }}
                </p>
                <p class="mt-1 text-sm text-neutral-600">
                    Marque uno, varios o todos los estudiantes y genere un único PDF con los informes seleccionados.
                </p>
            </div>

            @if ($hayMatriculas)
                <div class="se-toolbar-pocos-campos border-b border-accent-100 bg-white px-5 py-3">
                    <button type="button"
                            wire:click="seleccionarTodasMatriculas"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                        Marcar todos
                    </button>
                    <button type="button"
                            wire:click="quitarTodasMatriculas"
                            class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 shadow-sm transition hover:border-accent-300 hover:bg-accent-50">
                        Desmarcar todos
                    </button>
                    @if ($cantidadSeleccionados > 0)
                        <span class="se-pill">{{ $cantidadSeleccionados }} seleccionado{{ $cantidadSeleccionados === 1 ? '' : 's' }}</span>
                    @endif
                    @if ($puedePdfLote ?? false)
                        <form method="POST"
                              action="{{ route('seguimiento.inasistencias.informe.lote.pdf') }}"
                              target="_blank"
                              rel="noopener noreferrer"
                              class="inline">
                            @csrf
                            <input type="hidden" name="curso" value="{{ (int) $cursoId }}">
                            @foreach ($idsPdfLote as $idMat)
                                <input type="hidden" name="matriculas[]" value="{{ (int) $idMat }}">
                            @endforeach
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Generar informes (PDF)
                            </button>
                        </form>
                    @elseif ($cantidadSeleccionados > $maxMatriculasPdf)
                        <p class="text-xs text-amber-800">
                            Máximo {{ $maxMatriculasPdf }} estudiantes por PDF.
                        </p>
                    @endif
                </div>
            @endif

            <div class="w-full overflow-x-auto px-4 pb-4 pt-1 se-grid-angosta-wrap">
                <table class="se-grid-pocos-campos w-auto table-auto divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="w-10 py-2 text-center">
                                @if ($hayMatriculas)
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           title="Marcar o desmarcar todos"
                                           @checked($todasMarcadas)
                                           wire:click="toggleSeleccionTodas">
                                @endif
                            </th>
                            <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Apellido y nombre</th>
                            <th scope="col" class="py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Informe individual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($matriculas as $mat)
                            <tr class="hover:bg-accent-50/60" wire:key="informe-mat-{{ $mat->id }}">
                                <td class="py-2 text-center align-middle">
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           wire:model.live="matriculasSeleccionadas"
                                           value="{{ $mat->id }}">
                                </td>
                                <td class="py-2 align-middle font-medium text-neutral-800">
                                    {{ trim(($mat->legajo?->apellido ?? '').', '.($mat->legajo?->nombre ?? '')) }}
                                    @if ($mat->legajo?->dni)
                                        <span class="text-xs font-normal text-neutral-500">· DNI {{ $mat->legajo->dni }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right align-middle">
                                    <x-pdf-post
                                        :action="route('seguimiento.inasistencias.informe.pdf')"
                                        :matricula="$mat->id"
                                        button-class="inline-flex items-center justify-end gap-1.5 rounded-xl border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-primary-700 shadow-sm transition hover:border-primary-300 hover:bg-accent-50">
                                        PDF
                                    </x-pdf-post>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-10 text-center text-sm text-neutral-500">
                                    No hay matrículas en este curso para el ciclo lectivo actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
