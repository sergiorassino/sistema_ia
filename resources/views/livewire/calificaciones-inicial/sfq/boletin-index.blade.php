{{-- SFQ — Informes inicial: diagnóstico, etapas pedagógicas y Bellas Artes. --}}
<div class="mx-auto w-full max-w-6xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Inicial · SFQ</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Informes pedagógicos</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::urlInicio() }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver
            </a>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="min-w-0 flex-1">
            <label for="se-sfq-boletin-curso" class="form-label">Curso</label>
            <select id="se-sfq-boletin-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full max-w-xl">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($cursoId)
        <div class="se-card overflow-hidden p-0">
            <div class="border-b border-primary-700 bg-primary-600 px-5 py-3">
                <p class="text-sm font-semibold uppercase tracking-wide text-white">
                    Seleccione el / los alumnos a imprimir
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-200 bg-accent-50 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($tiposInforme as $item)
                        <button type="button"
                                wire:click="$set('tipoInforme', '{{ $item['clave'] }}')"
                                @class([
                                    'rounded-full px-4 py-2 text-xs font-semibold shadow-sm transition',
                                    'bg-primary-600 text-white ring-2 ring-primary-600' => $hayTipoInforme && $tipoPdf === $item['clave'],
                                    'bg-white text-neutral-700 ring-1 ring-accent-200 hover:bg-accent-50' => ! $hayTipoInforme || $tipoPdf !== $item['clave'],
                                ])>
                            {{ $item['etiqueta'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if (! $hayTipoInforme)
                <div class="border-b border-accent-100 bg-white px-5 py-3">
                    <p class="text-sm text-neutral-600">Seleccione el tipo de informe a imprimir.</p>
                </div>
            @endif

            @if ($hayTipoInforme && $matriculas->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-100 bg-white px-5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="seleccionarTodasMatriculas"
                                class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                            Marcar todos
                        </button>
                        <button type="button" wire:click="quitarTodasMatriculas"
                                class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-600 shadow-sm hover:bg-accent-50">
                            Desmarcar todos
                        </button>
                    </div>
                    @if ($puedePdfLote ?? false)
                        <form method="POST"
                              action="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::rutaBoletin('pdfLote') }}"
                              target="_blank" rel="noopener noreferrer" class="inline">
                            @csrf
                            <input type="hidden" name="tipo" value="{{ $tipoPdf }}">
                            @foreach ($idsPdfLote as $idMat)
                                <input type="hidden" name="matriculas[]" value="{{ $idMat }}">
                            @endforeach
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700">
                                Imprimir seleccionados ({{ count($idsPdfLote) }})
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="w-full overflow-x-auto px-4 pb-4 pt-2">
                <table class="min-w-full divide-y divide-accent-200 text-sm">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="w-10 py-3 pl-2 pr-1 text-center">
                                <span class="sr-only">Seleccionar</span>
                            </th>
                            <th class="py-3 pl-2 pr-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Apellido</th>
                            <th class="py-3 px-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Nombre</th>
                            <th class="py-3 px-3 text-left text-[11px] font-semibold uppercase text-neutral-500">DNI</th>
                            <th class="py-3 px-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Condición</th>
                            @if ($hayTipoInforme)
                                <th class="py-3 pl-3 pr-2 text-left text-[11px] font-semibold uppercase text-neutral-500">PDF</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($matriculas as $mat)
                            <tr wire:key="sfq-bol-{{ $mat->id }}-{{ $tipoPdf ?: 'sin-tipo' }}" class="hover:bg-accent-50/60">
                                <td class="py-3 pl-2 pr-1 text-center align-middle">
                                    @if ($hayTipoInforme)
                                        <input type="checkbox"
                                               wire:model.live="matriculasSeleccionadas"
                                               value="{{ $mat->id }}"
                                               class="rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                    @endif
                                </td>
                                <td class="py-3 pl-2 pr-3 align-middle font-medium text-neutral-800 whitespace-nowrap">
                                    {{ trim((string) ($mat->legajo?->apellido ?? '')) ?: '—' }}
                                </td>
                                <td class="py-3 px-3 align-middle text-neutral-800 whitespace-nowrap">
                                    {{ trim((string) ($mat->legajo?->nombre ?? '')) ?: '—' }}
                                </td>
                                <td class="py-3 px-3 align-middle text-neutral-700 whitespace-nowrap">
                                    {{ trim((string) ($mat->legajo?->dni ?? '')) ?: '—' }}
                                </td>
                                <td class="py-3 px-3 align-middle text-neutral-600 whitespace-nowrap">
                                    {{ trim((string) ($mat->condicion?->condicion ?? '')) ?: '—' }}
                                </td>
                                @if ($hayTipoInforme)
                                    <td class="py-3 pl-3 pr-2 align-middle">
                                        <form method="POST"
                                              action="{{ \App\Support\PortalDocente\CalificacionesInicialSfqPortalDocente::rutaBoletin('pdf') }}"
                                              target="_blank" rel="noopener noreferrer" class="inline">
                                            @csrf
                                            <input type="hidden" name="matricula" value="{{ $mat->id }}">
                                            <input type="hidden" name="tipo" value="{{ $tipoPdf }}">
                                            <button type="submit"
                                                    class="rounded-xl border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm hover:bg-accent-50">
                                                Abrir PDF
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $hayTipoInforme ? 6 : 5 }}" class="px-5 py-10 text-center text-sm text-neutral-500">No hay matrículas en este curso.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
