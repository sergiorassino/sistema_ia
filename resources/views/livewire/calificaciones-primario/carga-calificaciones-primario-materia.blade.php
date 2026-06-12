{{-- Carga por materia: etapa → curso → materia → grilla alumnos × parciales + nota etapa (+ anual en 2ª). --}}
@php
    use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
@endphp
<div class="mx-auto w-full max-w-[98rem] space-y-6">
    <style>
        table.se-calif-prim-mat-grid { table-layout: fixed; width: 100%; min-width: 720px; font-size: 11px; }
        table.se-calif-prim-mat-grid th,
        table.se-calif-prim-mat-grid td { padding: 4px 3px; line-height: 1.2; }
        table.se-calif-prim-mat-grid input[type="text"] {
            width: 100%;
            height: 22px;
            padding: 0 4px;
            font-size: 11px;
            text-align: center;
            box-sizing: border-box;
        }
        table.se-calif-prim-mat-grid input:disabled {
            background: #e8ecef;
            color: #6b7280;
            cursor: not-allowed;
        }
        table.se-calif-prim-mat-grid .se-calif-prim-mat-col-alumno {
            width: 11rem;
            text-align: left;
            font-weight: 600;
        }
        table.se-calif-prim-mat-grid thead th.se-calif-prim-mat-col-nota {
            min-width: 2.5rem;
            max-width: 3.5rem;
            line-height: 1.15;
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
                        <table class="se-calif-prim-mat-grid min-w-[720px] border-collapse border border-accent-200">
                            <thead>
                                <tr class="bg-accent-50">
                                    <th class="se-calif-prim-mat-col-alumno border border-accent-200 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-neutral-600">
                                        Estudiante
                                    </th>
                                    @foreach ($columnasParciales as $col)
                                        <th class="se-calif-prim-mat-col-nota border border-accent-200 px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-neutral-700"
                                            title="Calificación parcial">
                                            {{ $col['etiqueta'] }}
                                        </th>
                                    @endforeach
                                    <th class="se-calif-prim-mat-col-nota border border-accent-200 px-1 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-primary-800"
                                        title="{{ $columnaFinalEtapa['etiqueta'] }}">
                                        {{ $columnaFinalEtapa['etiqueta'] }}
                                    </th>
                                    @if ($columnaAnual)
                                        <th class="se-calif-prim-mat-col-nota border border-accent-200 px-1 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-primary-800"
                                            title="{{ $columnaAnual['etiqueta'] }}">
                                            {{ $columnaAnual['etiqueta'] }}
                                        </th>
                                    @endif
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
                                    <tr wire:key="calif-prim-mat-row-{{ $materiaId }}-{{ $etapa }}-{{ $idMatricula }}" class="hover:bg-accent-50/40">
                                        <td class="se-calif-prim-mat-col-alumno border border-accent-200 bg-white px-2 py-1 text-[11px] text-neutral-800">
                                            {{ $fila['alumno'] }}
                                        </td>
                                        @foreach ($columnasParciales as $col)
                                            @php
                                                $campo = $col['campo'];
                                                $valor = $fila['notas'][$campo] ?? '';
                                            @endphp
                                            <td class="border border-accent-200 p-0.5">
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
                                        <td class="border border-accent-200 bg-accent-50/30 p-0.5">
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
                                            <td class="border border-accent-200 bg-accent-50/30 p-0.5">
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
