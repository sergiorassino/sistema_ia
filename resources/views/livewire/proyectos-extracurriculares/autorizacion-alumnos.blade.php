<div>
    <div class="se-page">
        <section class="se-hero">
            <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="se-eyebrow">{{ $esAutorizacion ? 'Autorización' : 'Notificación' }}</p>
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $actividadNombre }}</h2>
                    <p class="text-sm text-white/80">
                        {{ schoolCtx()->nivelNombre() }}
                        @if ($resumenFechas !== '')
                            · {{ $resumenFechas }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('proyectosExtracurriculares.autorizaciones') }}"
                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver al listado
                </a>
            </div>
        </section>

        <div class="se-card overflow-hidden">
            <div class="border-b border-accent-200 bg-accent-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Alumnos de la actividad</p>
                @if ($esAutorizacion)
                    <p class="mt-1 text-sm text-neutral-600">
                        Revise quiénes reciben la autorización. El PDF trae los datos personales y los del viaje; la firma queda en blanco.
                    </p>
                @else
                    <p class="mt-1 text-sm text-neutral-600">
                        Las notificaciones se emitirán en una próxima versión. El listado ya muestra los alumnos de esta actividad.
                    </p>
                @endif
            </div>

            @if ($matriculas->isNotEmpty() && $esAutorizacion)
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
                    @if ($puedePdf)
                        <form method="POST"
                              action="{{ route('proyectosExtracurriculares.autorizaciones.pdf') }}"
                              target="_blank"
                              rel="noopener noreferrer"
                              class="inline">
                            @csrf
                            <input type="hidden" name="actividad" value="{{ (int) $actividadId }}">
                            @foreach ($idsPdf as $idMat)
                                <input type="hidden" name="matriculas[]" value="{{ (int) $idMat }}">
                            @endforeach
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Generar autorizaciones
                            </button>
                        </form>
                    @elseif ($excedeMaximo)
                        <p class="text-xs text-amber-800">Máximo {{ $maxPdf }} alumnos por PDF.</p>
                    @endif
                </div>
            @endif

            @if ($matriculas->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-neutral-500">
                    No hay alumnos del ciclo en esta actividad.
                </div>
            @else
                <div class="w-full overflow-x-auto px-4 pb-4 pt-1 se-grid-angosta-wrap">
                    <table class="se-grid-pocos-campos w-auto table-auto divide-y divide-accent-200 text-sm">
                        <thead class="bg-white">
                            <tr>
                                @if ($esAutorizacion)
                                    <th scope="col" class="w-10 py-2 text-center">
                                        <input type="checkbox"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                               title="Marcar o desmarcar todos"
                                               @checked($todasMarcadas)
                                               wire:click="toggleSeleccionTodas">
                                    </th>
                                @endif
                                <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Alumno</th>
                                <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">DNI</th>
                                <th scope="col" class="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Curso</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-100 bg-white">
                            @foreach ($matriculas as $mat)
                                <tr class="hover:bg-accent-50/60" wire:key="ext-aut-mat-{{ $mat->id }}">
                                    @if ($esAutorizacion)
                                        <td class="py-2 text-center align-middle">
                                            <input type="checkbox"
                                                   class="rounded border-accent-300 text-primary-600 focus:ring-primary-500"
                                                   wire:model.live="matriculasSeleccionadas"
                                                   value="{{ $mat->id }}">
                                        </td>
                                    @endif
                                    <td class="py-2 align-middle font-medium text-neutral-800">
                                        {{ trim((string) ($mat->legajo?->apellido ?? '').', '.(string) ($mat->legajo?->nombre ?? '')) }}
                                    </td>
                                    <td class="py-2 align-middle text-neutral-700">{{ $mat->legajo?->dni ?: '—' }}</td>
                                    <td class="py-2 align-middle text-neutral-700">{{ $mat->curso?->nombreParaListado() ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
