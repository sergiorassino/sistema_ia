{{-- Carga por materia: etapa → curso → materia → grilla alumnos × parciales + nota etapa (+ anual en 2ª) + obs etapa. --}}
@php
    use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
    $maxObsCalif = CalificacionesPrimarioCatalogo::MAX_CARACTERES_OBS_CALIFICACION;
@endphp
<div class="mx-auto w-full max-w-[98rem] space-y-6">
    <style>
        table.se-calif-prim-mat-grid {
            table-layout: fixed;
            width: 100%;
            font-size: 11px;
        }
        table.se-calif-prim-mat-grid th,
        table.se-calif-prim-mat-grid td { padding: 3px 2px; line-height: 1.2; }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-alumno,
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-nota {
            vertical-align: middle;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-obs {
            vertical-align: top;
        }
        table.se-calif-prim-mat-grid input[type="text"] {
            width: 100%;
            height: 22px;
            padding: 0 2px;
            font-size: 11px;
            text-align: center;
            box-sizing: border-box;
        }
        table.se-calif-prim-mat-grid input:disabled {
            background: #e8e8e8;
            color: #6b7280;
            cursor: not-allowed;
        }
        table.se-calif-prim-mat-grid .se-calif-prim-mat-col-alumno {
            width: 9.5rem;
            text-align: left;
            font-weight: 600;
        }
        table.se-calif-prim-mat-grid th.se-calif-prim-mat-col-nota,
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-nota {
            width: 3.7rem;
            min-width: 3.7rem;
            max-width: 3.7rem;
            padding-left: 2px;
            padding-right: 2px;
        }
        table.se-calif-prim-mat-grid thead th.se-calif-prim-mat-col-nota,
        table.se-calif-prim-mat-grid thead th.se-calif-prim-mat-col-alumno {
            vertical-align: middle;
        }
        table.se-calif-prim-mat-grid thead th.se-calif-prim-mat-col-nota {
            font-size: 7px;
            line-height: 1.05;
            word-break: break-word;
            hyphens: auto;
            letter-spacing: -0.02em;
        }
        table.se-calif-prim-mat-grid thead th.se-calif-prim-mat-col-nota-principal {
            font-size: 7px;
            font-weight: 700;
            line-height: 1.05;
            word-break: break-word;
            hyphens: auto;
            letter-spacing: -0.02em;
        }
        table.se-calif-prim-mat-grid th.se-calif-prim-mat-col-obs,
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-obs {
            width: auto;
            min-width: 12rem;
        }
        table.se-calif-prim-mat-grid textarea.se-calif-prim-mat-obs-input {
            display: block;
            width: 100%;
            min-height: 2.75rem;
            padding: 4px 6px;
            font-size: 11px;
            line-height: 1.35;
            text-align: left;
            resize: vertical;
            box-sizing: border-box;
        }
        /* Nota de etapa (columna final de etapa) — gris neutro pálido */
        table.se-calif-prim-mat-grid th.se-calif-prim-mat-col-etapa,
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-etapa {
            background-color: #f5f5f5;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-etapa input[type="text"],
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-etapa .se-calif-prim-nota-combo {
            background-color: #f5f5f5;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-etapa .se-calif-prim-nota-picker-btn {
            background-color: #f5f5f5;
            border-color: #d4d4d4;
        }
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-etapa,
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-etapa input[type="text"],
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-etapa .se-calif-prim-nota-combo,
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-etapa .se-calif-prim-nota-picker-btn {
            background-color: #ececec;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-etapa .se-calif-prim-nota-picker-btn:hover {
            background-color: #ececec;
            border-color: #b3b3b3;
            color: #404040;
        }
        /* Aprec. final / calificación anual — gris neutro suave */
        table.se-calif-prim-mat-grid th.se-calif-prim-mat-col-anual,
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-anual {
            background-color: #f2f2f2;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-anual input[type="text"],
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-anual .se-calif-prim-nota-combo {
            background-color: #f2f2f2;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-anual .se-calif-prim-nota-picker-btn {
            background-color: #f2f2f2;
            border-color: #d4d4d4;
        }
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-anual,
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-anual input[type="text"],
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-anual .se-calif-prim-nota-combo,
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-anual .se-calif-prim-nota-picker-btn {
            background-color: #ececec;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-anual .se-calif-prim-nota-picker-btn:hover {
            background-color: #ececec;
            border-color: #b3b3b3;
            color: #404040;
        }
        /* Parciales e intensificación: fondo blanco (también al hover de fila) */
        table.se-calif-prim-mat-grid th.se-calif-prim-mat-col-blanco,
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-blanco {
            background-color: #ffffff;
        }
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-blanco input[type="text"],
        table.se-calif-prim-mat-grid td.se-calif-prim-mat-col-blanco .se-calif-prim-nota-combo {
            background-color: #ffffff;
        }
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-blanco,
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-blanco input[type="text"],
        table.se-calif-prim-mat-grid tbody tr:hover td.se-calif-prim-mat-col-blanco .se-calif-prim-nota-combo {
            background-color: #ffffff;
        }
    </style>

    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Primario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de calificaciones por materia</h2>
                <p class="max-w-3xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    @if ($cursoId && $materiaId)
                        <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">
                            {{ $etapa === 2 ? '2ª etapa' : '1ª etapa' }}
                            · <span class="font-semibold text-white">{{ $cursoLabel }}</span>
                            · <span class="font-semibold text-white">{{ $materiaLabel }}</span>
                        </span>
                    @endif
                </p>
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesPrimarioPortalDocente::urlInicio() }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al panel
            </a>
        </div>
    </section>

    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="se-calif-prim-mat-etapa" class="form-label">Etapa</label>
                <select id="se-calif-prim-mat-etapa" wire:model.live="etapa" class="form-select mt-1.5 w-full">
                    <option value="1">1ª etapa</option>
                    <option value="2">2ª etapa</option>
                </select>
            </div>
            <div>
                <label for="se-calif-prim-mat-curso" class="form-label">Curso</label>
                <select id="se-calif-prim-mat-curso" wire:model.live="cursoId" class="form-select mt-1.5 w-full">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-calif-prim-mat-materia" class="form-label">Materia</label>
                <select id="se-calif-prim-mat-materia" wire:model.live="materiaId" class="form-select mt-1.5 w-full" @disabled(! $cursoId)>
                    <option value="">— Seleccione —</option>
                    @foreach ($materias as $m)
                        <option value="{{ $m->id }}">
                            {{ CalificacionesPrimarioCatalogo::etiquetaEncabezadoColumna($m) !== '—'
                                ? CalificacionesPrimarioCatalogo::etiquetaEncabezadoColumna($m).' — '
                                : '' }}{{ trim((string) ($m->materia ?? '')) !== '' ? $m->materia : ('Ord '.$m->ord) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($notasPermitidasActiva)
        <p class="text-xs text-neutral-500">
            Elija la nota del desplegable (flecha) o escriba con el teclado. En el listado: ↑↓, Enter y Escape.
        </p>
    @endif

    @if ($cursoId && $materiaId)
        @if ($filas === [])
            <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
                No hay matrículas regulares en este curso para el ciclo lectivo actual.
            </div>
        @else
            <div class="se-card overflow-x-auto p-4" wire:key="calif-prim-mat-grid-{{ $materiaId }}-{{ $etapa }}">
                <div class="w-full overflow-x-auto">
                    <div class="flex justify-start">
                        <table class="se-calif-prim-mat-grid w-full border-collapse border border-accent-200">
                            <thead>
                                <tr class="bg-neutral-100">
                                    <th class="se-calif-prim-mat-col-alumno border border-accent-200 bg-white px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                        Estudiante
                                    </th>
                                    @foreach ($columnasParciales as $idx => $col)
                                        <th class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-blanco border border-accent-200 px-0.5 py-1.5 text-center uppercase text-neutral-700"
                                            title="{{ $col['etiqueta'] }}">
                                            {{ $idx + 1 }}
                                        </th>
                                    @endforeach
                                    <th class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-nota-principal se-calif-prim-mat-col-etapa border border-accent-200 px-0.5 py-1.5 text-center uppercase text-primary-800"
                                        title="{{ $columnaFinalEtapa['etiqueta'] }}">
                                        {{ $columnaFinalEtapa['etiqueta'] }}
                                    </th>
                                    @if ($columnaAnual)
                                        <th class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-nota-principal se-calif-prim-mat-col-anual border border-accent-200 px-0.5 py-1.5 text-center uppercase text-primary-800"
                                            title="{{ $columnaAnual['etiqueta'] }}">
                                            {{ $columnaAnual['etiqueta'] }}
                                        </th>
                                    @endif
                                    @if ($columnaIntensificacion)
                                        <th class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-nota-principal se-calif-prim-mat-col-blanco border border-accent-200 px-0.5 py-1.5 text-center uppercase text-primary-800"
                                            title="{{ $columnaIntensificacion['etiqueta'] }}">
                                            {{ $columnaIntensificacion['etiqueta'] }}
                                        </th>
                                    @endif
                                    <th class="se-calif-prim-mat-col-obs border border-accent-200 bg-white px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-700"
                                        title="{{ $columnaObs['etiqueta'] }} ({{ $maxObsCalif }} caract. máx.)">
                                        {{ $columnaObs['etiqueta'] }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                wire:key="calif-prim-mat-tbody-{{ $materiaId }}-{{ $etapa }}"
                                data-se-calif-prim-mat-tbody
                                data-se-calif-prim-mat-materia-id="{{ $materiaId }}"
                                data-se-calif-prim-mat-ord="{{ $ordMateria }}"
                                data-se-calif-prim-mat-activa="{{ $notasPermitidasActiva ? '1' : '0' }}"
                                data-se-calif-prim-mat-allowed='@json($notasPermitidasLista ?? [])'>
                                @foreach ($filas as $idMatricula => $fila)
                                    <tr wire:key="calif-prim-mat-row-{{ $materiaId }}-{{ $etapa }}-{{ $idMatricula }}" class="hover:bg-neutral-50">
                                        <td class="se-calif-prim-mat-col-alumno border border-accent-200 bg-white px-2 py-1 text-[11px] text-neutral-800 align-middle">
                                            {{ $fila['alumno'] }}
                                        </td>
                                        @foreach ($columnasParciales as $col)
                                            @php
                                                $campo = $col['campo'];
                                                $valor = $fila['notas'][$campo] ?? '';
                                            @endphp
                                            <td class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-blanco border border-accent-200 p-0.5 align-middle">
                                                @include('livewire.calificaciones-primario.partials.celda-nota-permitida', [
                                                    'id' => 'se-calif-prim-mat-'.$idMatricula.'-'.$campo,
                                                    'value' => $valor,
                                                    'wireKey' => 'prim-mat-cell-'.$materiaId.'-'.$etapa.'-'.$idMatricula.'-'.$campo,
                                                    'notasPermitidasActiva' => $notasPermitidasActiva,
                                                    'notasPermitidasLista' => $notasPermitidasLista ?? [],
                                                ])
                                            </td>
                                        @endforeach
                                        @php
                                            $campoFinal = $columnaFinalEtapa['campo'];
                                            $valorFinal = $fila['notas'][$campoFinal] ?? '';
                                        @endphp
                                        <td class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-etapa border border-accent-200 p-0.5 align-middle">
                                            @include('livewire.calificaciones-primario.partials.celda-nota-permitida', [
                                                'id' => 'se-calif-prim-mat-'.$idMatricula.'-'.$campoFinal,
                                                'value' => $valorFinal,
                                                'wireKey' => 'prim-mat-cell-'.$materiaId.'-'.$etapa.'-'.$idMatricula.'-'.$campoFinal,
                                                'inputClass' => 'rounded border border-accent-200 font-semibold focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
                                                'notasPermitidasActiva' => $notasPermitidasActiva,
                                                'notasPermitidasLista' => $notasPermitidasLista ?? [],
                                            ])
                                        </td>
                                        @if ($columnaAnual)
                                            @php
                                                $campoAnual = $columnaAnual['campo'];
                                                $valorAnual = $fila['notas'][$campoAnual] ?? '';
                                            @endphp
                                            <td class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-anual border border-accent-200 p-0.5 align-middle">
                                                @include('livewire.calificaciones-primario.partials.celda-nota-permitida', [
                                                    'id' => 'se-calif-prim-mat-'.$idMatricula.'-'.$campoAnual,
                                                    'value' => $valorAnual,
                                                    'wireKey' => 'prim-mat-cell-'.$materiaId.'-'.$etapa.'-'.$idMatricula.'-'.$campoAnual,
                                                    'inputClass' => 'rounded border border-accent-200 font-semibold focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
                                                    'notasPermitidasActiva' => $notasPermitidasActiva,
                                                    'notasPermitidasLista' => $notasPermitidasLista ?? [],
                                                ])
                                            </td>
                                        @endif
                                        @if ($columnaIntensificacion)
                                            @php
                                                $campoIntensif = $columnaIntensificacion['campo'];
                                                $valorIntensif = $fila['notas'][$campoIntensif] ?? '';
                                            @endphp
                                            <td class="se-calif-prim-mat-col-nota se-calif-prim-mat-col-blanco border border-accent-200 p-0.5 align-middle">
                                                @include('livewire.calificaciones-primario.partials.celda-nota-permitida', [
                                                    'id' => 'se-calif-prim-mat-'.$idMatricula.'-'.$campoIntensif,
                                                    'value' => $valorIntensif,
                                                    'wireKey' => 'prim-mat-cell-'.$materiaId.'-'.$etapa.'-'.$idMatricula.'-'.$campoIntensif,
                                                    'inputClass' => 'rounded border border-accent-200 font-semibold focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
                                                    'notasPermitidasActiva' => $notasPermitidasActiva,
                                                    'notasPermitidasLista' => $notasPermitidasLista ?? [],
                                                ])
                                            </td>
                                        @endif
                                        @php
                                            $campoObs = $columnaObs['campo'];
                                            $valorObs = $fila['notas'][$campoObs] ?? '';
                                        @endphp
                                        <td class="se-calif-prim-mat-col-obs border border-accent-200 p-1">
                                            <textarea id="se-calif-prim-mat-{{ $idMatricula }}-{{ $campoObs }}"
                                                      rows="2"
                                                      maxlength="{{ $maxObsCalif }}"
                                                      autocomplete="off"
                                                      wire:key="prim-mat-cell-{{ $materiaId }}-{{ $etapa }}-{{ $idMatricula }}-{{ $campoObs }}"
                                                      title="{{ $maxObsCalif }} caracteres máx."
                                                      class="se-calif-prim-mat-obs-input rounded border border-accent-200 leading-relaxed focus:border-primary-500 focus:ring-1 focus:ring-primary-500">{{ $valorObs }}</textarea>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
