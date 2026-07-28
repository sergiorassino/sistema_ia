import Chart from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels';

Chart.register(ChartDataLabels);

const SELECTOR = 'canvas.se-estad-chart-canvas[data-estad-chart="1"]';

let bootTimer = null;
let booting = false;
let bootQueued = false;
let observerStarted = false;

function parseConfig(canvas) {
    const root = canvas.closest('[data-se-estad-chart-root]');
    const jsonEl = root?.querySelector('[data-se-estad-chart-json]');
    if (jsonEl) {
        const raw = typeof jsonEl.value === 'string' ? jsonEl.value : (jsonEl.textContent || '');
        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    }

    const rawAttr = canvas.getAttribute('data-chart-config');
    if (!rawAttr) {
        return null;
    }
    try {
        return JSON.parse(rawAttr);
    } catch {
        return null;
    }
}

function chartOptions(chartType, horizontal, stacked, useDatalabels) {
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        animations: {
            colors: false,
            x: { duration: 0 },
            y: { duration: 0 },
        },
        transitions: {
            active: { animation: { duration: 0 } },
            resize: { animation: { duration: 0 } },
        },
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { enabled: false },
        },
    };

    if (chartType === 'bar' && horizontal) {
        options.indexAxis = 'y';
        options.scales = {
            x: {
                stacked,
                min: 0,
                max: 100,
                ticks: { callback: (v) => `${v}%` },
            },
            y: { stacked },
        };
        if (useDatalabels) {
            options.plugins.datalabels = {
                anchor: 'center',
                align: 'center',
                color: '#fff',
                font: { weight: 'bold', size: 11 },
                formatter: (value) => (value != null && value !== 0 ? `${value}%` : ''),
            };
        }
    }

    if (chartType === 'doughnut' && useDatalabels) {
        options.plugins.datalabels = {
            color: '#fff',
            font: { weight: 'bold', size: 14 },
            formatter: (value) => (value != null ? `${value}%` : ''),
        };
    }

    return options;
}

function renderCanvas(canvas) {
    if (!canvas || canvas.getAttribute('data-estad-chart') !== '1') {
        return false;
    }

    const chartData = parseConfig(canvas);
    if (!chartData?.labels?.length) {
        return false;
    }

    const chartType = canvas.getAttribute('data-chart-type') || 'bar';
    const horizontal = canvas.getAttribute('data-chart-horizontal') === '1';
    const stacked = canvas.getAttribute('data-chart-stacked') === '1';
    const useDatalabels = canvas.getAttribute('data-chart-datalabels') !== '0';

    const existing = Chart.getChart(canvas);
    if (existing) {
        existing.destroy();
    }

    // eslint-disable-next-line no-new
    new Chart(canvas.getContext('2d'), {
        type: chartType,
        data: chartData,
        options: chartOptions(chartType, horizontal, stacked, useDatalabels),
    });

    return true;
}

function showPanels() {
    document.querySelectorAll('[data-se-estad-chart-panel]').forEach((panel) => {
        panel.classList.add('se-estad-chart-panel--ready');
    });
}

function resizeCharts() {
    document.querySelectorAll(SELECTOR).forEach((canvas) => {
        const chart = Chart.getChart(canvas);
        if (chart) {
            chart.resize();
        }
    });
}

function renderAll() {
    const canvases = document.querySelectorAll(SELECTOR);
    if (canvases.length === 0) {
        return;
    }

    showPanels();
    canvases.forEach(renderCanvas);
    requestAnimationFrame(() => {
        resizeCharts();
        requestAnimationFrame(resizeCharts);
    });
}

function boot() {
    if (booting) {
        bootQueued = true;
        return;
    }
    booting = true;
    bootQueued = false;

    try {
        renderAll();
    } finally {
        booting = false;
        if (bootQueued) {
            scheduleBoot();
        }
    }
}

function scheduleBoot() {
    clearTimeout(bootTimer);
    bootTimer = setTimeout(boot, 80);
}

function startObserver() {
    if (observerStarted || typeof MutationObserver === 'undefined') {
        return;
    }
    observerStarted = true;

    const observer = new MutationObserver(() => scheduleBoot());
    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
    });
}

function bindLivewire() {
    const L = window.Livewire;
    if (!L || typeof L.hook !== 'function') {
        return;
    }

    L.hook('morph.updated', () => scheduleBoot());
    L.hook('commit', ({ succeed }) => {
        succeed(() => scheduleBoot());
    });
}

export function initEstadisticaCharts() {
    window.__seEstadChartsBoot = boot;
    startObserver();
    scheduleBoot();

    document.addEventListener('livewire:initialized', () => {
        bindLivewire();
        scheduleBoot();
    });
    document.addEventListener('livewire:navigated', scheduleBoot);

    if (window.Livewire) {
        bindLivewire();
    }
}

initEstadisticaCharts();
