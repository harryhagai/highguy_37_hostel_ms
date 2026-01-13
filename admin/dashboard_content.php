

<?php
// If this file is loaded via AJAX, you may need to include the DB connection and stats logic.
// If stats are passed in from the parent, you can skip the DB queries here.
if (!isset($total_users)) {
    require_once __DIR__ . '/../config/db_connection.php';
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_hostels = $pdo->query("SELECT COUNT(*) FROM hostels")->fetchColumn();
    $rooms_available = $pdo->query("SELECT COUNT(*) FROM rooms WHERE available > 0")->fetchColumn();
    $full_rooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE available = 0")->fetchColumn();

    // Chart data
    $months = $applications = $approved = $rejected = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M', strtotime("-$i months"));
        $months[] = $month;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE MONTH(booking_date) = ? AND YEAR(booking_date) = ?");
        $stmt->execute([date('n', strtotime("-$i months")), date('Y', strtotime("-$i months"))]);
        $applications[] = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND MONTH(booking_date) = ? AND YEAR(booking_date) = ?");
        $stmt->execute([date('n', strtotime("-$i months")), date('Y', strtotime("-$i months"))]);
        $approved[] = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled' AND MONTH(booking_date) = ? AND YEAR(booking_date) = ?");
        $stmt->execute([date('n', strtotime("-$i months")), date('Y', strtotime("-$i months"))]);
        $rejected[] = (int)$stmt->fetchColumn();
    }
}
?>



<style>
/* CSS yako yote hapa */
.tiles-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2.2rem;
    flex-wrap: wrap;
}
.tile {
    flex: 1 1 180px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(28,202,216,0.08);
    padding: 1.5rem 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 180px;
}
.tile-icon {
    font-size: 2.2rem;
    color: #11998e;
    background: #1ccad8;
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 2px 10px rgba(28,202,216,0.08);
}
.tile-content { flex: 1; }
.tile-label {
    font-size: 1.05rem;
    color: #11998e;
    font-weight: 500;
    margin-bottom: 2px;
}
.tile-value {
    font-size: 1.55rem;
    font-weight: 700;
    color: #233142;
}
.dashboard-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(28,202,216,0.08);
    padding: 2rem 1.5rem;
    margin-bottom: 1.5rem;
}
/* ...ongeza zingine unazotaka... */
</style>





<div class="tiles-row">
    <div class="tile">
        <div class="tile-icon"><i class="bi bi-people"></i></div>
        <div class="tile-content">
            <div class="tile-label">Total Users</div>
            <div class="tile-value"><?= $total_users ?></div>
        </div>
    </div>
    <div class="tile">
        <div class="tile-icon"><i class="bi bi-building"></i></div>
        <div class="tile-content">
            <div class="tile-label">Total Hostels</div>
            <div class="tile-value"><?= $total_hostels ?></div>
        </div>
    </div>
    <div class="tile">
        <div class="tile-icon"><i class="bi bi-door-open"></i></div>
        <div class="tile-content">
            <div class="tile-label">Rooms Available</div>
            <div class="tile-value"><?= $rooms_available ?></div>
        </div>
    </div>
    <div class="tile">
        <div class="tile-icon"><i class="bi bi-door-closed"></i></div>
        <div class="tile-content">
            <div class="tile-label">Full Rooms</div>
            <div class="tile-value"><?= $full_rooms ?></div>
        </div>
    </div>
</div>
<div class="dashboard-card">
    <h5 class="mb-4"><i class="bi bi-graph-up-arrow"></i> Application Management Trends (Last 6 Months)</h5>
    <canvas id="adminDashboardChart" height="90"></canvas>
</div>
<script>
function loadDashboardChart() {
    const ctx = document.getElementById('adminDashboardChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [
                    {
                        label: 'Applications',
                        data: <?= json_encode($applications) ?>,
                        borderColor: 'rgba(28,202,216,1)',
                        backgroundColor: 'rgba(28,202,216,0.1)',
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        fill: true
                    },
                    {
                        label: 'Approved',
                        data: <?= json_encode($approved) ?>,
                        borderColor: 'rgba(17,153,142,1)',
                        backgroundColor: 'rgba(17,153,142,0.1)',
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        fill: true
                    },
                    {
                        label: 'Rejected',
                        data: <?= json_encode($rejected) ?>,
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
}
loadDashboardChart();
</script>
