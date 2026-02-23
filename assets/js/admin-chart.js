(function () {
    var chartRefs = {
        trend: null,
        split: null
    };

    function parseJSON(value) {
        try {
            return value ? JSON.parse(value) : [];
        } catch (e) {
            return [];
        }
    }

    function toNumberArray(values) {
        if (!Array.isArray(values)) {
            return [];
        }
        return values.map(function (value) {
            var parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : 0;
        });
    }

    function destroyChart(key) {
        if (!chartRefs[key]) {
            return;
        }
        chartRefs[key].destroy();
        chartRefs[key] = null;
    }

    function destroyCanvasChart(canvas) {
        if (!canvas || typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return;
        }

        var existing = Chart.getChart(canvas);
        if (existing) {
            existing.destroy();
        }
    }

    function registerCenterTextPlugin() {
        if (typeof Chart === 'undefined' || window.__adminCenterTextPluginReady) {
            return;
        }

        Chart.register({
            id: 'adminCenterTextPlugin',
            afterDraw: function (chart) {
                if (chart.config.type !== 'doughnut') {
                    return;
                }

                var options = chart.options.plugins.adminCenterTextPlugin || {};
                if (!options.text) {
                    return;
                }

                var meta = chart.getDatasetMeta(0);
                if (!meta || !meta.data || !meta.data.length) {
                    return;
                }

                var center = meta.data[0];
                var ctx = chart.ctx;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#0f172a';
                ctx.font = '700 19px Roboto, sans-serif';
                ctx.fillText(String(options.text), center.x, center.y - 7);
                ctx.fillStyle = '#64748b';
                ctx.font = '500 11px Roboto, sans-serif';
                ctx.fillText(options.subText || '', center.x, center.y + 12);
                ctx.restore();
            }
        });

        window.__adminCenterTextPluginReady = true;
    }

    function buildTrendChart() {
        var canvas = document.getElementById('adminDashboardChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        destroyCanvasChart(canvas);
        destroyChart('trend');
        canvas.style.height = '290px';
        canvas.style.maxHeight = '290px';

        var labels = parseJSON(canvas.dataset.labels);
        var applications = toNumberArray(parseJSON(canvas.dataset.applications));

        var context = canvas.getContext('2d');
        var appGradient = context.createLinearGradient(0, 0, 0, 300);
        appGradient.addColorStop(0, 'rgba(59,130,246,0.45)');
        appGradient.addColorStop(1, 'rgba(59,130,246,0.06)');

        chartRefs.trend = new Chart(context, {
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Applications',
                        data: applications,
                        backgroundColor: appGradient,
                        borderColor: 'rgba(59,130,246,1)',
                        borderWidth: 1,
                        borderRadius: 8,
                        maxBarThickness: 34
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            color: '#334155',
                            font: {
                                family: 'Roboto',
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.92)',
                        titleFont: { family: 'Roboto', weight: '700' },
                        bodyFont: { family: 'Roboto', weight: '500' },
                        padding: 10,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(148,163,184,0.18)'
                        },
                        ticks: {
                            color: '#64748b',
                            precision: 0,
                            font: {
                                family: 'Roboto',
                                weight: '600'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#475569',
                            font: {
                                family: 'Roboto',
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });
    }

    function buildSplitChart() {
        var canvas = document.getElementById('adminBookingSplitChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        destroyCanvasChart(canvas);
        destroyChart('split');
        registerCenterTextPlugin();
        canvas.style.height = '220px';
        canvas.style.maxHeight = '220px';

        var pending = Number(canvas.dataset.pending || 0);
        var confirmed = Number(canvas.dataset.confirmed || 0);
        var cancelled = Number(canvas.dataset.cancelled || 0);
        var total = Math.max(0, pending + confirmed + cancelled);

        var values = total > 0 ? [pending, confirmed, cancelled] : [1, 1, 1];
        var colors = total > 0
            ? ['#f59e0b', '#0ea5a5', '#ef4444']
            : ['#cbd5e1', '#cbd5e1', '#cbd5e1'];

        chartRefs.split = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Confirmed', 'Cancelled'],
                datasets: [
                    {
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var realValues = [pending, confirmed, cancelled];
                                var value = realValues[context.dataIndex] || 0;
                                return context.label + ': ' + value;
                            }
                        }
                    },
                    adminCenterTextPlugin: {
                        text: total,
                        subText: 'Total'
                    }
                }
            }
        });
    }

    function initAdminDashboardChart() {
        if (typeof Chart === 'undefined') {
            return;
        }
        buildTrendChart();
        buildSplitChart();
    }

    window.initAdminDashboardChart = initAdminDashboardChart;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminDashboardChart);
    } else {
        initAdminDashboardChart();
    }
})();
