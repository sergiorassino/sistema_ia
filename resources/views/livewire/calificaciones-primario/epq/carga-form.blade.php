@php
    use App\Support\CalificacionesPrimario\Epq\CalificacionesEpqCatalogo;
    $soloLectura = $soloLectura ?? false;
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
@endphp
<div>
<div class="mx-auto w-full max-w-5xl space-y-6">
    <style>
        table.se-epq-calif-grid {
            table-layout: fixed;
            width: max-content;
            font-size: 12.1px;
            flex-shrink: 0;
        }
        table.se-epq-calif-grid th,
        table.se-epq-calif-grid td {
            padding: 4.5px 7px;
            border: 1px solid #C1D7DA;
        }
        table.se-epq-calif-grid .se-epq-col-nro { width: 2.2rem; }
        table.se-epq-calif-grid .se-epq-col-materia {
            width: 15.4rem;
            text-align: left;
            font-weight: 600;
            font-size: 12.1px;
        }
        table.se-epq-calif-grid .se-epq-col-nota { width: 3.575rem; }
        table.se-epq-calif-grid .se-epq-col-destacada { width: 6.325rem; }
        table.se-epq-calif-grid thead th {
            font-size: 11px;
            line-height: 1.35;
        }
        table.se-epq-calif-grid th.se-epq-th-destacada,
        table.se-epq-calif-grid th.se-epq-th-destacada strong {
            font-weight: 700 !important;
            color: #333333;
            font-size: 12.1px;
        }
        table.se-epq-calif-grid tbody td.se-epq-col-nro,
        table.se-epq-calif-grid tbody td.se-epq-col-materia {
            font-size: 12.1px;
            line-height: 1.35;
        }
        table.se-epq-calif-grid input[type="text"] {
            width: 100%;
            height: 29px;
            padding: 0 7px;
            font-size: 12.1px;
            text-align: center;
            border-radius: 0.4125rem;
            border: 1px solid #C1D7DA;
            box-sizing: border-box;
        }
        table.se-epq-calif-grid input:focus {
            border-color: #40848D;
            outline: none;
            box-shadow: 0 0 0 1px #40848D;
        }
        table.se-epq-calif-grid .se-epq-col-destacada input { font-weight: 600; }
        table.se-epq-calif-grid td.p-1 { padding: 0.275rem; }
    </style>

    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">Calificaciones · Primario · EPQ</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Carga de calificaciones</h2>
                <p class="max-w-3xl text-sm text-white/80">
                    <span class="font-semibold text-white">{{ $alumnoLinea }}</span>
                    <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">{{ $cursoLabel }}</span>
                </p>
                @if ($soloLectura)
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-200/95">
                        Solo consulta — la carga está deshabilitada
                    </p>
                @endif
            </div>
            <a href="{{ \App\Support\PortalDocente\CalificacionesPrimarioPortalDocente::route('carga', ['curso' => $cursoId]) }}"
               class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Volver
            </a>
        </div>
    </section>

    <p class="text-xs text-neutral-500">
        @if ($soloLectura)
            Visualización de calificaciones (solo lectura).
        @else
            Los datos se guardan automáticamente al salir de cada celda. No se calcula ningún promedio.
        @endif
    </p>

    @if ($materiasLista === [])
        <div class="se-card px-5 py-8 text-center text-sm text-neutral-600">
            Este curso no tiene materias configuradas para el ciclo lectivo activo.
        </div>
    @else
        <div class="se-card p-[1.1rem]">
            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <table class="se-epq-calif-grid">
                    <thead class="bg-accent-50">
                        <tr>
                            <th class="se-epq-col-nro text-center uppercase text-neutral-500">#</th>
                            <th class="se-epq-col-materia uppercase text-neutral-600">Materia</th>
                            @foreach (CalificacionesEpqCatalogo::CAMPOS_NOTA as $campo)
                                @php $destacada = CalificacionesEpqCatalogo::esCampoDestacado($campo); @endphp
                                <th @class([
                                    'text-center uppercase text-neutral-700',
                                    'se-epq-col-nota' => ! $destacada,
                                    'se-epq-col-destacada se-epq-th-destacada' => $destacada,
                                ])>
                                    @if ($destacada)
                                        <strong>{{ CalificacionesEpqCatalogo::etiquetaCampoNota($campo) }}</strong>
                                    @else
                                        {{ CalificacionesEpqCatalogo::etiquetaCampoNota($campo) }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($materiasLista as $i => $m)
                            @php $idMaterias = (int) $m['id']; @endphp
                            <tr wire:key="epq-calif-{{ $idMaterias }}">
                                <td class="se-epq-col-nro text-center text-neutral-500">{{ $i + 1 }}</td>
                                <td class="se-epq-col-materia text-neutral-800">{{ $m['materia'] }}</td>
                                @foreach (CalificacionesEpqCatalogo::CAMPOS_NOTA as $campo)
                                    @php
                                        $valor = $notas[$idMaterias][$campo] ?? '';
                                        $destacada = CalificacionesEpqCatalogo::esCampoDestacado($campo);
                                    @endphp
                                    <td @class([
                                        'p-1',
                                        'se-epq-col-nota' => ! $destacada,
                                        'se-epq-col-destacada' => $destacada,
                                    ])>
                                        <input type="text"
                                               maxlength="15"
                                               value="{{ $valor }}"
                                               wire:key="epq-inp-{{ $idMaterias }}-{{ $campo }}"
                                               @readonly($soloLectura)
                                               @if (! $soloLectura) wire:blur="saveCell({{ $idMaterias }}, '{{ $campo }}', $event.target.value)" @endif
                                               @class([
                                                   'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
                                               ]) />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

    @include('livewire.partials.modal-carga-notas-off', [
        'modalWireKey' => 'modal-notas-off-epq-form',
        'modalTituloId' => 'modal-notas-off-epq-form-titulo',
    ])
</div>
