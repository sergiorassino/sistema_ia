{{-- EPQ secundario: carga por curso/materia. Guardado vía `saveCell` + delegación focusout en app.js (`data-se-calif-tbody`). --}}
@php
    use App\Support\CalificacionesSecundario\Epq\CalificacionesEpqSecundarioCatalogo;

    $modoPortalDocente = $modoPortalDocente ?? false;
    $soloLectura = $soloLectura ?? false;
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
    $pdfUrl = $pdfUrl ?? null;
    $urlLista = $urlLista ?? null;
    $gruposCuat = CalificacionesEpqSecundarioCatalogo::gruposCuatrimestre();
    $columnasFinales = CalificacionesEpqSecundarioCatalogo::columnasFinales();
    $totalColumnas = 2 + count(CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA);
@endphp
<div>
<div class="mx-auto w-full max-w-7xl space-y-6">
    <style>
        table.se-calif-grid-epq {
            --epq-s: 1.1;
            table-layout: fixed;
            width: max-content;
            font-size: calc(10px * var(--epq-s)) !important;
            flex-shrink: 0;
        }
        table.se-calif-grid-epq th, table.se-calif-grid-epq td { line-height: 1 !important; }
        table.se-calif-grid-epq .se-epq-sec-col-nro { width: calc(2rem * var(--epq-s)); }
        table.se-calif-grid-epq .se-epq-sec-col-estudiante {
            width: calc(14rem * var(--epq-s));
            text-align: left;
        }
        table.se-calif-grid-epq .se-epq-sec-col-nota { width: calc(2.75rem * var(--epq-s)); }
        table.se-calif-grid-epq .se-epq-sec-col-destacada { width: calc(5.5rem * var(--epq-s)); }
        table.se-calif-grid-epq thead tr:first-child { height: calc(34px * var(--epq-s)) !important; font-size: calc(10px * var(--epq-s)); }
        table.se-calif-grid-epq thead tr:nth-child(2) { height: calc(28px * var(--epq-s)) !important; font-size: calc(9px * var(--epq-s)); }
        table.se-calif-grid-epq thead .se-epq-sec-col-nro { font-size: calc(7px * var(--epq-s)); }
        table.se-calif-grid-epq thead .se-epq-sec-th-sub { font-size: calc(7px * var(--epq-s)); }
        table.se-calif-grid-epq thead .se-epq-sec-th-final { font-size: calc(9px * var(--epq-s)); }
        table.se-calif-grid-epq tbody tr { font-size: calc(11px * var(--epq-s)); }
        table.se-calif-grid-epq th.se-epq-sec-th-destacada,
        table.se-calif-grid-epq th.se-epq-sec-th-destacada strong {
            font-weight: 700 !important;
            color: #333333;
        }
        table.se-calif-grid-epq .se-epq-sec-col-destacada input[type="text"] { font-weight: 600; }
        table.se-calif-grid-epq input[type="text"]{
            height: calc(18px * var(--epq-s)) !important;
            padding: 0 !important;
            line-height: 1 !important;
            font-size: calc(10px * var(--epq-s)) !important;
            min-width: 0 !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        table.se-calif-grid-epq .se-epq-sec-input-alumno {
            font-size: calc(10px * var(--epq-s)) !important;
        }
    </style>

    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">{{ $modoPortalDocente ? 'Portal docente · Secundario EPQ' : 'Calificaciones · Secundario EPQ' }}</p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ $modoPortalDocente ? 'Calificaciones' : 'Carga de calificaciones' }}
                </h2>
                <p class="max-w-2xl text-sm text-white/80">
                    {{ schoolCtx()->nivelNombre() }} · Ciclo lectivo {{ schoolCtx()->terlecAno() }}
                    @if ($modoPortalDocente && $cursoId && $materiaId)
                        <span class="block sm:inline sm:before:content-['·'] sm:before:mx-2">
                            <span class="font-semibold text-white">{{ $cursoLabel ?? '—' }}</span>
                            · <span class="font-semibold text-white">{{ $materiaLabel ?? '—' }}</span>
                        </span>
                    @endif
                </p>
                @if ($soloLectura)
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-200/95">
                        Solo consulta — la carga está deshabilitada
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap shrink-0 items-center gap-2">
                @if ($pdfUrl && $cursoId && $materiaId)
                    <a href="{{ $pdfUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Imprimir
                    </a>
                @endif
                <a href="{{ $modoPortalDocente ? ($urlLista ?? route('portalDocente.calificaciones')) : route('dashboard') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $modoPortalDocente ? 'Volver al listado' : 'Volver al panel' }}
                </a>
            </div>
        </div>
    </section>

    @if (! $modoPortalDocente)
    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="se-calif-epq-curso" class="form-label">Curso</label>
                <select id="se-calif-epq-curso" wire:model.live="cursoId" class="form-select w-full mt-1.5">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-calif-epq-materia" class="form-label">Materia</label>
                <select id="se-calif-epq-materia" wire:model.live="materiaId" class="form-select mt-1.5 w-full" @disabled(! $cursoId)>
                    <option value="">— Seleccione —</option>
                    @foreach ($materias as $m)
                        <option value="{{ $m->id }}">{{ trim((string) ($m->materia ?? '')) !== '' ? $m->materia : ('ID ' . $m->id) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    @endif

    @if ($cursoId && $materiaId)
        <div class="se-card px-5 py-3">
            <p class="text-sm text-neutral-600">
                @if (! $modoPortalDocente)
                    <span class="font-semibold text-neutral-800">{{ $cursoLabel ?? '—' }}</span>
                    <span class="mx-1.5 text-neutral-400">·</span>
                    <span class="font-semibold text-neutral-800">{{ $materiaLabel ?? '—' }}</span>
                    <span class="mx-1.5 text-neutral-400 hidden sm:inline">·</span>
                @endif
                <span class="mt-1 block text-xs text-neutral-500 sm:mt-0 sm:inline">
                    @if ($soloLectura)
                        Visualización de calificaciones (solo lectura).
                    @else
                        Los datos se guardan al salir de cada celda.
                    @endif
                </span>
            </p>
        </div>

        <div class="se-card overflow-hidden p-2 sm:p-3">
            <div class="w-full overflow-x-auto se-grid-angosta-wrap">
                <table class="se-calif-grid-epq border-collapse leading-none rounded-xl border border-accent-200 bg-white shadow-sm">
                    <thead class="sticky top-0 z-[1] bg-accent-50 text-neutral-900 shadow-sm shadow-neutral-900/5">
                        <tr class="leading-tight">
                            <th rowspan="2" class="se-epq-sec-col-nro border border-accent-200 px-0.5 py-1 text-center">#</th>
                            <th rowspan="2" class="se-epq-sec-col-estudiante border border-accent-200 px-1 py-1 text-left">Estudiante</th>
                            @foreach ($gruposCuat as $grupo)
                                <th colspan="{{ count($grupo['cols']) }}" class="border border-accent-200 px-1 py-2 text-center font-semibold">
                                    {{ $grupo['label'] }}
                                </th>
                            @endforeach
                            @foreach ($columnasFinales as $col)
                                @php $destacada = CalificacionesEpqSecundarioCatalogo::esCampoDestacado($col['field']); @endphp
                                <th rowspan="2" @class([
                                    'se-epq-sec-th-final border border-accent-200 px-0.5 py-2 text-center align-middle leading-tight',
                                    'se-epq-sec-col-nota' => ! $destacada,
                                    'se-epq-sec-col-destacada se-epq-sec-th-destacada' => $destacada,
                                ])>
                                    @if ($destacada)
                                        <strong>{{ $col['label'] }}</strong>
                                    @else
                                        {{ $col['label'] }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                        <tr class="leading-tight bg-accent-50/90">
                            @foreach ($gruposCuat as $grupo)
                                @foreach ($grupo['cols'] as $col)
                                    @php $destacada = CalificacionesEpqSecundarioCatalogo::esCampoDestacado($col['field']); @endphp
                                    <th @class([
                                        'se-epq-sec-th-sub border border-accent-200 px-0 py-1.5 text-center',
                                        'se-epq-sec-col-nota' => ! $destacada,
                                        'se-epq-sec-col-destacada se-epq-sec-th-destacada' => $destacada,
                                    ])>
                                        @if ($destacada)
                                            <strong>{{ $col['label'] }}</strong>
                                        @else
                                            {{ $col['label'] }}
                                        @endif
                                    </th>
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>
                            <tbody
                                class="bg-white"
                                data-se-calif-tbody
                                data-se-calif-activa="{{ $notasPermitidasActiva ? '1' : '0' }}"
                                data-se-calif-solo-lectura="{{ $soloLectura ? '1' : '0' }}"
                                data-se-calif-allowed='@json($notasPermitidasLista ?? [])'
                            >
                                @forelse ($rows as $row)
                                    <tr class="transition-colors hover:bg-accent-50/60" wire:key="row-epq-{{ (int) $materiaId }}-{{ (int) $row['id'] }}">
                                        <td class="se-epq-sec-col-nro border border-accent-200 px-1 py-0.5 text-center text-neutral-700 bg-accent-50/80">
                                            {{ $row['ord'] ?? '' }}
                                        </td>
                                        <td class="se-epq-sec-col-estudiante border border-accent-200 px-1 py-0.5 text-neutral-800 bg-accent-50/80">
                                            <input type="text"
                                                   readonly
                                                   aria-readonly="true"
                                                   tabindex="-1"
                                                   class="se-epq-sec-input-alumno w-full bg-transparent border-0 p-0 m-0 leading-tight text-neutral-800 truncate focus:outline-none focus:ring-0"
                                                   value="{{ $row['alumno'] ?? '—' }}"
                                                   title="{{ $row['alumno'] ?? '—' }}" />
                                        </td>
                                        @foreach (CalificacionesEpqSecundarioCatalogo::CAMPOS_NOTA as $field)
                                            @php
                                                $valor = $row[$field] ?? '';
                                                $notaNum = is_numeric($valor) && $valor !== '' ? (float) $valor : null;
                                                $destacada = CalificacionesEpqSecundarioCatalogo::esCampoDestacado($field);
                                            @endphp
                                            <td @class([
                                                'border border-accent-200 px-0.5 py-0.5',
                                                'se-epq-sec-col-nota' => ! $destacada,
                                                'se-epq-sec-col-destacada' => $destacada,
                                            ])>
                                                <input
                                                    id="se-calif-{{ (int) $row['id'] }}-{{ $field }}"
                                                    @readonly($soloLectura)
                                                    @class([
                                                        'w-full text-center leading-none border border-accent-200 rounded !px-0 !py-0',
                                                        'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
                                                        'focus:border-primary-500 focus:ring-1 focus:ring-primary-500' => ! $soloLectura,
                                                        'font-semibold text-red-600' => $notaNum !== null && $notaNum < 6,
                                                    ])
                                                    maxlength="2"
                                                    value="{{ $valor }}"
                                                    wire:key="cell-epq-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-{{ $field }}"
                                                />
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $totalColumnas }}" class="border border-accent-200 px-4 py-8 text-center text-sm text-neutral-600">
                                            No hay alumnos con calificaciones registradas para esta materia.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
            </div>
        </div>
    @elseif (! $modoPortalDocente)
        <div class="se-card px-5 py-8">
            <p class="text-center text-sm text-neutral-600 sm:text-left">
                Seleccioná un curso y después una materia para cargar la planilla.
            </p>
        </div>
    @endif
</div>

    @if ($mostrarModalNotasOff)
        @teleport('body')
        <div class="fixed inset-0 z-[1100] flex items-center justify-center overflow-y-auto px-4 py-3 sm:px-6 sm:py-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-notas-off-epq-titulo"
             wire:key="modal-notas-off-carga-epq">
            <div class="absolute inset-0 bg-neutral-900/55 backdrop-blur-sm"
                 wire:click="aceptarAvisoCargaNotasOff"
                 aria-hidden="true"></div>
            <div class="relative z-10 my-auto flex w-full max-w-md max-h-[calc(100dvh-1.75rem)] flex-col overflow-hidden rounded-2xl border border-accent-200 bg-white shadow-xl ring-1 ring-black/5 sm:max-h-[min(calc(100dvh-2rem),40rem)]"
                 @click.stop>
                <div class="shrink-0 border-b border-accent-200 px-5 py-4">
                    <h3 id="modal-notas-off-epq-titulo" class="text-base font-bold text-neutral-900">Carga de calificaciones</h3>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <p class="text-sm text-neutral-700 leading-relaxed whitespace-pre-line">{{ $mensajeNotasOff }}</p>
                    <p class="mt-3 text-xs text-neutral-500">
                        Podrá consultar las calificaciones en modo solo lectura.
                    </p>
                </div>
                <div class="shrink-0 flex justify-end border-t border-accent-200 bg-accent-50 px-5 py-3">
                    <button type="button"
                            wire:click="aceptarAvisoCargaNotasOff"
                            class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        Aceptar
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
