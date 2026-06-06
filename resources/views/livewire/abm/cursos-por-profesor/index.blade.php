<div class="se-page">
    <section class="se-hero">
        <div class="se-hero-inner">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Docentes · ppc + horarios26</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Cursos por profesor</h2>
                <p class="text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                </p>
                <p class="max-w-2xl text-xs text-white/65">
                    Solo lectura. Listado de docentes con materias y cursos asignados, situación de revista
                    y horas cátedra cargadas en la grilla horaria. Las altas y bajas de asignaciones se
                    gestionan en «Asignación de Profesores por Materia y Curso».
                </p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <span class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white/85">
                    <span class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-white/50">Docentes</span>
                    <span class="text-xl font-bold tabular-nums">{{ $profesores->total() }}</span>
                </span>
            </div>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 sm:flex-row sm:items-end">
        <div class="relative flex-1 min-w-0">
            <label for="se-cxp-buscar" class="form-label">Buscar docente</label>
            <div class="relative mt-1.5">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input id="se-cxp-buscar"
                       wire:model.live.debounce.400ms="search"
                       type="search"
                       placeholder="Apellido, nombre o DNI…"
                       class="form-input pl-9">
            </div>
        </div>

        <div class="sm:w-72">
            <label for="se-cxp-curso" class="form-label">Curso</label>
            <select id="se-cxp-curso" wire:model.live="cursoId" class="form-select mt-1.5">
                <option value="">Todos los cursos</option>
                @foreach ($cursos as $c)
                    @php
                        $secText = trim((string) ($c->cursec ?? ''));
                        $turnoText = trim((string) ($c->turnoClase?->nombre ?? ''));
                        $label = $secText !== '' ? $secText : ('Curso #'.(int) $c->Id);
                        if ($turnoText !== '') {
                            $label .= ' · ' . $turnoText;
                        }
                    @endphp
                    <option value="{{ (int) $c->Id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:w-60">
            <label for="se-cxp-sitrev" class="form-label">Situación de revista</label>
            <select id="se-cxp-sitrev" wire:model.live="situacionRevistaId" class="form-select mt-1.5">
                <option value="">Todas</option>
                @foreach ($situaciones as $s)
                    <option value="{{ (int) $s->id }}">{{ $s->sitRev }}</option>
                @endforeach
            </select>
        </div>

        @if ($search !== '' || $cursoId !== '' || $situacionRevistaId !== '')
            <div class="flex items-end">
                <button type="button" wire:click="limpiarFiltros" class="btn-secondary">
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    @if ($profesores->isEmpty())
        <div class="se-card px-6 py-12 text-center text-sm text-neutral-500">
            No hay docentes con asignaciones para los filtros seleccionados.
        </div>
    @else
        <div class="space-y-4">
            @foreach ($profesores as $p)
                @php
                    $idP = (int) $p->id;
                    $filas = $asignacionesPorProfesor->get($idP, collect());
                    $totalHoras = (int) ($totalesPorProfesor->get($idP) ?? 0);
                    $cantAsignaciones = $filas->count();
                @endphp
                <div wire:key="prof-{{ $idP }}" class="se-card overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-accent-200 bg-accent-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-neutral-900">
                                {{ $p->apellido }}, {{ $p->nombre }}
                            </h3>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                DNI <span class="font-mono">{{ $p->dni }}</span>
                                <span class="mx-1 text-neutral-300">·</span>
                                <span>#{{ $idP }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="se-pill text-[11px]">
                                {{ $cantAsignaciones }}
                                {{ $cantAsignaciones === 1 ? 'asignación' : 'asignaciones' }}
                            </span>
                            <span class="se-pill text-[11px] tabular-nums">
                                {{ $totalHoras }} {{ $totalHoras === 1 ? 'hora cátedra' : 'horas cátedra' }}
                            </span>
                            <a href="{{ route('abm.legajos-profesor.edit', $idP) }}"
                               class="btn-secondary btn-sm"
                               title="Abrir legajo del docente">
                                Ver legajo
                            </a>
                        </div>
                    </div>

                    @if ($filas->isEmpty())
                        <p class="px-4 py-6 text-center text-sm text-neutral-500">
                            Sin asignaciones para los filtros actuales.
                        </p>
                    @else
                        <div class="w-full overflow-x-auto">
                            <table class="min-w-full border-collapse text-sm">
                                <thead class="bg-white">
                                    <tr class="border-b border-accent-100 text-[10px] font-semibold uppercase tracking-wide text-neutral-500">
                                        <th class="px-4 py-2.5 text-left">Materia</th>
                                        <th class="px-4 py-2.5 text-left w-48">Curso</th>
                                        <th class="px-4 py-2.5 text-left w-40">Situación de revista</th>
                                        <th class="px-4 py-2.5 text-right w-32">Horas cátedra</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-accent-100 bg-white">
                                    @foreach ($filas as $f)
                                        @php
                                            $clave = ((int) $f->idProfesor).'-'.((int) $f->idMateria);
                                            $horas = (int) ($horasPorPpc[$clave] ?? 0);
                                            $abrev = trim((string) ($f->materiaAbrev ?? ''));
                                            $sitRev = trim((string) ($f->sitRev ?? ''));
                                        @endphp
                                        <tr wire:key="ppc-{{ (int) $f->ppcId }}" class="align-top hover:bg-accent-50/60 transition-colors">
                                            <td class="px-4 py-2.5 text-neutral-800">
                                                <span class="font-medium">{{ $f->materia }}</span>
                                                @if ($abrev !== '')
                                                    <span class="ml-2 text-neutral-400">({{ $abrev }})</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-neutral-700">
                                                {{ $f->cursoLabel }}
                                            </td>
                                            <td class="px-4 py-2.5 text-neutral-700">
                                                @if ($sitRev !== '')
                                                    <span class="inline-flex items-center rounded-full border border-accent-200 bg-accent-50 px-2 py-0.5 text-[11px] font-medium text-neutral-700">
                                                        {{ $sitRev }}
                                                    </span>
                                                @else
                                                    <span class="text-neutral-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono tabular-nums text-neutral-800">
                                                {{ $horas }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-accent-50/60">
                                    <tr class="text-[11px] font-semibold uppercase tracking-wide text-neutral-600">
                                        <td colspan="3" class="px-4 py-2 text-right">Total de horas cátedra</td>
                                        <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $totalHoras }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($profesores->hasPages())
            <div class="se-card border-t border-accent-200 bg-accent-50/70 px-4 py-3">
                {{ $profesores->links('vendor.pagination.se') }}
            </div>
        @endif
    @endif
</div>
