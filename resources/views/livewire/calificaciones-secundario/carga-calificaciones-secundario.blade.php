{{-- Módulo calificacionesSecundario: carga de calificaciones (UI). Guardado vía `saveCell`: TEA con `wire:change`; el resto de inputs numéricos con delegación `focusout` en `tbody` (validación de notas permitidas en el navegador, ver `app.js`). --}}
@php
    $modoPortalDocente = $modoPortalDocente ?? false;
    $soloLectura = $soloLectura ?? false;
    $mostrarModalNotasOff = $mostrarModalNotasOff ?? false;
    $mensajeNotasOff = $mensajeNotasOff ?? '';
    $pdfUrl = $pdfUrl ?? null;
    $urlLista = $urlLista ?? null;
@endphp
<div>
<div class="mx-auto w-full max-w-[98rem] space-y-6">
    <style>
        /* Override inline para evitar “celdas enormes” por estilos globales/caché.
           Importante: va dentro del root del componente (Livewire requiere un solo root). */
        table.se-calif-grid { table-layout: fixed !important; font-size: 10px !important; }
        table.se-calif-grid th, table.se-calif-grid td { line-height: 1 !important; }
        /* Permite que la tabla ocupe el ancho disponible, pero mantenga scroll si no entra */
        table.se-calif-grid { width: 100% !important; min-width: 980px; }
        /* Header: aumentar altura de filas de etiquetas (Estudiante/Eval) y sub-etiquetas (N/R1/R2). */
        table.se-calif-grid thead th { line-height: 1.15 !important; }
        table.se-calif-grid thead tr:first-child { height: 34px !important; }
        table.se-calif-grid thead tr:nth-child(2) { height: 28px !important; }
        table.se-calif-grid thead tr:first-child th { padding-top: 7px !important; padding-bottom: 7px !important; min-height: 34px !important; }
        table.se-calif-grid thead tr:nth-child(2) th { padding-top: 6px !important; padding-bottom: 6px !important; min-height: 28px !important; }
        table.se-calif-grid input[type="text"]{
            height: 18px !important;
            padding: 0 !important;
            line-height: 1 !important;
            font-size: 10px !important;
            min-width: 0 !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        table.se-calif-grid input[type="checkbox"]{ height: 14px !important; width: 14px !important; }
    </style>
    <section class="se-hero">
        <div class="se-hero-inner flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-2">
                <p class="se-eyebrow">{{ $modoPortalDocente ? 'Portal docente · Secundario' : 'Calificaciones · Secundario' }}</p>
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
                @if ($modoPortalDocente && $pdfUrl && $cursoId && $materiaId)
                    <a href="{{ $pdfUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Exportar PDF
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
    {{-- Paso 1/2: selección de curso y materia. `wire:model.live` dispara los `updated*()` del componente. --}}
    <div class="se-toolbar flex-col !items-stretch gap-4 lg:flex-row lg:items-end">
        <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="se-calif-curso" class="form-label">Curso</label>
                <select id="se-calif-curso" wire:model.live="cursoId" class="form-select w-full mt-1.5">
                    <option value="">— Seleccione —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->Id }}">{{ $c->nombreParaListado() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="se-calif-materia" class="form-label">Materia</label>
                <select id="se-calif-materia" wire:model.live="materiaId" class="form-select mt-1.5 w-full" @disabled(! $cursoId)>
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

        {{-- Grilla tipo planilla: scroll horizontal desde la izquierda (sidebar). --}}
        <div class="se-card overflow-hidden p-2 sm:p-3">
            <div class="w-full overflow-x-auto">
                <div class="flex justify-start">
                    <div class="w-full rounded-xl border border-accent-200 bg-white shadow-sm">
                <table class="se-calif-grid w-full border-collapse table-fixed text-[10px] leading-none">
                    <colgroup>
                        {{-- Fijas: para que la grilla “anclada” siga siendo usable --}}
                        <col style="width:20px">
                        <col style="width:208px">
                        @for ($e = 1; $e <= 8; $e++)
                            {{-- Notas: sin ancho fijo -> se reparten el ancho disponible (responsive) --}}
                            <col>
                            <col>
                            <col>
                            @if ($e < 8)
                                {{-- Separación visual entre bloques (columna vacía muy angosta). --}}
                                <col style="width:6px">
                            @endif
                        @endfor
                        <col style="width:6px">
                        <col>
                        <col>
                        <col style="width:6px">
                        <col>
                        <col>
                        <col style="width:6px">
                        <col>
                        <col>
                        {{-- Pr.Final: doble de ancho (promedio, solo lectura). --}}
                        <col style="width:50px">
                        <col style="width:32px">
                    </colgroup>
                    {{-- Encabezado en 2 filas: títulos de bloque + subcolumnas (N/R1/R2, etc.). --}}
                    <thead class="sticky top-0 z-[1] bg-accent-50 text-neutral-900 shadow-sm shadow-neutral-900/5">
                        <tr class="text-[10px] leading-tight">
                            <th class="border border-accent-200 px-0.5 py-1 text-center text-[7px] w-[20px]">Ord</th>
                            <th class="border border-accent-200 px-1 py-1 text-left">Estudiante</th>
                            @for ($e = 1; $e <= 8; $e++)
                                <th colspan="3" class="border border-accent-200 px-1 py-2 text-center">Eval. {{ $e }}</th>
                                @if ($e < 8)
                                    <th class="border border-accent-200 p-0" aria-hidden="true"></th>
                                @endif
                            @endfor
                            <th class="border border-accent-200 p-0" aria-hidden="true"></th>
                            <th colspan="2" class="border border-accent-200 px-1 py-2 text-center">JIS 1</th>
                            <th class="border border-accent-200 p-0" aria-hidden="true"></th>
                            <th colspan="2" class="border border-accent-200 px-1 py-2 text-center">JIS 2</th>
                            <th class="border border-accent-200 p-0" aria-hidden="true"></th>
                            {{-- Dic/Feb/Pr.Final/TEA: rowspan=2 para “fusionar” con la fila de subencabezado vacía. --}}
                            <th rowspan="2" class="border border-accent-200 px-1 py-2 text-center align-middle">Dic</th>
                            <th rowspan="2" class="border border-accent-200 px-1 py-2 text-center align-middle">Feb</th>
                            <th rowspan="2" class="border border-accent-200 px-1 py-2 text-center align-middle font-bold">Pr.Final</th>
                            <th rowspan="2" class="border border-accent-200 px-1 py-2 text-center align-middle">TEA</th>
                        </tr>
                        <tr class="text-[9px] leading-tight bg-accent-50/90">
                            <th class="border border-accent-200 px-1 py-1"></th>
                            <th class="border border-accent-200 px-1 py-1"></th>
                            @for ($e = 1; $e <= 8; $e++)
                                <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">N</th>
                                <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">R1</th>
                                <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">R2</th>
                                @if ($e < 8)
                                    <th class="border border-accent-200 p-0 bg-white" aria-hidden="true"></th>
                                @endif
                            @endfor
                            <th class="border border-accent-200 p-0 bg-white" aria-hidden="true"></th>
                            <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">N</th>
                            <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">R</th>
                            <th class="border border-accent-200 p-0 bg-white" aria-hidden="true"></th>
                            <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">N</th>
                            <th class="border border-accent-200 px-0 py-1.5 text-center text-[7px]">R</th>
                            <th class="border border-accent-200 p-0 bg-white" aria-hidden="true"></th>
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
                            {{-- `wire:key` incluye `materiaId` para forzar recreación de inputs al cambiar de materia (evita valores “pegados” del DOM). --}}
                            <tr class="text-[11px] transition-colors hover:bg-accent-50/60" wire:key="row-{{ (int) $materiaId }}-{{ (int) $row['id'] }}">
                                <td class="border border-accent-200 px-1 py-0.5 text-center text-neutral-700 bg-accent-50/80">
                                    {{ $row['ord'] ?? '' }}
                                </td>
                                <td class="border border-accent-200 px-1 py-0.5 text-neutral-800 bg-accent-50/80">
                                    <input
                                        type="text"
                                        readonly
                                        aria-readonly="true"
                                        tabindex="-1"
                                        class="w-full bg-transparent border-0 p-0 m-0 text-[10px] leading-tight text-neutral-800 truncate focus:outline-none focus:ring-0"
                                        value="{{ $row['alumno'] ?? '—' }}"
                                        title="{{ $row['alumno'] ?? '—' }}"
                                    />
                                </td>

                                @php
                                    // Orden físico de columnas `ic**` tal como se renderizan en la tabla (debe coincidir con los separadores).
                                    $map = [
                                        // Eval 1..8 (N,R1,R2) => ic01..ic24
                                        'ic01','ic02','ic03','ic04','ic05','ic06','ic07','ic08','ic09','ic10','ic11','ic12',
                                        'ic13','ic14','ic15','ic16','ic17','ic18','ic19','ic20','ic21','ic22','ic23','ic24',
                                        // JIS 1/2 => ic25..ic28
                                        'ic25','ic26','ic27','ic28',
                                    ];
                                @endphp

                                @foreach ($map as $i => $field)
                                    @php
                                        $idx = (int) $i; // 0-based
                                        // Fin de bloque Eval: cada 3 campos (N/R1/R2). Además, luego de E8 hay separación antes de JIS1.
                                        $isEvalBlockEnd = $idx <= 23 && (($idx + 1) % 3 === 0); // ic01..ic24 (E1..E8) cada 3
                                        // Fin de bloque JIS: separación después de R de JIS1 y JIS2.
                                        $isJisBlockEnd = $idx === 25 || $idx === 27; // ic26 fin JIS1, ic28 fin JIS2
                                    @endphp
                                    <td class="border border-accent-200 px-0.5 py-0.5">
                                        <input
                                            id="se-calif-{{ (int) $row['id'] }}-{{ $field }}"
                                            @readonly($soloLectura)
                                            @class([
                                                'w-full h-[18px] text-center text-[10px] leading-none border border-accent-200 rounded !px-0 !py-0',
                                                'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
                                                'focus:border-primary-500 focus:ring-1 focus:ring-primary-500' => ! $soloLectura,
                                            ])
                                            maxlength="2"
                                            value="{{ $row[$field] ?? '' }}"
                                            wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-{{ $field }}"
                                        />
                                    </td>
                                    @if ($isEvalBlockEnd && $idx < 23)
                                        <td class="border border-accent-200 p-0 bg-white" aria-hidden="true"></td>
                                    @elseif ($isEvalBlockEnd && $idx === 23)
                                        <td class="border border-accent-200 p-0 bg-white" aria-hidden="true"></td>
                                    @elseif ($isJisBlockEnd && $idx === 25)
                                        <td class="border border-accent-200 p-0 bg-white" aria-hidden="true"></td>
                                    @elseif ($isJisBlockEnd && $idx === 27)
                                        <td class="border border-accent-200 p-0 bg-white" aria-hidden="true"></td>
                                    @endif
                                @endforeach

                                <td class="border border-accent-200 px-0.5 py-0.5">
                                    <input
                                        id="se-calif-{{ (int) $row['id'] }}-dic"
                                        @readonly($soloLectura)
                                        @class([
                                            'w-full h-[18px] text-center text-[10px] leading-none border border-accent-200 rounded !px-0 !py-0',
                                            'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
                                            'focus:border-primary-500 focus:ring-1 focus:ring-primary-500' => ! $soloLectura,
                                        ])
                                        maxlength="2"
                                        value="{{ $row['dic'] ?? '' }}"
                                        wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-dic"
                                    />
                                </td>
                                <td class="border border-accent-200 px-0.5 py-0.5">
                                    <input
                                        id="se-calif-{{ (int) $row['id'] }}-feb"
                                        @readonly($soloLectura)
                                        @class([
                                            'w-full h-[18px] text-center text-[10px] leading-none border border-accent-200 rounded !px-0 !py-0',
                                            'bg-accent-50/80 text-neutral-700 cursor-default' => $soloLectura,
                                            'focus:border-primary-500 focus:ring-1 focus:ring-primary-500' => ! $soloLectura,
                                        ])
                                        maxlength="2"
                                        value="{{ $row['feb'] ?? '' }}"
                                        wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-feb"
                                    />
                                </td>
                                <td class="border border-accent-200 px-0.5 py-0.5 bg-accent-50/90">
                                    <input
                                        id="se-calif-{{ (int) $row['id'] }}-calif"
                                        type="text"
                                        readonly
                                        tabindex="0"
                                        aria-readonly="true"
                                        class="w-full h-[18px] cursor-default text-center text-[10px] leading-none font-bold text-neutral-900 border border-accent-200 rounded !px-0 !py-0 bg-transparent focus:outline-none focus:ring-0"
                                        maxlength="5"
                                        value="{{ $row['calif'] ?? '' }}"
                                        wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-calif"
                                    />
                                </td>
                                <td class="border border-accent-200 px-0.5 py-0.5 text-center">
                                    <input
                                        type="checkbox"
                                        class="h-3.5 w-3.5 rounded border-accent-300 text-primary-600 focus:ring-primary-500 disabled:opacity-60"
                                        @checked((bool) ($row['tea'] ?? false))
                                        @disabled($soloLectura)
                                        wire:key="cell-{{ (int) $materiaId }}-{{ (int) $row['id'] }}-tea"
                                        @if (! $soloLectura)
                                            wire:change="saveCell({{ $row['id'] }}, 'tea', $event.target.checked)"
                                        @endif
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                {{-- Debe coincidir con la cantidad total de columnas de la tabla (incluye separadores). --}}
                                <td colspan="43" class="border border-accent-200 px-4 py-8 text-center text-sm text-neutral-600">
                                    No hay alumnos con calificaciones registradas para esta materia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                    </div>
                </div>
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
        @include('livewire.partials.modal-carga-notas-off', [
            'modalWireKey' => 'modal-notas-off-carga',
            'modalTituloId' => 'modal-notas-off-titulo',
        ])
    @endif
</div>
