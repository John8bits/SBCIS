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
        green: '#466557',
        greenFill: 'rgba(70, 101, 87, .14)',
        slate: '#687f89',
        text: '#35434a',
        muted: '#69777e',
        grid: 'rgba(78, 94, 86, .12)'
    };

    Chart.defaults.font.family = bodyStyle.fontFamily || 'Inter, Arial, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = colors.muted;
    Chart.defaults.animation = reducedMotion ? false : { duration: 300 };

    const count = (value, label) => `${value} ${label}`;
    const total = (values) => values.reduce((sum, value) => sum + Number(value || 0), 0);
    const pointerOnItem = (event, items) => {
        if (event.native?.target) {
            event.native.target.style.cursor = items.length ? 'pointer' : 'default';
        }
    };
    const openRecords = (path, search = '') => {
        window.location.href = path + (search ? `?search=${encodeURIComponent(search)}` : '');
    };
    const linearScale = () => ({
        beginAtZero: true,
        ticks: { precision: 0, color: colors.muted, padding: 8 },
        grid: { color: colors.grid, drawTicks: false },
        border: { display: false }
    });
    const common = {
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: Math.min(window.devicePixelRatio || 1, 2.5),
        resizeDelay: 100,
        normalized: true,
        interaction: { mode: 'nearest', intersect: true },
        onHover: pointerOnItem,
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
                backgroundColor: '#26352f',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                displayColors: true,
                padding: 12,
                cornerRadius: 7
            }
        }
    };

    const activityCanvas = document.getElementById('activityChart');
    let activityChart = null;
    if (activityCanvas) {
        activityChart = new Chart(activityCanvas, {
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
                        pointRadius: 4,
                        pointHoverRadius: 6,
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
                        pointRadius: 4,
                        pointHoverRadius: 6,
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
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: colors.muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 6, padding: 8 } },
                    y: linearScale()
                }
            }
        });

        document.querySelectorAll('[data-chart-months]').forEach((button) => {
            button.addEventListener('click', () => {
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
        new Chart(coverageCanvas, {
            type: 'bar',
            data: {
                labels: data.coverage.labels,
                datasets: [{ label: 'Boreholes', data: data.coverage.values, backgroundColor: colors.green, hoverBackgroundColor: '#355246', borderRadius: 5, barThickness: 18 }]
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
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: colors.text, padding: 8, font: { weight: 600 } } }
                }
            }
        });
    }

    const soilCanvas = document.getElementById('soilChart');
    if (soilCanvas) {
        const soilTotal = total(data.soils.values);
        new Chart(soilCanvas, {
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
                onClick: (event, items) => {
                    if (!items.length) return;
                    const label = data.soils.labels[items[0].index];
                    openRecords('soil_layers.php', label === 'Other soil types' ? '' : label);
                },
                plugins: {
                    ...common.plugins,
                    legend: {
                        position: 'bottom',
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
        new Chart(capacityCanvas, {
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
})();
