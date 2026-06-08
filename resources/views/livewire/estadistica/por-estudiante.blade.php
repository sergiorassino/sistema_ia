<div class="se-page !max-w-none min-w-0">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-eyebrow">Estadísticas</p>
                <h2 class="text-2xl font-bold tracking-tight">Estadística por estudiante</h2>
                <p class="text-sm text-white/80">Desempeño por alumno — durante el año, Diciembre y Febrero</p>
            </div>
            <a href="{{ route('estadistica.rendimiento') }}" class="btn-secondary !border-white/30 !bg-white/10 !text-white shrink-0">← Volver</a>
        </div>
    </section>

    <div class="se-card p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año lectivo</label>
                <div class="form-input mt-1 bg-accent-100 text-neutral-600 cursor-not-allowed w-auto min-w-[5rem]">{{ $anoLabel ?: '—' }}</div>
            </div>
            <div>
                <label for="curso-est" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Por curso</label>
                <select wire:model.live="cursoId" id="curso-est" class="form-input mt-1 min-w-[10rem]">
                    <option value="0">— Todos —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c['id'] }}">{{ $c['cursec'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="legajo-est" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Por estudiante</label>
                <select wire:model.live="legajoId" id="legajo-est" class="form-input mt-1 min-w-[16rem] max-w-full">
                    <option value="0">— Todos —</option>
                    @foreach ($alumnos as $a)
                        <option value="{{ $a['id'] }}">{{ $a['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="limpiarFiltros" class="btn-secondary">Limpiar</button>
        </div>
    </div>

    @if ($idTerlec <= 0)
        <div class="se-card p-6 text-center text-neutral-500 text-sm">Seleccioná un año lectivo en el contexto de sesión.</div>
    @elseif ($resumen !== null)
        @include('livewire.estadistica.partials.resumen-cards', ['resumen' => $resumen, 'anoLabel' => $anoLabel])

        @if ($resumen['total'] > 0)
            <p class="text-sm text-neutral-500 mb-2">
                Año: {{ $pctResumen[0] }}% —
                Dic: {{ $pctResumen[1] }}% —
                Feb: {{ $pctResumen[2] }}% —
                Pend: {{ $pctResumen[3] }}%
            </p>
            <p class="text-xs text-neutral-500 mb-4 leading-relaxed">
                En rojo: alumnos en riesgo (3 o más materias sin aprobar durante el año).<br>
                En ámbar: TEA o 25+ inasistencias (todas las materias reprobadas durante el año).<br>
                Fondo rosa en Boletín: tiene previas.
            </p>
        @endif

        @if (! empty($porEstudiante))
            <div class="se-card min-w-0 overflow-hidden mb-6">
                <h3 class="px-4 py-3 text-sm font-semibold text-neutral-800 border-b border-accent-200">Por estudiante</h3>
                <div class="w-full overflow-x-auto">
                    <table class="min-w-full text-sm" data-se-tabla-ordenable>
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header text-center num" data-sort-num="1">Nº</th>
                                <th class="table-header">Apellido y nombre</th>
                                <th class="table-header">Curso</th>
                                <th class="table-header text-right num" data-sort-num="1">Total</th>
                                <th class="table-header text-right num" data-sort-num="1">Año</th>
                                <th class="table-header text-right num" data-sort-num="1">Dic</th>
                                <th class="table-header text-right num" data-sort-num="1">Feb</th>
                                <th class="table-header text-right num" data-sort-num="1">Pend</th>
                                <th class="table-header text-right num" data-sort-num="1">Inas</th>
                                <th class="table-header text-center" data-sort-disabled="1">Boletín</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @foreach ($porEstudiante as $num => $r)
                                @php
                                    $nombreCompleto = trim(($r['apellido'] ?? '').', '.($r['nombre'] ?? ''));
                                    $sinAprobarDurante = (int) $r['total'] - (int) $r['durante_anio'];
                                    $sinNota = (int) ($r['sin_nota'] ?? 0);
                                    $pend = (int) ($r['pendientes'] ?? 0);
                                    $repCount = max(0, $pend - $sinNota);
                                    $tieneTea = ! empty($r['tiene_tea']);
                                    $inas = $inasPorLegajo[$r['idLegajos']] ?? 0;
                                    $inasRojo = $inas > 10;
                                    $mostrarAmarillo = $tieneTea || $inas >= 25;
                                    $mostrarRojo = ! $mostrarAmarillo && ($sinAprobarDurante - $sinNota) >= 3;
                                    $claseNombre = $mostrarAmarillo ? 'text-amber-700 font-semibold' : ($mostrarRojo ? 'text-red-600 font-semibold' : '');
                                    $tienePrevia = ! empty($previasPorLegajo[$r['idLegajos']]);
                                    $idMatricula = $matriculaPorLegajo[$r['idLegajos']] ?? 0;
                                @endphp
                                <tr class="hover:bg-accent-50/60">
                                    <td class="table-cell text-center tabular-nums">{{ $num + 1 }}</td>
                                    <td class="table-cell {{ $claseNombre }}">
                                        {{ $nombreCompleto }}
                                        @if ($repCount > 3)
                                            <span class="text-neutral-500 font-normal">(Rep: {{ $repCount }})</span>
                                        @endif
                                        @if ($sinNota > 0)
                                            <span class="text-neutral-500 font-normal">(Sin nota: {{ $sinNota }})</span>
                                        @endif
                                        @if ($tieneTea)
                                            <span class="text-neutral-500 font-normal">(TEA)</span>
                                        @endif
                                        @if ($inas >= 25)
                                            <span class="text-neutral-500 font-normal">(25+ inas)</span>
                                        @endif
                                    </td>
                                    <td class="table-cell">{{ $r['curso'] ?? '' }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['total'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['durante_anio'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['diciembre'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['febrero'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['pendientes'] ?? 0 }}</td>
                                    <td class="table-cell text-right tabular-nums {{ $inasRojo ? 'text-red-600 font-semibold' : '' }}">{{ $inas }}</td>
                                    <td class="table-cell text-center {{ $tienePrevia ? 'bg-pink-200/70' : '' }}">
                                        @if ($idMatricula > 0)
                                            <x-pdf-post-matricula
                                                :action="route('boletinesSecundario.pdf')"
                                                :matricula="$idMatricula"
                                                button-class="inline-flex items-center justify-center rounded-lg p-1.5 text-lg hover:opacity-80"
                                                title="Ver boletín"
                                            >📋</x-pdf-post-matricula>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                $chartDataBarras = [
                    'labels' => $chartBarras['labels'],
                    'datasets' => [
                        ['label' => 'Año (%)', 'data' => $chartBarras['durante'], 'backgroundColor' => '#198754', 'stack' => 's1'],
                        ['label' => 'Dic (%)', 'data' => $chartBarras['diciembre'], 'backgroundColor' => '#0d6efd', 'stack' => 's1'],
                        ['label' => 'Feb (%)', 'data' => $chartBarras['febrero'], 'backgroundColor' => '#fd7e14', 'stack' => 's1'],
                        ['label' => 'Pend (%)', 'data' => $chartBarras['pendientes'], 'backgroundColor' => '#6c757d', 'stack' => 's1'],
                    ],
                ];
            @endphp
            <div class="se-card p-4 mb-6" wire:key="chart-est-{{ $cursoId }}-{{ $legajoId }}">
                <h3 class="text-sm font-semibold text-neutral-800 mb-3">Gráfico por estudiante (%)</h3>
                <div class="relative w-full se-estad-chart-panel" data-se-estad-chart-panel style="height: {{ max(280, count($porEstudiante) * 36) }}px;">
                    @include('livewire.estadistica.partials.chart-canvas', [
                        'canvasId' => 'chartEstadEstudiantes',
                        'chartType' => 'bar',
                        'chartData' => $chartDataBarras,
                        'horizontal' => true,
                        'stacked' => true,
                    ])
                </div>
            </div>
        @endif

        @if ($resumen['total'] > 0)
            @php
                $chartDataTorta = [
                    'labels' => ['Año', 'Dic', 'Feb', 'Pend'],
                    'datasets' => [[
                        'data' => $pctResumen,
                        'backgroundColor' => ['#198754', '#0d6efd', '#fd7e14', '#6c757d'],
                        'borderWidth' => 1,
                    ]],
                ];
            @endphp
            <div class="se-card p-4 max-w-md" wire:key="chart-torta-est-{{ $cursoId }}-{{ $legajoId }}">
                <h3 class="text-sm font-semibold text-neutral-800 mb-3">Distribución de aprobación (%)</h3>
                <div class="relative w-full h-72 se-estad-chart-panel" data-se-estad-chart-panel>
                    @include('livewire.estadistica.partials.chart-canvas', [
                        'canvasId' => 'chartEstadTortaEstudiante',
                        'chartType' => 'doughnut',
                        'chartData' => $chartDataTorta,
                    ])
                </div>
            </div>
        @endif

        @include('livewire.estadistica.partials.tabla-ordenable-script')

        @script
        <script>
            @include('livewire.estadistica.partials.estadistica-charts-init')
        </script>
        @endscript
    @endif
</div>
