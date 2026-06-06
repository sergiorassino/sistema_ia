<div class="se-page !max-w-none min-w-0">
    <section class="se-hero">
        <div class="se-hero-inner flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="se-eyebrow">Docentes</p>
                <h2 class="text-2xl font-bold tracking-tight">Ranking por materia y curso</h2>
                <p class="text-sm text-white/80">Inasistencias con detalle materia/curso · {{ $anio }}</p>
            </div>
            <a href="{{ route('docentes.inasistencias') }}" class="btn-secondary !border-white/30 !bg-white/10 !text-white shrink-0">← Listado</a>
        </div>
    </section>

    <div class="se-toolbar flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        @if ($tieneDetalle && count($filas) > 0)
            <a href="{{ route('docentes.inasistencias.ranking.csv', ['anio' => $anio, 'periodo' => $periodo, 'sort' => $sort, 'dir' => $dir]) }}"
               class="btn-secondary shrink-0">Exportar CSV</a>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <label for="anio-rank" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Año</label>
            <select wire:model.live="anio" id="anio-rank" class="form-input w-auto min-w-[5rem]">
                @foreach ($anios as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>

            <label for="periodo-rank" class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">Ver</label>
            <select wire:model.live="periodo" id="periodo-rank" class="form-input w-auto min-w-[10rem]">
                <option value="0">Todo el año</option>
                @foreach ($bimestres as $num => $b)
                    <option value="{{ $num }}">Bimestre {{ $num }} — {{ $b['label'] }} ({{ $b['titulo'] }})</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (! $tieneDetalle)
        <div class="se-soft-card border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            No existe la tabla de detalle (<code>inasdocentes_detalle</code>). No hay datos para el ranking.
        </div>
    @elseif (count($filas) === 0)
        <div class="se-card p-6 text-center text-neutral-500 text-sm">
            No hay registros de inasistencias con detalle de materia/curso para el año {{ $anio }}.
        </div>
    @else
        <div class="se-card min-w-0 overflow-hidden mb-6">
            <h3 class="px-4 py-3 text-sm font-semibold text-neutral-800 border-b border-accent-200">Listado</h3>
            <table class="min-w-full text-sm">
                <thead class="bg-accent-50">
                    <tr>
                        <th class="table-header">
                            <button type="button" wire:click="ordenar('curso')" class="hover:text-primary-700">
                                Curso @if($sort === 'curso') {{ $dir === 'ASC' ? '↑' : '↓' }} @endif
                            </button>
                        </th>
                        <th class="table-header">
                            <button type="button" wire:click="ordenar('materia')" class="hover:text-primary-700">
                                Materia @if($sort === 'materia') {{ $dir === 'ASC' ? '↑' : '↓' }} @endif
                            </button>
                        </th>
                        <th class="table-header text-right">
                            <button type="button" wire:click="ordenar('total')" class="hover:text-primary-700">
                                Total inasist. @if($sort === 'total') {{ $dir === 'ASC' ? '↑' : '↓' }} @endif
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-accent-200 bg-white">
                    @foreach ($filas as $r)
                        <tr class="hover:bg-accent-50/60">
                            <td class="table-cell">{{ $r['curso'] ?: '—' }}</td>
                            <td class="table-cell">{{ $r['materia'] ?: '—' }}</td>
                            <td class="table-cell text-right tabular-nums font-medium">{{ number_format($r['total'], 1, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (count($chart['labels'] ?? []) > 0)
            <div class="se-card p-4">
                <h3 class="text-sm font-semibold text-neutral-800 mb-1">
                    Inasistencias por curso — {{ $anio }}
                    @if ($periodoLabel)
                        · {{ $periodoLabel }}
                    @endif
                </h3>
                <div class="h-80 max-w-4xl" wire:key="rank-chart-{{ $anio }}-{{ $periodo }}-{{ $sort }}-{{ $dir }}">
                    <canvas id="chartCursosInasDoc" data-chart='@json($chart)'></canvas>
                </div>
            </div>

            @script
            <script>
                (function () {
                    function loadChartJs(cb) {
                        if (typeof Chart !== 'undefined') {
                            cb();
                            return;
                        }
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                        s.onload = cb;
                        document.head.appendChild(s);
                    }

                    function renderChart() {
                        const canvas = document.getElementById('chartCursosInasDoc');
                        if (!canvas || !canvas.dataset.chart) return;
                        let chartData;
                        try {
                            chartData = JSON.parse(canvas.dataset.chart);
                        } catch (e) {
                            return;
                        }
                        if (!chartData.labels || chartData.labels.length === 0) return;
                        if (typeof Chart === 'undefined') return;

                        const existing = Chart.getChart(canvas);
                        if (existing) existing.destroy();

                        const dataRaw = chartData.data.map(v => typeof v === 'number' ? v : parseFloat(v) || 0);
                        const dataDisplay = dataRaw.map(v => v === 0 ? 0.1 : v);

                        new Chart(canvas, {
                            type: 'bar',
                            data: {
                                labels: chartData.labels,
                                datasets: [{
                                    label: 'Inasistencias',
                                    data: dataDisplay,
                                    backgroundColor: 'rgba(64, 132, 141, 0.85)',
                                    borderColor: 'rgba(64, 132, 141, 1)',
                                    borderWidth: 1,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: {
                                        title: { display: true, text: 'Curso' },
                                        ticks: { maxRotation: 90, minRotation: 45 },
                                    },
                                    y: { beginAtZero: true, title: { display: true, text: 'Cantidad' } },
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label(ctx) {
                                                return 'Inasistencias: ' + dataRaw[ctx.dataIndex];
                                            },
                                        },
                                    },
                                },
                            },
                        });
                    }

                    loadChartJs(renderChart);
                    Livewire.hook('morph.updated', () => setTimeout(renderChart, 80));
                })();
            </script>
            @endscript
        @endif
    @endif
</div>
