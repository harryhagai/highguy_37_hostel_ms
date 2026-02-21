<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

// Example admin info (replace with your own session logic)
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$displayUsername = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)$username)) ?? '');
if ($displayUsername === '') {
    $displayUsername = 'Admin';
} else {
    $displayUsername = ucwords(strtolower($displayUsername));
}
$profile_pic = isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : '../assets/images/prof.jpg';

// --- PAGE ROUTING ---
$allowed = [
    'dashboard' => 'dashboard_content.php',
    'manage_users' => 'manage_users.php',
    'manage_hostel' => 'manage_hostel.php',
    'manage_rooms' => 'manage_rooms.php',
    'manage_beds' => 'manage_beds.php',
    'application_management' => 'application_management.php', // <-- Added!
    'notice' => 'notice.php',
    'settings' => 'settings.php'
];

$page = isset($_GET['page']) ? (string)$_GET['page'] : 'dashboard';
$isSpaRequest = isset($_GET['spa']) && $_GET['spa'] === '1';

function renderAdminPageContent(string $page, array $allowed): void
{
    if (array_key_exists($page, $allowed)) {
        include $allowed[$page];
        return;
    }

    echo '<div class="dashboard-card"><h4>Page not found.</h4></div>';
}

if ($isSpaRequest) {
    renderAdminPageContent($page, $allowed);
    exit;
}
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
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <link rel="stylesheet" href="../assets/css/admin-dashboard-layout.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard-content.css">
</head>
<body>
<!-- Header -->
<header class="dashboard-header">
    <div class="header-left">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" data-no-spinner="true" aria-label="Toggle sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
        <div class="header-title">
            <i class="bi bi-house-door-fill"></i> HostelPro Admin Dashboard
        </div>
    </div>
    <div class="header-search" role="search" aria-label="Dashboard quick search">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search student, hostel, room..." aria-label="Search">
    </div>
    <div class="header-tools">
        <div class="dropdown icon-dropdown">
            <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" data-no-spinner="true" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <span class="icon-badge">3</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li><span class="dropdown-item-text">2 new booking requests</span></li>
                <li><span class="dropdown-item-text">1 room marked maintenance</span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="admin_dashboard_layout.php?page=notice" data-spa-page="notice" data-no-spinner="true">View notices</a></li>
            </ul>
        </div>
        <div class="dropdown icon-dropdown">
            <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" data-no-spinner="true" aria-label="Quick actions">
                <i class="bi bi-grid"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Quick actions</h6></li>
                <li><a class="dropdown-item" href="admin_dashboard_layout.php?page=manage_users" data-spa-page="manage_users" data-no-spinner="true">Manage users</a></li>
                <li><a class="dropdown-item" href="admin_dashboard_layout.php?page=manage_rooms" data-spa-page="manage_rooms" data-no-spinner="true">Manage rooms</a></li>
                <li><a class="dropdown-item" href="admin_dashboard_layout.php?page=manage_beds" data-spa-page="manage_beds" data-no-spinner="true">Manage beds</a></li>
            </ul>
        </div>
        <div class="user-menu dropdown">
            <button type="button" class="profile-trigger" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-no-spinner="true">
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic header-profile-pic">
                <span class="user-name"><?= htmlspecialchars($displayUsername) ?></span>
                <i class="bi bi-caret-down-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                <li><a class="dropdown-item" href="admin_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a class="dropdown-item" href="admin_dashboard_layout.php?page=settings" data-spa-page="settings" data-no-spinner="true"><i class="bi bi-person-circle"></i> My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>
<div class="dashboard-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="profile sidebar-brand">
            <span class="profile-pic sidebar-brand-icon rounded-circle">
                <img src="../assets/images/logo.png" alt="HostelPro Logo" class="sidebar-brand-logo rounded-circle">
            </span>
            <div class="profile-name">HostelPro System</div>
        </div>
        <div class="sidebar-main">
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="admin_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
                    <li><a href="admin_dashboard_layout.php?page=manage_users" data-spa-page="manage_users" data-no-spinner="true" class="<?= $page === 'manage_users' ? 'active' : '' ?>"><i class="bi bi-people"></i> <span>Manage Users</span></a></li>
                    <li><a href="admin_dashboard_layout.php?page=manage_hostel" data-spa-page="manage_hostel" data-no-spinner="true" class="<?= $page === 'manage_hostel' ? 'active' : '' ?>"><i class="bi bi-building"></i> <span>Manage Hostels</span></a></li>
                    <li><a href="admin_dashboard_layout.php?page=manage_rooms" data-spa-page="manage_rooms" data-no-spinner="true" class="<?= $page === 'manage_rooms' ? 'active' : '' ?>"><i class="bi bi-door-open"></i> <span>Manage Rooms</span></a></li>
                    <li><a href="admin_dashboard_layout.php?page=manage_beds" data-spa-page="manage_beds" data-no-spinner="true" class="<?= $page === 'manage_beds' ? 'active' : '' ?>"><i class="bi bi-grid-3x3-gap"></i> <span>Manage Beds</span></a></li>
                    <li><a href="admin_dashboard_layout.php?page=application_management" data-spa-page="application_management" data-no-spinner="true" class="<?= $page === 'application_management' ? 'active' : '' ?>"><i class="bi bi-clipboard-check"></i> <span>Application</span></a></li>
                    <li><a href="admin_dashboard_layout.php?page=notice" data-spa-page="notice" data-no-spinner="true" class="<?= $page === 'notice' ? 'active' : '' ?>"><i class="bi bi-megaphone"></i> <span>Notices</span></a></li>
                </ul>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-divider"></div>
            <a href="admin_dashboard_layout.php?page=settings" data-spa-page="settings" data-no-spinner="true" class="<?= $page === 'settings' ? 'active' : '' ?>"><i class="bi bi-gear"></i> <span>Settings</span></a>
            <a href="../auth/logout.php" class="logout-link"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <!-- Main Content -->
    <div class="main-content">
        <main class="dashboard-content" id="dashboardContent" data-current-page="<?= htmlspecialchars($page) ?>">
            <?php renderAdminPageContent($page, $allowed); ?>
        </main>
    </div>
</div>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ui-spinner.js"></script>
<script src="../assets/js/admin-chart.js"></script>
<script src="../assets/js/admin-shell.js"></script>
<script src="../assets/js/admin-spa.js"></script>
</body>
<?php ob_end_flush(); ?>
</html>



