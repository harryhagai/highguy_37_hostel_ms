

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




<link rel="stylesheet" href="../assets/css/admin-dashboard-content.css">





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
    <canvas
        id="adminDashboardChart"
        height="90"
        data-labels='<?= htmlspecialchars(json_encode($months), ENT_QUOTES, "UTF-8") ?>'
        data-applications='<?= htmlspecialchars(json_encode($applications), ENT_QUOTES, "UTF-8") ?>'
        data-approved='<?= htmlspecialchars(json_encode($approved), ENT_QUOTES, "UTF-8") ?>'
        data-rejected='<?= htmlspecialchars(json_encode($rejected), ENT_QUOTES, "UTF-8") ?>'>
    </canvas>
</div>
<script src="../assets/js/admin-chart.js"></script>


