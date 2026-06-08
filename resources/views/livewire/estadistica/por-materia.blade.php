<div class="se-page !max-w-none min-w-0">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-eyebrow">Estadísticas</p>
                <h2 class="text-2xl font-bold tracking-tight">Estadística por materias</h2>
                <p class="text-sm text-white/80">Nivel de aprobación — durante el año, Diciembre y Febrero</p>
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
                <label for="materia-curso" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Por materia y curso</label>
                <select wire:model.live="materiaCurso" id="materia-curso" class="form-input mt-1 min-w-[14rem] max-w-full">
                    <option value="0">— Todas —</option>
                    @foreach ($materiasCursos as $mc)
                        <option value="{{ $mc['idMaterias'] }}-{{ $mc['idCursos'] }}">{{ $mc['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="curso-filtro" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Por curso</label>
                <select wire:model.live="cursoId" id="curso-filtro" class="form-input mt-1 min-w-[10rem]">
                    <option value="0">— Todos —</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c['id'] }}">{{ $c['cursec'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Por nivel</label>
                <div class="form-input mt-1 bg-accent-100 text-neutral-600 cursor-not-allowed w-auto min-w-[8rem]">Nivel Medio</div>
            </div>
            <button type="button" wire:click="limpiarFiltros" class="btn-secondary">Limpiar</button>
        </div>
    </div>

    @if ($idTerlec <= 0)
        <div class="se-card p-6 text-center text-neutral-500 text-sm">Seleccioná un año lectivo en el contexto de sesión.</div>
    @elseif ($resumen !== null)
        @include('livewire.estadistica.partials.resumen-cards', ['resumen' => $resumen, 'anoLabel' => $anoLabel])

        @if ($resumen['total'] > 0)
            <p class="text-sm text-neutral-500 mb-4">
                Durante el año: {{ $pctResumen[0] }}% —
                Diciembre: {{ $pctResumen[1] }}% —
                Febrero: {{ $pctResumen[2] }}% —
                Pendientes: {{ $pctResumen[3] }}%
            </p>
        @endif

        @if (! empty($porMateriaCurso))
            <div class="se-card min-w-0 overflow-hidden mb-6">
                <h3 class="px-4 py-3 text-sm font-semibold text-neutral-800 border-b border-accent-200">Por materia y curso</h3>
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
                            @foreach ($porMateriaCurso as $r)
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

            @php
                $chartDataBarras = [
                    'labels' => $chartBarras['labels'],
                    'datasets' => [
                        ['label' => 'Durante el año (%)', 'data' => $chartBarras['durante'], 'backgroundColor' => '#198754', 'stack' => 's1'],
                        ['label' => 'Diciembre (%)', 'data' => $chartBarras['diciembre'], 'backgroundColor' => '#0d6efd', 'stack' => 's1'],
                        ['label' => 'Febrero (%)', 'data' => $chartBarras['febrero'], 'backgroundColor' => '#fd7e14', 'stack' => 's1'],
                        ['label' => 'Pendientes (%)', 'data' => $chartBarras['pendientes'], 'backgroundColor' => '#6c757d', 'stack' => 's1'],
                    ],
                ];
            @endphp
            <div class="se-card p-4 mb-6" wire:key="chart-mc-{{ $materiaCurso }}-{{ $cursoId }}">
                <h3 class="text-sm font-semibold text-neutral-800 mb-3">Gráfico por materia y curso (%)</h3>
                <div class="relative w-full se-estad-chart-panel" data-se-estad-chart-panel style="height: {{ max(280, count($porMateriaCurso) * 36) }}px;">
                    @include('livewire.estadistica.partials.chart-canvas', [
                        'canvasId' => 'chartEstadMateriaCurso',
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
                    'labels' => ['Durante el año', 'Diciembre', 'Febrero', 'Pendientes'],
                    'datasets' => [[
                        'data' => $pctResumen,
                        'backgroundColor' => ['#198754', '#0d6efd', '#fd7e14', '#6c757d'],
                        'borderWidth' => 1,
                    ]],
                ];
            @endphp
            <div class="se-card p-4 max-w-md" wire:key="chart-torta-mc-{{ $materiaCurso }}-{{ $cursoId }}">
                <h3 class="text-sm font-semibold text-neutral-800 mb-3">Distribución de aprobación (%)</h3>
                <div class="relative w-full h-72 se-estad-chart-panel" data-se-estad-chart-panel>
                    @include('livewire.estadistica.partials.chart-canvas', [
                        'canvasId' => 'chartEstadTortaMateria',
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
