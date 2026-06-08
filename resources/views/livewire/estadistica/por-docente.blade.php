<div class="se-page !max-w-none min-w-0">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-eyebrow">Estadísticas</p>
                <h2 class="text-2xl font-bold tracking-tight">Estadística por docente</h2>
                <p class="text-sm text-white/80">Materias por docente — mismas métricas que por estudiante</p>
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
                <label for="profesor-filtro" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Por docente</label>
                <select wire:model.live="profesorId" id="profesor-filtro" class="form-input mt-1 min-w-[14rem] max-w-full">
                    <option value="0">— Todos —</option>
                    @foreach ($profesores as $pr)
                        <option value="{{ $pr['id'] }}">{{ $pr['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="limpiarFiltros" class="btn-secondary">Limpiar</button>
        </div>
    </div>

    @if ($idTerlec <= 0)
        <div class="se-card p-6 text-center text-neutral-500 text-sm">Seleccioná un año lectivo en el contexto de sesión.</div>
    @elseif (empty($porProfesor))
        <div class="se-card p-6 text-center text-neutral-500 text-sm">
            No hay datos de calificaciones para los docentes del año seleccionado, o no hay profesores con materias en nivel medio.
        </div>
    @else
        <h3 class="text-sm font-semibold text-neutral-800 mb-3">
            Por docente y materia @if ($anoLabel) — {{ $anoLabel }} @endif
        </h3>

        @foreach ($porProfesor as $bloque)
            <div class="se-card min-w-0 overflow-hidden mb-6">
                <div class="px-4 py-3 border-b border-accent-200">
                    <h4 class="text-sm font-semibold text-neutral-800">
                        {{ trim($bloque['apellido'].', '.$bloque['nombre']) }}
                    </h4>
                    <p class="text-xs text-neutral-500 mt-1">
                        Total: {{ $bloque['total'] }} —
                        Durante año: {{ $bloque['durante_anio'] }} —
                        Dic: {{ $bloque['diciembre'] }} —
                        Feb: {{ $bloque['febrero'] }} —
                        Pend: {{ $bloque['pendientes'] }}
                    </p>
                </div>
                <div class="w-full overflow-x-auto">
                    <table class="min-w-full text-sm" data-se-tabla-ordenable>
                        <thead class="bg-accent-50">
                            <tr>
                                <th class="table-header">Curso</th>
                                <th class="table-header">Materia</th>
                                <th class="table-header text-right num" data-sort-num="1">Total</th>
                                <th class="table-header text-right num" data-sort-num="1">Durante año</th>
                                <th class="table-header text-right num" data-sort-num="1">Diciembre</th>
                                <th class="table-header text-right num" data-sort-num="1">Febrero</th>
                                <th class="table-header text-right num" data-sort-num="1">Pendientes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-accent-200 bg-white">
                            @foreach ($bloque['materias'] as $r)
                                <tr class="hover:bg-accent-50/60">
                                    <td class="table-cell">{{ $r['curso'] }}</td>
                                    <td class="table-cell">{{ $r['materia'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['total'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['durante_anio'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['diciembre'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['febrero'] }}</td>
                                    <td class="table-cell text-right tabular-nums">{{ $r['pendientes'] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @php
            $chartDataComp = [
                'labels' => $chartComparativa['labels'],
                'datasets' => [
                    ['label' => 'Durante el año (%)', 'data' => $chartComparativa['durante'], 'backgroundColor' => '#198754', 'stack' => 's1'],
                    ['label' => 'Diciembre (%)', 'data' => $chartComparativa['diciembre'], 'backgroundColor' => '#0d6efd', 'stack' => 's1'],
                    ['label' => 'Febrero (%)', 'data' => $chartComparativa['febrero'], 'backgroundColor' => '#fd7e14', 'stack' => 's1'],
                    ['label' => 'Pendientes (%)', 'data' => $chartComparativa['pendientes'], 'backgroundColor' => '#6c757d', 'stack' => 's1'],
                ],
            ];
        @endphp
        <div class="se-card p-4 mb-6" wire:key="chart-doc-{{ $profesorId }}">
            <h3 class="text-sm font-semibold text-neutral-800 mb-3">Comparativa por docente (%)</h3>
            <div class="relative w-full se-estad-chart-panel" data-se-estad-chart-panel style="height: {{ max(280, count($porProfesor) * 40) }}px;">
                @include('livewire.estadistica.partials.chart-canvas', [
                    'canvasId' => 'chartEstadDocentes',
                    'chartType' => 'bar',
                    'chartData' => $chartDataComp,
                    'horizontal' => true,
                    'stacked' => true,
                ])
            </div>
        </div>

        @include('livewire.estadistica.partials.tabla-ordenable-script')

        @script
        <script>
            @include('livewire.estadistica.partials.estadistica-charts-init')
        </script>
        @endscript
    @endif
</div>
