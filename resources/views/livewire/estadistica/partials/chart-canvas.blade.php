@props([
    'canvasId',
    'chartType' => 'bar',
    'chartData' => [],
    'horizontal' => false,
    'stacked' => false,
    'datalabels' => true,
])

<div class="relative h-full w-full min-h-[12rem]" data-se-estad-chart-root>
    <textarea hidden readonly data-se-estad-chart-json>@json($chartData)</textarea>
    <canvas
        id="{{ $canvasId }}"
        class="se-estad-chart-canvas absolute inset-0 h-full w-full"
        data-estad-chart="1"
        data-chart-type="{{ $chartType }}"
        data-chart-horizontal="{{ $horizontal ? '1' : '0' }}"
        data-chart-stacked="{{ $stacked ? '1' : '0' }}"
        data-chart-datalabels="{{ $datalabels ? '1' : '0' }}"
    ></canvas>
</div>
