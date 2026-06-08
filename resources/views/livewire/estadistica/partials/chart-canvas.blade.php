@props([
    'canvasId',
    'chartType' => 'bar',
    'chartData' => [],
    'horizontal' => false,
    'stacked' => false,
    'datalabels' => true,
])

<canvas
    id="{{ $canvasId }}"
    class="se-estad-chart-canvas w-full h-full"
    data-estad-chart="1"
    data-chart-type="{{ $chartType }}"
    data-chart-horizontal="{{ $horizontal ? '1' : '0' }}"
    data-chart-stacked="{{ $stacked ? '1' : '0' }}"
    data-chart-datalabels="{{ $datalabels ? '1' : '0' }}"
    data-chart-config='@json($chartData)'
></canvas>
