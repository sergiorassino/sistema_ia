{{-- Formulario por alumno: materias (ord) × ic01/ic02/ic03 + obs matrícula. Guardado por celda al salir del campo. --}}
@php
    use App\Support\CalificacionesPrimario\CalificacionesPrimarioCatalogo;
@endphp
<div class="mx-auto w-full max-w-[98rem] space-y-6">
    <style>
        table.se-calif-prim-grid { table-layout: fixed; width: 100%; min-width: 720px; font-size: 11px; }
        table.se-calif-prim-grid th,
        table.se-calif-prim-grid td { padding: 4px 3px; line-height: 1.2; }
        table.se-calif-prim-grid input[type="text"] {
            width: 100%;
            height: 22px;
            padding: 0 4px;
            font-size: 11px;
            text-align: center;
            box-sizing: border-box;
        }
        table.se-calif-prim-grid input:disabled {
            background: #e8ecef;
            color: #6b7280;
            cursor: not-allowed;
        }
        table.se-calif-prim-grid .se-calif-prim-row-label {
            width: 7.5rem;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }
        table.se-calif-prim-grid thead th.se-calif-prim-col-abrev {
            min-width: 2.75rem;
            max-width: 4.5rem;
            line-height: 1.15;
            word-break: break-word;
        }
    </style>

    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Primario</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de calificaciones por estudiante</h2>
                <p class="max-w-3xl text-sm text-white/80">
                    <span class="font-semibold text-white">{{ $alumnoLinea }}</span>
                    <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">{{ $cursoLabel }}</span>
                    <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">Ciclo lectivo {{ schoolCtx()->terlecAno() }}</span>
                </p>
            </div>
            <a href="{{ route('calificacionesPrimario.carga', ['curso' => $cursoId]) }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver
            </a>
        </div>
    </section>

    @if ($notasPermitidasActiva)
        <p class="text-xs text-neutral-500">
            Elija la nota del desplegable (flecha) o escriba con el teclado. En el listado: ↑↓, Enter y Escape.
            Use las flechas para moverse entre celdas y Enter para pasar a la siguiente.
        </p>
    @endif

    @if ($materiasLista === [])
        <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
            Este curso no tiene materias configuradas en el ciclo lectivo activo. Revise la asignación de materias del plan.
        </div>
    @else
    <div class="se-card overflow-x-auto p-4">
        <table class="se-calif-prim-grid border-collapse border border-accent-200">
            <thead>
                <tr class="bg-accent-50">
                    <th class="se-calif-prim-row-label border border-accent-200 px-2 py-2 text-[10px] uppercase tracking-wide text-neutral-600"></th>
                    @foreach ($materiasLista as $m)
                        <th class="se-calif-prim-col-abrev border border-accent-200 px-1 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-neutral-800"
                            title="{{ trim((string) ($m['materia'] ?? '')) !== '' ? $m['materia'] : 'Materia' }}">
                            {{ CalificacionesPrimarioCatalogo::etiquetaEncabezadoColumna($m) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody
                data-se-calif-prim-tbody
                data-se-calif-prim-activa="{{ $notasPermitidasActiva ? '1' : '0' }}"
                data-se-calif-prim-allowed='@json($notasPermitidasLista ?? [])'>
                @foreach (['ic01' => 'Etapa 1', 'ic02' => 'Etapa 2', 'ic03' => 'Nota anual'] as $campo => $etiquetaFila)
                    <tr wire:key="calif-prim-fila-{{ $campo }}">
                        <td class="se-calif-prim-row-label border border-accent-200 bg-accent-50/80 px-2 text-[10px] uppercase text-neutral-600">
                            {{ $etiquetaFila }}
                        </td>
                        @foreach ($materiasLista as $m)
                            @php
                                $ord = (int) $m['ord'];
                                $valor = $notas[$ord][$campo] ?? '';
                            @endphp
                            <td class="border border-accent-200 p-0.5">
                                @include('livewire.calificaciones-primario.partials.celda-nota-permitida', [
                                    'id' => 'se-calif-prim-'.$ord.'-'.$campo,
                                    'value' => $valor,
                                    'wireKey' => 'prim-cell-'.$idMatricula.'-'.$ord.'-'.$campo,
                                    'notasPermitidasActiva' => $notasPermitidasActiva,
                                    'notasPermitidasLista' => $notasPermitidasLista ?? [],
                                ])
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="se-card p-5">
            <label for="obs1" class="form-label">Obs. Etapa 1 <span class="font-normal normal-case text-neutral-500">(1200 caract. máx.)</span></label>
            <textarea id="obs1"
                      rows="5"
                      maxlength="1200"
                      wire:model="obs1"
                      wire:blur="saveObservacion('obs1', $event.target.value)"
                      class="mt-2 w-full rounded-2xl border border-accent-200 bg-white px-3 py-2 text-sm leading-relaxed focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
        </div>
        <div class="se-card p-5">
            <label for="obs2" class="form-label">Obs. Etapa 2 <span class="font-normal normal-case text-neutral-500">(1200 caract. máx.)</span></label>
            <textarea id="obs2"
                      rows="5"
                      maxlength="1200"
                      wire:model="obs2"
                      wire:blur="saveObservacion('obs2', $event.target.value)"
                      class="mt-2 w-full rounded-2xl border border-accent-200 bg-white px-3 py-2 text-sm leading-relaxed focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
        </div>
    </div>

    <div class="se-card p-5">
        <label for="obsAnual" class="form-label">Observaciones Aprec. Final</label>
        <input type="text"
               id="obsAnual"
               maxlength="500"
               wire:model="obsAnual"
               wire:blur="saveObservacion('obsAnual', $event.target.value)"
               class="mt-2 w-full rounded-xl border border-accent-200 bg-white px-3 py-2.5 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
    </div>
</div>
