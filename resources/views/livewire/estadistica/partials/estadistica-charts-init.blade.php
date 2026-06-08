(function () {
    var bootTimer = null;
    var booting = false;

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            if (document.querySelector('script[src="' + src + '"]')) {
                resolve();
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function parseConfig(canvas) {
        var raw = canvas.getAttribute('data-chart-config');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function chartOptions(chartType, horizontal, stacked, useDatalabels) {
        var options = {
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
                    stacked: stacked,
                    min: 0,
                    max: 100,
                    ticks: { callback: function (v) { return v + '%'; } },
                },
                y: { stacked: stacked },
            };
            if (useDatalabels && typeof ChartDataLabels !== 'undefined') {
                options.plugins.datalabels = {
                    anchor: 'center',
                    align: 'center',
                    color: '#fff',
                    font: { weight: 'bold', size: 11 },
                    formatter: function (value) {
                        return value != null && value !== 0 ? value + '%' : '';
                    },
                };
            }
        }

        if (chartType === 'doughnut' && useDatalabels && typeof ChartDataLabels !== 'undefined') {
            options.plugins.datalabels = {
                color: '#fff',
                font: { weight: 'bold', size: 14 },
                formatter: function (value) {
                    return value != null ? value + '%' : '';
                },
            };
        }

        return options;
    }

    function renderCanvas(canvas) {
        if (!canvas || canvas.getAttribute('data-estad-chart') !== '1') return false;

        var chartData = parseConfig(canvas);
        if (!chartData || !chartData.labels || chartData.labels.length === 0) return false;
        if (typeof Chart === 'undefined') return false;

        var chartType = canvas.getAttribute('data-chart-type') || 'bar';
        var horizontal = canvas.getAttribute('data-chart-horizontal') === '1';
        var stacked = canvas.getAttribute('data-chart-stacked') === '1';
        var useDatalabels = canvas.getAttribute('data-chart-datalabels') !== '0';

        var existing = Chart.getChart(canvas);
        if (existing) existing.destroy();

        new Chart(canvas.getContext('2d'), {
            type: chartType,
            data: chartData,
            options: chartOptions(chartType, horizontal, stacked, useDatalabels),
        });

        return true;
    }

    function hidePanels() {
        document.querySelectorAll('[data-se-estad-chart-panel]').forEach(function (panel) {
            panel.classList.remove('se-estad-chart-panel--ready');
        });
    }

    function showPanels() {
        document.querySelectorAll('[data-se-estad-chart-panel]').forEach(function (panel) {
            panel.classList.add('se-estad-chart-panel--ready');
        });
    }

    function renderAll() {
        var canvases = document.querySelectorAll('canvas.se-estad-chart-canvas[data-estad-chart="1"]');
        if (canvases.length === 0) {
            return;
        }

        hidePanels();
        canvases.forEach(renderCanvas);

        requestAnimationFrame(function () {
            requestAnimationFrame(showPanels);
        });
    }

    async function boot() {
        if (booting) return;
        booting = true;

        try {
            var canvases = document.querySelectorAll('canvas.se-estad-chart-canvas[data-estad-chart="1"]');
            if (canvases.length === 0) {
                return;
            }

            await loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js');
            await loadScript('https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0');
            if (typeof ChartDataLabels !== 'undefined' && !window.__seEstadChartDatalabels) {
                Chart.register(ChartDataLabels);
                window.__seEstadChartDatalabels = true;
            }
            renderAll();
        } catch (e) {
            showPanels();
        } finally {
            booting = false;
        }
    }

    function scheduleBoot() {
        clearTimeout(bootTimer);
        bootTimer = setTimeout(boot, 100);
    }

    boot();

    Livewire.hook('morph.updated', function () {
        if (document.querySelector('canvas.se-estad-chart-canvas[data-estad-chart="1"]')) {
            scheduleBoot();
        }
    });
})();
