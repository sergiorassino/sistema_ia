@php
    use App\Support\Cuotas\CuotasFormato;
    use App\Support\Cuotas\CuotasPlantillaCatalog;
    use App\Support\Cuotas\EstadisticaPagoCuotasDatos;
@endphp

<div class="se-page max-w-6xl mx-auto">
    <section class="se-hero mb-4">
        <div class="se-hero-inner flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-0.5">
                <p class="se-eyebrow">Resúmenes</p>
                <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">Estadística de pago de cuotas</h1>
                <p class="text-xs text-white/75">
                    Ciclo lectivo {{ $ano }} · Porcentaje de cuotas pagadas y no pagadas por plantilla
                </p>
            </div>
        </div>
    </section>

    @if ($idTerlec <= 0)
        <div class="se-card p-6 text-center text-sm text-neutral-500">
            Seleccioná un año lectivo en el contexto de sesión.
        </div>
    @elseif ($plantillas->isEmpty())
        <div class="se-card p-6 text-center text-sm text-neutral-500">
            No hay plantillas de cuota en el ciclo lectivo activo.
        </div>
    @else
        <div class="se-card overflow-hidden mb-6">
            <div class="border-b border-accent-200 bg-accent-50/80 px-4 py-3 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-neutral-700">
                        Marcá las cuotas a incluir y pulsá Graficar. Solo se cuentan cuotas generadas con importe mayor a cero.
                    </p>
                    <span class="se-pill shrink-0 tabular-nums">
                        {{ $cantidadSeleccionadas }} de {{ $plantillas->count() }} seleccionadas
                    </span>
                </div>
            </div>

            <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5">
                <div>
                    <label for="nivel-estad-pago" class="form-label">Nivel</label>
                    <select id="nivel-estad-pago"
                            wire:model.live="idNivel"
                            class="form-input mt-1">
                        <option value="0">Todos</option>
                        @foreach ($niveles as $nivel)
                            <option value="{{ (int) $nivel['id'] }}">{{ $nivel['nombre'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="px-4 pb-4 sm:px-5">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-[12rem] flex-1">
                        <label for="filtro-cuotas-estad" class="form-label">Buscar cuota</label>
                        <input id="filtro-cuotas-estad"
                               type="search"
                               wire:model.live.debounce.300ms="filtroCuotas"
                               placeholder="Nombre, mes o tipo"
                               class="form-input mt-1"
                               autocomplete="off">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                wire:click="seleccionarTodasCuotas"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-800 hover:bg-accent-50">
                            Todas
                        </button>
                        <button type="button"
                                wire:click="quitarTodasCuotas"
                                class="inline-flex rounded-lg border border-accent-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-accent-50">
                            Ninguna
                        </button>
                    </div>
                </div>

                <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto rounded-xl border border-accent-200 bg-accent-50/30 p-3">
                    @foreach ($plantillas as $cuota)
                        @php
                            $etiqueta = CuotasPlantillaCatalog::etiquetaCuota($cuota);
                            $oculta = $filtroCuotas !== ''
                                && ! str_contains(mb_strtolower($etiqueta), mb_strtolower(trim($filtroCuotas)));
                        @endphp
                        <li wire:key="cuota-estad-{{ $cuota->id }}" @class(['hidden' => $oculta])>
                            <label class="flex cursor-pointer items-center gap-2 py-1">
                                <input type="checkbox"
                                       wire:model.live="cuotasSeleccionadas"
                                       value="{{ $cuota->id }}"
                                       class="h-4 w-4 rounded border-accent-300 text-primary-600 focus:ring-primary-500" />
                                <span class="text-sm text-neutral-800">{{ $etiqueta }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                @error('cuotasSeleccionadas')
                    <p class="form-error mt-2">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-accent-200 bg-accent-50/60 px-4 py-3 sm:px-5">
                <button type="button" wire:click="limpiar" class="btn-secondary">Limpiar</button>
                <button type="button"
                        wire:click="graficar"
                        wire:loading.attr="disabled"
                        class="btn-primary">
                    <span wire:loading.remove wire:target="graficar">Graficar</span>
                    <span wire:loading wire:target="graficar">Calculando…</span>
                </button>
            </div>
        </div>

        @if (! $mostrarResultados)
            <div class="se-card p-6 text-center text-sm text-neutral-500">
                Elegí una o más cuotas y pulsá Graficar para ver los porcentajes de pago.
            </div>
        @elseif ($filas === [])
            <div class="se-card p-6 text-center text-sm text-neutral-500">
                No hay cuotas generadas con importe para la selección.
            </div>
        @else
            <div class="se-card min-w-0 overflow-hidden mb-6">
                <h3 class="px-4 py-3 text-sm font-semibold text-neutral-800 border-b border-accent-200">Resumen por cuota</h3>
                <div class="w-full overflow-x-auto">
                    <div class="flex justify-start">
                        <table class="min-w-full text-sm" data-se-tabla-ordenable>
                            <thead class="bg-accent-50">
                                <tr>
                                    <th class="table-header">Cuota</th>
                                    <th class="table-header text-right num" data-sort-num="1">Generadas</th>
                                    <th class="table-header text-right num" data-sort-num="1">Pagadas</th>
                                    <th class="table-header text-right num" data-sort-num="1">No pagadas</th>
                                    <th class="table-header text-right num" data-sort-num="1">% pago</th>
                                    <th class="table-header text-right num" data-sort-num="1">Importe</th>
                                    <th class="table-header text-right num" data-sort-num="1">Cobrado</th>
                                    <th class="table-header text-right num" data-sort-num="1">% cobrado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-accent-200 bg-white">
                                @foreach ($filas as $fila)
                                    <tr class="hover:bg-accent-50/60">
                                        <td class="table-cell">{{ $fila['etiqueta'] }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ $fila['total'] }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ $fila['pagadas'] }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ $fila['no_pagadas'] }}</td>
                                        <td class="table-cell text-right tabular-nums font-semibold text-primary-800">{{ $fila['pct_pagadas'] }}%</td>
                                        <td class="table-cell text-right tabular-nums">{{ CuotasFormato::formatearImporte($fila['importe']) }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ CuotasFormato::formatearImporte($fila['pagado']) }}</td>
                                        <td class="table-cell text-right tabular-nums">{{ $fila['pct_cobrado'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="se-card p-4 mb-6" wire:key="chart-barras-pago-{{ implode('-', array_column($filas, 'id')) }}-{{ $idNivel }}">
                <h3 class="text-sm font-semibold text-neutral-800 mb-3">Porcentaje de pago por cuota</h3>
                <div class="relative w-full se-estad-chart-panel" data-se-estad-chart-panel style="height: {{ max(280, count($filas) * 40) }}px;">
                    @include('livewire.estadistica.partials.chart-canvas', [
                        'canvasId' => 'chartEstadPagoBarras',
                        'chartType' => 'bar',
                        'chartData' => $chartBarras,
                        'horizontal' => true,
                        'stacked' => true,
                    ])
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($filas as $fila)
                    <div class="se-card p-4" wire:key="chart-dona-pago-{{ $fila['id'] }}-{{ $idNivel }}">
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <h3 class="text-sm font-semibold text-neutral-800 leading-snug">{{ $fila['etiqueta'] }}</h3>
                            <span class="se-pill shrink-0 tabular-nums">{{ $fila['pct_pagadas'] }}%</span>
                        </div>
                        @if ($fila['total'] <= 0)
                            <p class="py-10 text-center text-sm text-neutral-500">Sin cuotas generadas con importe.</p>
                        @else
                            <p class="mb-2 text-xs text-neutral-500">
                                {{ $fila['pagadas'] }} pagadas · {{ $fila['no_pagadas'] }} no pagadas · {{ $fila['total'] }} generadas
                            </p>
                            <div class="relative w-full h-56 se-estad-chart-panel" data-se-estad-chart-panel>
                                @include('livewire.estadistica.partials.chart-canvas', [
                                    'canvasId' => 'chartEstadPagoDona'.$fila['id'],
                                    'chartType' => 'doughnut',
                                    'chartData' => EstadisticaPagoCuotasDatos::chartDona($fila),
                                ])
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    @include('livewire.estadistica.partials.tabla-ordenable-script')
</div>

@script
<script>
    $wire.on('se-swal-error', ({ mensaje }) => {
        if (typeof window.seSwalError === 'function') {
            window.seSwalError(mensaje);
        }
    });
</script>
@endscript
