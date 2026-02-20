<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db_connection.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

// Example admin info (replace with your own session logic)
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$profile_pic = isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : '../assets/images/prof.jpg';

// --- DYNAMIC STATISTICS ---
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_hostels = $pdo->query("SELECT COUNT(*) FROM hostels")->fetchColumn();
$hasRoomsAvailableColumn = columnExists($pdo, 'rooms', 'available');
$hasBedsTable = tableExists($pdo, 'beds');
$hasBookingBed = columnExists($pdo, 'bookings', 'bed_id');
$hasBookingStart = columnExists($pdo, 'bookings', 'start_date');
$hasBookingEnd = columnExists($pdo, 'bookings', 'end_date');

if ($hasRoomsAvailableColumn) {
    // Legacy schema
    $rooms_available = $pdo->query("SELECT COUNT(*) FROM rooms WHERE available > 0")->fetchColumn();
    $full_rooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE available = 0")->fetchColumn();
} elseif ($hasBedsTable && $hasBookingBed && $hasBookingStart && $hasBookingEnd) {
    // New schema: room availability derives from active beds and active date-range bookings
    $rooms_available = $pdo->query("
        SELECT COUNT(DISTINCT b.room_id)
        FROM beds b
        LEFT JOIN bookings bk
            ON bk.bed_id = b.id
           AND bk.status IN ('pending', 'confirmed')
           AND CURDATE() >= bk.start_date
           AND CURDATE() < bk.end_date
        WHERE b.status = 'active'
          AND bk.id IS NULL
    ")->fetchColumn();

    $full_rooms = $pdo->query("
        SELECT COUNT(*)
        FROM (
            SELECT b.room_id,
                   SUM(CASE WHEN bk.id IS NULL THEN 1 ELSE 0 END) AS free_beds
            FROM beds b
            LEFT JOIN bookings bk
                ON bk.bed_id = b.id
               AND bk.status IN ('pending', 'confirmed')
               AND CURDATE() >= bk.start_date
               AND CURDATE() < bk.end_date
            WHERE b.status = 'active'
            GROUP BY b.room_id
            HAVING free_beds = 0
        ) room_state
    ")->fetchColumn();
} else {
    // Fallback (schema still in transition)
    $rooms_available = 0;
    $full_rooms = 0;
}

// --- MULTI-LINE GRAPH DATA (Application Management) ---
$months = [];
$applications = [];
$approved = [];
$rejected = [];
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

// --- PAGE ROUTING ---
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$allowed = [
    'dashboard' => 'dashboard_content.php',
    'manage_users' => 'manage_users.php',
    'manage_hostel' => 'manage_hostel.php',
    'manage_rooms' => 'manage_rooms.php',
    'manage_beds' => 'manage_beds.php',
    'application_management' => 'application_management.php', // <-- Added!
    'notice' => 'notice.php'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/admin-dashboard-layout.css">
</head>
<body>
<!-- Header -->
<header class="dashboard-header">
    <div class="header-title">
        <i class="bi bi-house-door-fill"></i> HostelPro Admin Dashboard
    </div>
    <div class="user-menu dropdown">
        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic header-profile-pic">
        <span class="user-name"><?= htmlspecialchars($username) ?></span>
        <a href="#" class="dropdown-toggle" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-caret-down-fill"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
            <li><a class="dropdown-item" href="admin_dashboard_layout.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </div>
</header>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="profile">
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-pic" id="sidebarProfilePic">
            <div class="profile-name"><?= htmlspecialchars($username) ?></div>
        </div>
        <nav class="sidebar-menu">
            <ul>
                <li><a href="admin_dashboard_layout.php?page=dashboard" class="<?= (!isset($_GET['page']) || $_GET['page']=='dashboard')?'active':'' ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
                <li><a href="admin_dashboard_layout.php?page=manage_users" class="<?= (isset($_GET['page']) && $_GET['page']=='manage_users')?'active':'' ?>"><i class="bi bi-people"></i> <span>Manage Users</span></a></li>
                <li><a href="admin_dashboard_layout.php?page=manage_hostel" class="<?= (isset($_GET['page']) && $_GET['page']=='manage_hostel')?'active':'' ?>"><i class="bi bi-building"></i> <span>Manage Hostels</span></a></li>
                <li><a href="admin_dashboard_layout.php?page=manage_rooms" class="<?= (isset($_GET['page']) && $_GET['page']=='manage_rooms')?'active':'' ?>"><i class="bi bi-door-open"></i> <span>Manage Rooms</span></a></li>
                <li><a href="admin_dashboard_layout.php?page=manage_beds" class="<?= (isset($_GET['page']) && $_GET['page']=='manage_beds')?'active':'' ?>"><i class="bi bi-grid-3x3-gap"></i> <span>Manage Beds</span></a></li>
                <!-- Application Management added here -->
                <li><a href="admin_dashboard_layout.php?page=application_management" class="<?= (isset($_GET['page']) && $_GET['page']=='application_management')?'active':'' ?>"><i class="bi bi-clipboard-check"></i> <span>Application</span></a></li>
                <li><a href="admin_dashboard_layout.php?page=notice" class="<?= (isset($_GET['page']) && $_GET['page']=='notice')?'active':'' ?>"><i class="bi bi-megaphone"></i> <span>Notices</span></a></li>
                <li><a href="../logout.php"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>
    <!-- Main Content -->
    <div class="main-content">
        <main class="dashboard-content" id="dashboardContent">
            <?php
            // Load the correct content page
            if ($page === 'dashboard') {
                // Inline dashboard content (cards + chart)
                ?>
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
                <?php
            } elseif (array_key_exists($page, $allowed)) {
                include $allowed[$page];
            } else {
                echo '<div class="dashboard-card"><h4>Page not found.</h4></div>';
            }
            ?>
        </main>
    </div>
</div>
<!-- Fixed Footer -->
<footer class="dashboard-footer">
    <span>© <?= date('Y') ?> HostelPro Admin. All rights reserved.</span>
    <span class="social-links ms-3">
        <a href="#"><i class="bi bi-facebook"></i></a>
        <a href="#"><i class="bi bi-twitter"></i></a>
        <a href="#"><i class="bi bi-instagram"></i></a>
        <a href="#"><i class="bi bi-linkedin"></i></a>
    </span>
</footer>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ui-spinner.js"></script>
<script src="../assets/js/admin-chart.js"></script>
</body>
<?php ob_end_flush(); ?>
</html>


