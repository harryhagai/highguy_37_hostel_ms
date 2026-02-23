<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$shellState = require __DIR__ . '/../controllers/user/shell_controller.php';
$displayUsername = (string)$shellState['display_username'];
$profilePic = (string)$shellState['profile_pic'];
$hasProfilePhoto = (bool)($shellState['has_profile_photo'] ?? false);
$primaryBookingPage = (string)($shellState['primary_booking_page'] ?? 'book_bed');
$primaryBookingLabel = (string)($shellState['primary_booking_label'] ?? 'Book Bed');
if (!in_array($primaryBookingPage, ['book_bed', 'my_bed'], true)) {
    $primaryBookingPage = 'book_bed';
}
if ($primaryBookingLabel === '') {
    $primaryBookingLabel = $primaryBookingPage === 'my_bed' ? 'My Bed' : 'Book Bed';
}
$roomMenuPage = (string)($shellState['room_menu_page'] ?? 'my_room');
$roomMenuLabel = (string)($shellState['room_menu_label'] ?? 'My Room');
if (!in_array($roomMenuPage, ['my_room', 'my_bed', 'view_hostels'], true)) {
    $roomMenuPage = 'my_room';
}
if ($roomMenuLabel === '') {
    $roomMenuLabel = $roomMenuPage === 'my_bed' ? 'My Bed' : ($roomMenuPage === 'view_hostels' ? 'View Rooms' : 'My Room');
}

$nameParts = preg_split('/\s+/', trim($displayUsername)) ?: [];
$firstName = $nameParts[0] ?? '';
$lastName = count($nameParts) > 1 ? ($nameParts[count($nameParts) - 1] ?? '') : '';
$initialA = $firstName !== '' ? substr($firstName, 0, 1) : 'S';
$initialB = $lastName !== '' ? substr($lastName, 0, 1) : (($firstName !== '' && strlen($firstName) > 1) ? substr($firstName, 1, 1) : $initialA);
$displayInitials = strtoupper($initialA . $initialB);

$allowed = [
    'dashboard' => 'dashboard_content.php',
    'notices' => 'notices.php',
    'view_hostels' => 'view_hostels.php',
    'my_bed' => 'my_bed.php',
    'book_bed' => 'book_bed.php',
    'my_room' => 'my_room.php',
    'book_room' => 'book_room.php',
    'my_bookings' => 'my_bookings.php',
    'payment_verification' => 'payment_verification.php',
    'profile' => 'user_profile.php',
];

$page = isset($_GET['page']) ? (string)$_GET['page'] : 'dashboard';
$isSpaRequest = isset($_GET['spa']) && $_GET['spa'] === '1';

function renderUserPageContent(string $page, array $allowed): void
{
    if (array_key_exists($page, $allowed)) {
        include $allowed[$page];
        return;
    }

    echo '<div class="dashboard-card"><h4>Page not found.</h4></div>';
}

if ($isSpaRequest) {
    renderUserPageContent($page, $allowed);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">

    <link rel="stylesheet" href="../assets/css/admin-dashboard-layout.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard-content.css">
    <link rel="stylesheet" href="../assets/css/user-dashboard-shell.css">
    <link rel="stylesheet" href="../assets/css/user-dashboard-content.css">
</head>
<body class="user-dashboard-theme">
<header class="dashboard-header">
    <div class="header-left">
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggle" data-no-spinner="true" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="header-title user-header-title">
            <img src="../assets/images/logo.png" alt="HostelPro Logo" class="header-title-logo">
            <span class="title-desktop">HostelPro Student Panel</span>
            <span class="title-mobile" aria-label="Student Panel">
                <span class="title-mobile-main">Student</span>
                <span class="title-mobile-sub">Panel</span>
            </span>
        </div>
    </div>
    <div class="header-search" role="search" aria-label="Student quick search">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search hostels, rooms, bookings..." aria-label="Search">
    </div>
    <div class="header-tools">
        <div class="dropdown icon-dropdown">
            <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" data-no-spinner="true" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <span class="icon-badge"><?= (int)$shellState['pending_bookings'] ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Notifications</h6></li>
                <li><span class="dropdown-item-text">Pending bookings: <?= (int)$shellState['pending_bookings'] ?></span></li>
                <?php if (!empty($shellState['latest_notice'])): ?>
                    <li><span class="dropdown-item-text">Latest notice: <?= htmlspecialchars((string)$shellState['latest_notice']['title']) ?></span></li>
                <?php else: ?>
                    <li><span class="dropdown-item-text">No notices yet.</span></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="dropdown icon-dropdown">
            <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" data-no-spinner="true" aria-label="Quick actions">
                <i class="bi bi-grid"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Quick actions</h6></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true">Browse hostels</a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=<?= htmlspecialchars($primaryBookingPage) ?>" data-spa-page="<?= htmlspecialchars($primaryBookingPage) ?>" data-no-spinner="true"><?= htmlspecialchars($primaryBookingLabel) ?></a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=<?= htmlspecialchars($roomMenuPage) ?>" data-spa-page="<?= htmlspecialchars($roomMenuPage) ?>" data-no-spinner="true"><?= htmlspecialchars($roomMenuLabel) ?></a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true">My bookings</a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=payment_verification" data-spa-page="payment_verification" data-no-spinner="true">Payment verification</a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=notices" data-spa-page="notices" data-no-spinner="true">Notices</a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=profile" data-spa-page="profile" data-no-spinner="true">My profile</a></li>
            </ul>
        </div>
        <div class="user-menu dropdown">
            <button type="button" class="profile-trigger" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-no-spinner="true">
                <?php if ($hasProfilePhoto): ?>
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile" class="profile-pic header-profile-pic">
                <?php else: ?>
                    <span class="header-profile-initials" title="<?= htmlspecialchars($displayUsername) ?>"><?= htmlspecialchars($displayInitials) ?></span>
                <?php endif; ?>
                <i class="bi bi-caret-down-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a class="dropdown-item" href="user_dashboard_layout.php?page=profile" data-spa-page="profile" data-no-spinner="true"><i class="bi bi-person-circle"></i> My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="profile sidebar-brand">
            <span class="profile-pic sidebar-brand-icon rounded-circle">
                <img src="../assets/images/logo.png" alt="HostelPro Logo" class="sidebar-brand-logo rounded-circle">
            </span>
            <div class="profile-name">HostelPro Student</div>
        </div>

        <div class="sidebar-main">
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="user_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="<?= $page === 'view_hostels' ? 'active' : '' ?>"><i class="bi bi-buildings"></i> <span>View Hostels</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=<?= htmlspecialchars($primaryBookingPage) ?>" data-spa-page="<?= htmlspecialchars($primaryBookingPage) ?>" data-no-spinner="true" class="<?= $page === $primaryBookingPage ? 'active' : '' ?>"><i class="bi <?= $primaryBookingPage === 'my_bed' ? 'bi-house-check' : 'bi-calendar-plus' ?>"></i> <span><?= htmlspecialchars($primaryBookingLabel) ?></span></a></li>
                    <li><a href="user_dashboard_layout.php?page=<?= htmlspecialchars($roomMenuPage) ?>" data-spa-page="<?= htmlspecialchars($roomMenuPage) ?>" data-no-spinner="true" class="<?= $page === $roomMenuPage ? 'active' : '' ?>"><i class="bi <?= $roomMenuPage === 'my_bed' ? 'bi-house-check' : ($roomMenuPage === 'view_hostels' ? 'bi-buildings' : 'bi-house-heart') ?>"></i> <span><?= htmlspecialchars($roomMenuLabel) ?></span></a></li>
                    <li><a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="<?= $page === 'my_bookings' ? 'active' : '' ?>"><i class="bi bi-journal-check"></i> <span>My Bookings</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=notices" data-spa-page="notices" data-no-spinner="true" class="<?= $page === 'notices' ? 'active' : '' ?>"><i class="bi bi-megaphone"></i> <span>Notices</span></a></li>
                </ul>
            </nav>
        </div>

        <div class="sidebar-footer">
            <div class="sidebar-divider"></div>
            <a href="user_dashboard_layout.php?page=profile" data-spa-page="profile" data-no-spinner="true" class="<?= $page === 'profile' ? 'active' : '' ?>"><i class="bi bi-gear"></i> <span>Settings</span></a>
            <a href="../auth/logout.php" class="logout-link"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">
        <main class="dashboard-content" id="dashboardContent" data-current-page="<?= htmlspecialchars($page) ?>">
            <?php renderUserPageContent($page, $allowed); ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/admin-alerts.js"></script>
<script src="../assets/js/ui-spinner.js"></script>
<script src="../assets/js/user-shell.js"></script>
<script src="../assets/js/user-spa.js"></script>
</body>
<?php ob_end_flush(); ?>
</html>
