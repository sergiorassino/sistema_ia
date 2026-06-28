{{-- EPQ — curso → alumnos con acceso a Calificaciones e Información adicional. --}}
<div class="mx-auto w-full max-w-6xl space-y-6">
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Primario · EPQ</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Seleccione el estudiante</h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesPrimarioPortalDocente::urlInicio() }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver
            </a>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="min-w-0 flex-1">
            <label for="se-epq-curso" class="form-label">Curso</label>
            <select id="se-epq-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full max-w-xl">
                <option value="">— Seleccione —</option>
                @foreach ($cursos as $c)
                    <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($cursoId)
        <div class="se-card overflow-hidden p-0">
            <div class="border-b border-accent-200 bg-primary-600 px-5 py-3 text-center">
                <p class="text-sm font-bold uppercase tracking-wide text-white">Seleccione el estudiante:</p>
            </div>
            <div class="w-full overflow-x-auto se-grid-angosta-wrap px-4 pb-4 pt-2">
                <table class="w-max min-w-[42rem] table-fixed divide-y divide-accent-200 text-sm">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="w-10 py-3 text-center text-[11px] font-semibold uppercase text-neutral-500">#</th>
                            <th class="w-48 py-3 pl-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Apellido</th>
                            <th class="w-40 py-3 text-left text-[11px] font-semibold uppercase text-neutral-500">Nombre</th>
                            <th class="w-28 py-3 text-center text-[11px] font-semibold uppercase text-neutral-500">DNI</th>
                            <th class="w-24 py-3 text-center text-[11px] font-semibold uppercase text-neutral-500">Calificaciones</th>
                            <th class="w-24 py-3 text-center text-[11px] font-semibold uppercase text-neutral-500">Info. Adic.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-accent-100 bg-white">
                        @forelse ($matriculas as $i => $mat)
                            <tr class="hover:bg-accent-50/60" wire:key="epq-mat-{{ $mat->id }}">
                                <td class="py-3 text-center text-neutral-500">{{ $i + 1 }}</td>
                                <td class="py-3 pl-3 font-medium text-neutral-800">{{ $mat->legajo?->apellido ?? '—' }}</td>
                                <td class="py-3 text-neutral-800">{{ $mat->legajo?->nombre ?? '—' }}</td>
                                <td class="py-3 text-center text-neutral-700">{{ $mat->legajo?->dni ?? '—' }}</td>
                                <td class="py-3 text-center">
                                    <a href="{{ \App\Support\PortalDocente\CalificacionesPrimarioPortalDocente::route('carga.alumno', ['matricula' => $mat->id, 'curso' => $cursoId]) }}"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-accent-200 bg-white shadow-sm transition hover:border-primary-400 hover:bg-accent-50"
                                       title="Carga de calificaciones">
                                        <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </a>
                                </td>
                                <td class="py-3 text-center">
                                    <a href="{{ \App\Support\PortalDocente\CalificacionesPrimarioPortalDocente::route('carga.infoAdicional', ['matricula' => $mat->id, 'curso' => $cursoId]) }}"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-accent-200 bg-white shadow-sm transition hover:border-primary-400 hover:bg-accent-50"
                                       title="Información adicional">
                                        <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-neutral-500">
                                    No hay matrículas regulares en este curso.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
