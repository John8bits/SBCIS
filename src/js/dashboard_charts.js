(() => {
    'use strict';

    const source = document.getElementById('dashboard-chart-data');
    if (!source) return;

    let data;
    try {
        data = JSON.parse(source.textContent);
    } catch (error) {
        return;
    }

    const canvases = document.querySelectorAll('.chart-canvas-wrap canvas');
    if (!window.Chart) {
        canvases.forEach((canvas) => {
            canvas.parentElement.innerHTML = '<div class="ov-empty">Charts could not be loaded. Check the internet connection and reload this page.</div>';
        });
        return;
    }

    const bodyStyle = getComputedStyle(document.body);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const colors = {
        green: '#176b4d',
        greenFill: 'rgba(23, 107, 77, .10)',
        slate: '#6f8479',
        text: '#263b32',
        muted: '#718078',
        grid: 'rgba(47, 78, 64, .09)'
    };

    Chart.defaults.font.family = bodyStyle.fontFamily || 'Inter, Arial, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = colors.muted;
    Chart.defaults.animation = reducedMotion ? false : {
        duration: 350,
        easing: 'easeOutQuart'
    };
    Chart.defaults.elements.arc.hoverOffset = 6;
    Chart.defaults.elements.point.hitRadius = 14;

    const count = (value, label) => `${value} ${label}`;
    const total = (values) => values.reduce((sum, value) => sum + Number(value || 0), 0);
    const charts = [];
    const createChart = (canvas, configuration) => {
        try {
            const chart = new Chart(canvas, configuration);
            charts.push(chart);
            return chart;
        } catch (error) {
            console.error('Dashboard chart:', error);
            canvas.parentElement.innerHTML = '<div class="ov-empty">This chart could not be displayed. Reload the page to try again.</div>';
            return null;
        }
    };
    const pointerOnItem = (event, items) => {
        if (event.native?.target) {
            const cursor = items.length ? 'pointer' : 'default';
            if (event.native.target.style.cursor !== cursor) {
                event.native.target.style.cursor = cursor;
            }
        }
    };
    const openRecords = (path, search = '') => {
        window.location.href = path + (search ? `?search=${encodeURIComponent(search)}` : '');
    };
    const linearScale = () => ({
        beginAtZero: true,
        ticks: { precision: 0, color: colors.muted, padding: 10, font: { size: 11 } },
        grid: { color: colors.grid, drawTicks: false, lineWidth: 1 },
        border: { display: false }
    });
    const common = {
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: Math.min(window.devicePixelRatio || 1, 2),
        resizeDelay: 120,
        normalized: true,
        interaction: { mode: 'nearest', axis: 'xy', intersect: true },
        hover: { mode: 'nearest', intersect: true },
        transitions: {
            active: {
                animation: { duration: reducedMotion ? 0 : 80 }
            }
        },
        onHover: pointerOnItem,
        layout: { padding: { top: 4, right: 8, bottom: 2, left: 4 } },
        plugins: {
            legend: {
                labels: {
                    color: colors.text,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 8,
                    padding: 18,
                    font: { weight: 600 }
                }
            },
            tooltip: {
                backgroundColor: '#ffffff',
                titleColor: '#173d2f',
                bodyColor: '#52635a',
                borderColor: 'rgba(31, 67, 51, .08)',
                borderWidth: 1,
                displayColors: true,
                padding: 13,
                cornerRadius: 10,
                caretPadding: 9,
                caretSize: 6,
                usePointStyle: true,
                boxPadding: 5,
                titleFont: { size: 12, weight: 700 },
                bodyFont: { size: 12, weight: 500 }
            }
        }
    };

    const activityCanvas = document.getElementById('activityChart');
    let activityChart = null;
    if (activityCanvas) {
        activityChart = createChart(activityCanvas, {
            type: 'line',
            data: {
                labels: [...data.activity.labels],
                datasets: [
                    {
                        label: 'Boreholes',
                        data: [...data.activity.boreholes],
                        borderColor: colors.green,
                        backgroundColor: colors.greenFill,
                        borderWidth: 2.5,
                        pointRadius: 3.5,
                        pointHoverRadius: 6.5,
                        pointHitRadius: 14,
                        pointBackgroundColor: '#ffffff',
                        pointBorderWidth: 2,
                        tension: .28,
                        fill: true
                    },
                    {
                        label: 'Soil layers',
                        data: [...data.activity.layers],
                        borderColor: colors.slate,
                        backgroundColor: '#ffffff',
                        borderWidth: 2.5,
                        pointRadius: 3.5,
                        pointHoverRadius: 6.5,
                        pointHitRadius: 14,
                        pointBackgroundColor: '#ffffff',
                        pointBorderWidth: 2,
                        tension: .28
                    }
                ]
            },
            options: {
                ...common,
                interaction: { mode: 'index', intersect: false },
                onClick: (event, items) => {
                    if (!items.length) return;
                    openRecords(items[0].datasetIndex === 0 ? 'boreholes.php' : 'soil_layers.php');
                },
                plugins: {
                    ...common.plugins,
                    tooltip: {
                        ...common.plugins.tooltip,
                        callbacks: { label: (context) => count(context.parsed.y, context.dataset.label.toLowerCase()) }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: colors.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 6, padding: 10, font: { size: 11 } } },
                    y: linearScale()
                }
            }
        });

        document.querySelectorAll('[data-chart-months]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!activityChart) return;
                const months = Number(button.dataset.chartMonths);
                const start = Math.max(0, data.activity.labels.length - months);
                activityChart.data.labels = data.activity.labels.slice(start);
                activityChart.data.datasets[0].data = data.activity.boreholes.slice(start);
                activityChart.data.datasets[1].data = data.activity.layers.slice(start);
                activityChart.update();
                document.querySelectorAll('[data-chart-months]').forEach((item) => {
                    const selected = item === button;
                    item.classList.toggle('active', selected);
                    item.setAttribute('aria-pressed', String(selected));
                });
            });
        });
    }

    const coverageCanvas = document.getElementById('coverageChart');
    if (coverageCanvas) {
        createChart(coverageCanvas, {
            type: 'bar',
            data: {
                labels: data.coverage.labels,
                datasets: [{ label: 'Boreholes', data: data.coverage.values, backgroundColor: colors.green, hoverBackgroundColor: '#0f573e', borderRadius: 7, borderSkipped: false, barPercentage: .72, categoryPercentage: .78, maxBarThickness: 24 }]
            },
            options: {
                ...common,
                indexAxis: 'y',
                onClick: (event, items) => {
                    if (items.length) openRecords('boreholes.php', data.coverage.labels[items[0].index]);
                },
                plugins: {
                    ...common.plugins,
                    legend: { display: false },
                    tooltip: { ...common.plugins.tooltip, callbacks: { label: (context) => count(context.parsed.x, 'boreholes') } }
                },
                scales: {
                    x: linearScale(),
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: colors.text, padding: 12, autoSkip: false, font: { size: 11, weight: 600 } } }
                }
            }
        });
    }

    const soilCanvas = document.getElementById('soilChart');
    if (soilCanvas) {
        const soilTotal = total(data.soils.values);
        createChart(soilCanvas, {
            type: 'doughnut',
            data: {
                labels: data.soils.labels,
                datasets: [{
                    data: data.soils.values,
                    backgroundColor: ['#466557', '#687f89', '#94866d', '#7e9387', '#a4ada8', '#c0c6c2'],
                    hoverBackgroundColor: ['#355246', '#566f79', '#81735b', '#6c8175', '#929c96', '#aeb5b1'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 7
                }]
            },
            options: {
                ...common,
                cutout: '62%',
                onResize: (chart, size) => {
                    chart.options.plugins.legend.position = size.width < 520 ? 'bottom' : 'right';
                },
                onClick: (event, items) => {
                    if (!items.length) return;
                    const label = data.soils.labels[items[0].index];
                    openRecords('soil_layers.php', label === 'Other soil types' ? '' : label);
                },
                plugins: {
                    ...common.plugins,
                    legend: {
                        position: soilCanvas.parentElement.clientWidth < 520 ? 'bottom' : 'right',
                        labels: {
                            color: colors.text,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            padding: 15,
                            font: { weight: 600 }
                        }
                    },
                    tooltip: {
                        ...common.plugins.tooltip,
                        callbacks: {
                            label: (context) => {
                                const value = Number(context.raw || 0);
                                const percent = soilTotal ? Math.round((value / soilTotal) * 100) : 0;
                                return ` ${context.label}: ${value} layers (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    const capacityCanvas = document.getElementById('capacityChart');
    if (capacityCanvas) {
        createChart(capacityCanvas, {
            type: 'bar',
            data: {
                labels: data.capacities.labels,
                datasets: [{
                    label: 'Soil layers',
                    data: data.capacities.values,
                    backgroundColor: colors.slate,
                    hoverBackgroundColor: '#526b75',
                    borderRadius: 5,
                    maxBarThickness: 48
                }]
            },
            options: {
                ...common,
                onClick: (event, items) => {
                    if (items.length) openRecords('bearing_capacity.php');
                },
                plugins: {
                    ...common.plugins,
                    legend: { display: false },
                    tooltip: { ...common.plugins.tooltip, callbacks: { label: (context) => count(context.parsed.y, 'soil layers') } }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: colors.text, maxRotation: 20, minRotation: 0, padding: 8, font: { weight: 600 } } },
                    y: linearScale()
                }
            }
        });
    }

    // The sidebar changes the chart grid width using a CSS transition. Resize
    // once that transition ends so every canvas remains sharp and correctly sized.
    const resizeCharts = () => window.requestAnimationFrame(() => {
        charts.forEach((chart) => chart.resize());
    });

    document.querySelector('.sidebar')?.addEventListener('transitionend', (event) => {
        if (event.propertyName === 'width' || event.propertyName === 'transform') {
            resizeCharts();
        }
    });

    window.addEventListener('beforeprint', () => charts.forEach((chart) => chart.resize(720, 360)));
    window.addEventListener('afterprint', resizeCharts);
})();
