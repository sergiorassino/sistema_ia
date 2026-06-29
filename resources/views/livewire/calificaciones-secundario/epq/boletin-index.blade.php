{{-- Informe de calificaciones EPQ secundario --}}
<div class="mx-auto w-full max-w-5xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Secundario · EPQ</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $etiquetaMenu }}</h2>
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

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="min-w-0 flex-1">
            <label for="se-epq-sec-boletin-curso" class="form-label">Curso</label>
            <select id="se-epq-sec-boletin-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full max-w-xl">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($cursoId)
        <div class="se-card overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Estudiantes del curso</p>
                <p class="text-sm text-neutral-600">
                    Marque uno, varios o todos los estudiantes. El PDF en lote imprime dos informes por hoja A4 (centrados en cada mitad, listos para corte).
                </p>
            </div>

            @if ($hayMatriculas)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-100 bg-white px-5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
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
                    </div>
                    @if ($puedePdfLote ?? false)
                        <form method="POST"
                              action="{{ route('calificacionesSecundarioEpq.boletin.pdfLote') }}"
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
                                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                PDF con seleccionados
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="w-full overflow-x-auto se-grid-angosta-wrap px-4 pb-4 pt-1">
                <table class="w-max max-w-full table-auto divide-y divide-accent-200 text-sm">
                    <thead class="bg-white">
                        <tr>
                            <th scope="col" class="w-10 py-3 pl-5 pr-1 text-center">
                                @if ($hayMatriculas)
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           title="Marcar o desmarcar todos"
                                           @checked($todasMarcadas)
                                           wire:click="toggleSeleccionTodas">
                                @endif
                            </th>
                            <th scope="col" class="py-3 pl-2 pr-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Apellido y nombre</th>
                            <th scope="col" class="py-3 pl-2 pr-5 text-right text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Informe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($matriculas as $mat)
                            <tr class="hover:bg-accent-50/60" wire:key="epq-sec-bol-{{ $mat->id }}">
                                <td class="py-3 pl-5 pr-1 text-center align-middle">
                                    <input type="checkbox"
                                           class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                           wire:model.live="matriculasSeleccionadas"
                                           value="{{ $mat->id }}">
                                </td>
                                <td class="py-3 pl-2 pr-2 align-middle font-medium text-neutral-800">
                                    {{ trim((string) ($mat->legajo?->nombre_completo ?? '')) === '' ? '—' : $mat->legajo->nombre_completo }}
                                </td>
                                <td class="py-3 pl-2 pr-5 text-right align-middle">
                                    <x-pdf-post-matricula :action="route('calificacionesSecundarioEpq.boletin.pdf')" :matricula="$mat->id">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        INFORME DE CALIFICACIONES
                                    </x-pdf-post-matricula>
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
