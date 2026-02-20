(function () {
    function parseJSON(value) {
        try {
            return value ? JSON.parse(value) : [];
        } catch (e) {
            return [];
        }
    }

    function initAdminDashboardChart() {
        var canvas = document.getElementById('adminDashboardChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }
        if (canvas.dataset.chartBound === '1') {
            return;
        }
        canvas.dataset.chartBound = '1';

        var labels = parseJSON(canvas.dataset.labels);
        var applications = parseJSON(canvas.dataset.applications);
        var approved = parseJSON(canvas.dataset.approved);
        var rejected = parseJSON(canvas.dataset.rejected);

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Applications',
                        data: applications,
                        borderColor: 'rgba(28,202,216,1)',
                        backgroundColor: 'rgba(28,202,216,0.1)',
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        fill: true
                    },
                    {
                        label: 'Approved',
                        data: approved,
                        borderColor: 'rgba(17,153,142,1)',
                        backgroundColor: 'rgba(17,153,142,0.1)',
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        fill: true
                    },
                    {
                        label: 'Rejected',
                        data: rejected,
                        borderColor: 'rgba(246,194,62,1)',
                        backgroundColor: 'rgba(246,194,62,0.08)',
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Roboto',
                                weight: 'bold'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#11998e',
                            font: {
                                weight: 'bold',
                                family: 'Roboto'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            color: '#11998e',
                            font: {
                                weight: 'bold',
                                family: 'Roboto'
                            }
                        }
                    }
                }
            }
        });
    }

    window.initAdminDashboardChart = initAdminDashboardChart;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminDashboardChart);
    } else {
        initAdminDashboardChart();
    }
})();
