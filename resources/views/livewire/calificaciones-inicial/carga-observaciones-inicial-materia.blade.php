{{-- Carga por espacio curricular: curso → materia → grilla alumnos × obs etapas 1 y 2. --}}
<div class="w-full max-w-none space-y-6">
    <style>
        table.se-calif-inicial-obs-mat-grid {
            table-layout: fixed;
            width: 100%;
            min-width: 58rem;
            font-size: 11px;
        }
        table.se-calif-inicial-obs-mat-grid th,
        table.se-calif-inicial-obs-mat-grid td { padding: 3px 2px; line-height: 1.2; }
        table.se-calif-inicial-obs-mat-grid td.se-calif-inicial-obs-mat-col-alumno {
            vertical-align: middle;
        }
        table.se-calif-inicial-obs-mat-grid td.se-calif-inicial-obs-mat-col-obs {
            vertical-align: top;
        }
        table.se-calif-inicial-obs-mat-grid .se-calif-inicial-obs-mat-col-alumno {
            width: 10rem;
            min-width: 10rem;
            max-width: 10rem;
            text-align: left;
            font-weight: 600;
        }
        table.se-calif-inicial-obs-mat-grid th.se-calif-inicial-obs-mat-col-obs,
        table.se-calif-inicial-obs-mat-grid td.se-calif-inicial-obs-mat-col-obs {
            width: calc((100% - 10rem) / 2);
            min-width: 24rem;
        }
        /* ~1500 caract. a 13px; columnas crecen en pantallas anchas */
        table.se-calif-inicial-obs-mat-grid textarea.se-calif-inicial-obs-mat-input {
            display: block;
            width: 100%;
            height: 21rem;
            min-height: 21rem;
            max-height: 21rem;
            padding: 6px 8px;
            font-size: 13px;
            line-height: 1.4;
            text-align: left;
            overflow-y: auto;
            resize: none;
            box-sizing: border-box;
        }
    </style>

    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Inicial</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de observaciones por espacio curricular</h2>
                <p class="max-w-3xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    @if ($cursoId && $materiaId)
                        <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">
                            <span class="font-semibold text-white">{{ $cursoLabel }}</span>
                            · <span class="font-semibold text-white">{{ $materiaLabel }}</span>
                        </span>
                    @endif
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesInicialPortalDocente::urlInicio() }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="se-calif-inicial-obs-mat-curso" class="form-label">Curso</label>
                <select id="se-calif-inicial-obs-mat-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-calif-inicial-obs-mat-materia" class="form-label">Espacio curricular</label>
                <select id="se-calif-inicial-obs-mat-materia" wire:model.live="materiaId" class="form-select mt-1.5 w-full" @disabled(! $cursoId)>
                    <option value="">— Seleccione —</option>
                    @foreach ($materias as $m)
                        <option value="{{ $m->id }}">{{ $m->materia }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($cursoId && $materiaId)
        @if ($filas === [])
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                No hay matrículas regulares en este curso para el ciclo lectivo actual.
            </div>
        @else
            <div class="se-card w-full overflow-x-auto p-4" wire:key="calif-inicial-obs-mat-grid-{{ $materiaId }}">
                <div class="w-full">
                    <table class="se-calif-inicial-obs-mat-grid w-full border-collapse border border-accent-200">
                            <thead>
                                <tr class="bg-neutral-100">
                                    <th class="se-calif-inicial-obs-mat-col-alumno border border-accent-200 bg-white px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                        Estudiante
                                    </th>
                                    @foreach ($etapas as $etapa)
                                        <th class="se-calif-inicial-obs-mat-col-obs border border-accent-200 bg-white px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-700"
                                            title="{{ $etapa }}ª etapa ({{ $maxCaracteres }} caract. máx.)">
                                            {{ $etapa }}ª etapa
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody
                                wire:key="calif-inicial-obs-mat-tbody-{{ $materiaId }}"
                                data-se-calif-inicial-obs-mat-tbody
                                data-se-calif-inicial-obs-mat-materia-id="{{ $materiaId }}"
                                data-se-calif-inicial-obs-mat-ord="{{ $ordMateria }}">
                                @foreach ($filas as $idMatricula => $fila)
                                    <tr wire:key="calif-inicial-obs-mat-row-{{ $materiaId }}-{{ $idMatricula }}" class="hover:bg-neutral-50">
                                        <td class="se-calif-inicial-obs-mat-col-alumno border border-accent-200 bg-white px-2 py-1 text-[11px] text-neutral-800 align-middle">
                                            {{ $fila['alumno'] }}
                                        </td>
                                        @foreach ($etapas as $etapa)
                                            @php
                                                $campo = $etapa === 2 ? 'obs02' : 'obs01';
                                                $valor = $fila['observaciones'][$etapa] ?? '';
                                            @endphp
                                            <td class="se-calif-inicial-obs-mat-col-obs border border-accent-200 p-1">
                                                <textarea id="se-calif-inicial-obs-mat-{{ $idMatricula }}-{{ $campo }}"
                                                          rows="21"
                                                          maxlength="{{ $maxCaracteres }}"
                                                          autocomplete="off"
                                                          wire:key="inicial-obs-mat-cell-{{ $materiaId }}-{{ $idMatricula }}-{{ $campo }}"
                                                          title="{{ $maxCaracteres }} caracteres máx."
                                                          class="se-calif-inicial-obs-mat-input rounded border border-accent-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">{{ $valor }}</textarea>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                </div>
            </div>
            <p class="text-xs text-neutral-500">
                Las observaciones se guardan automáticamente al salir de cada campo ({{ $maxCaracteres }} caracteres por etapa).
            </p>
        @endif
    @endif
</div>
