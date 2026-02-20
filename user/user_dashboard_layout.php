<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

function udColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function udTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT username, email, profile_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$username = $user['username'];
$email = $user['email'];
$profile_pic = $user['profile_photo'] ? $user['profile_photo'] : '../assets/images/prof.jpg';

// Fetch My Bookings count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_bookings = $stmt->fetchColumn();

// Fetch Available Rooms count
$hasRoomsAvailable = udColumnExists($pdo, 'rooms', 'available');
$hasBedsTable = udTableExists($pdo, 'beds');
$hasBookingBed = udColumnExists($pdo, 'bookings', 'bed_id');
$hasBookingStart = udColumnExists($pdo, 'bookings', 'start_date');
$hasBookingEnd = udColumnExists($pdo, 'bookings', 'end_date');

if ($hasRoomsAvailable) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms WHERE available > 0");
    $available_rooms = $stmt->fetchColumn();
} elseif ($hasBedsTable && $hasBookingBed && $hasBookingStart && $hasBookingEnd) {
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT b.room_id)
        FROM beds b
        LEFT JOIN bookings bk
            ON bk.bed_id = b.id
           AND bk.status IN ('pending', 'confirmed')
           AND CURDATE() >= bk.start_date
           AND CURDATE() < bk.end_date
        WHERE b.status = 'active'
          AND bk.id IS NULL
    ");
    $available_rooms = $stmt->fetchColumn();
} else {
    $available_rooms = 0;
}

// Fetch recent bookings (last 3)
$hasBookingRoom = udColumnExists($pdo, 'bookings', 'room_id');
if ($hasBookingRoom) {
    $stmt = $pdo->prepare("SELECT b.id, b.booking_date, r.room_number, h.name AS hostel_name, b.status
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($hasBookingBed) {
    $stmt = $pdo->prepare("SELECT b.id, b.booking_date, r.room_number, h.name AS hostel_name, b.status
        FROM bookings b
        JOIN beds bd ON b.bed_id = bd.id
        JOIN rooms r ON bd.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recent_bookings = [];
}

// Fetch latest announcement
$stmt = $pdo->query("SELECT title, content, created_at FROM notices ORDER BY created_at DESC LIMIT 1");
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

// Example control number (make dynamic if needed)
$control_number = "991234567890";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/user-dashboard-layout.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile">
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-pic">
                <div class="profile-name"><?= htmlspecialchars($username) ?></div>
            </div>
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="user_dashboard_layout.php" class="<?= !isset($_GET['page']) ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=profile" class="<?= (($_GET['page'] ?? '') === 'profile') ? 'active' : '' ?>"><i class="bi bi-person"></i> <span>Profile</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=view_hostels" class="<?= (($_GET['page'] ?? '') === 'view_hostels') ? 'active' : '' ?>"><i class="bi bi-buildings"></i> <span>View Hostels</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=book_room" class="<?= (($_GET['page'] ?? '') === 'book_room') ? 'active' : '' ?>"><i class="bi bi-calendar-plus"></i> <span>Book Room</span></a></li>
                    <li><a href="user_dashboard_layout.php?page=my_bookings" class="<?= (($_GET['page'] ?? '') === 'my_bookings') ? 'active' : '' ?>"><i class="bi bi-calendar-check"></i> <span>My Bookings</span></a></li>
                    <li><a href="../logout.php"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a></li>
                </ul>
            </nav>
        </aside>
        <!-- Main Content + Right Sidebar -->
        <div class="main-content-row">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Header -->
                <header class="dashboard-header mb-2">
                    <div class="header-title">
                        <i class="bi bi-house-door-fill"></i> HostelPro User Dashboard
                    </div>
                    <div class="user-menu dropdown">
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic header-user-pic">
                        <span class="user-name"><?= htmlspecialchars($username) ?></span>
                        <a href="#" class="dropdown-toggle" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-caret-down-fill"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                            <li><a class="dropdown-item" href="user_dashboard_layout.php?page=profile"><i class="bi bi-person"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </header>
                <div class="dashboard-content">
                    <?php
                    // Dynamic page loader
                    $page = $_GET['page'] ?? 'dashboard';
                    if ($page === 'profile') {
                        include 'user_profile.php';
                    } elseif ($page === 'book_room') {
                        include 'book_room.php';
                    } elseif ($page === 'my_bookings') {
                        include 'my_bookings.php';
                    } elseif ($page === 'view_hostels') {
                        include 'view_hostels.php'; // Create this file to show all hostels
                    } else {
                    ?>
                        <!-- Stats Row -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-4 col-12">
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                                    <div>
                                        <div class="stat-label">My Bookings</div>
                                        <div class="stat-value"><?= $my_bookings ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-12">
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="bi bi-building"></i></div>
                                    <div>
                                        <div class="stat-label">Available Rooms</div>
                                        <div class="stat-value"><?= $available_rooms ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Quick Actions -->
                        <div class="dashboard-card quick-actions d-flex flex-wrap gap-3 mb-4">
                            <a href="user_dashboard_layout.php?page=book_room" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Book a Room</a>
                            <a href="user_dashboard_layout.php?page=my_bookings" class="btn btn-success"><i class="bi bi-calendar-check"></i> View Bookings</a>
                            <a href="user_dashboard_layout.php?page=profile" class="btn btn-warning"><i class="bi bi-person"></i> Update Profile</a>
                        </div>
                        <!-- Recent Bookings -->
                        <div class="dashboard-card">
                            <h5><i class="bi bi-clock-history"></i> Recent Bookings</h5>
                            <?php if ($recent_bookings): ?>
                                <ul>
                                    <?php foreach ($recent_bookings as $b): ?>
                                        <li>
                                            <?= htmlspecialchars($b['hostel_name']) ?>, Room <?= htmlspecialchars($b['room_number']) ?> -
                                            <?= htmlspecialchars($b['status']) ?> (<?= date('d M Y', strtotime($b['booking_date'])) ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No recent bookings.</p>
                            <?php endif; ?>
                        </div>
                        <!-- Announcements -->
                        <div class="dashboard-card">
                            <h5><i class="bi bi-megaphone"></i> Announcements</h5>
                            <?php if ($announcement): ?>
                                <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                <p><?= htmlspecialchars($announcement['content']) ?></p>
                                <small class="text-muted"><?= date('d M Y', strtotime($announcement['created_at'])) ?></small>
                            <?php else: ?>
                                <p>No announcements at this time.</p>
                            <?php endif; ?>
                        </div>
                    <?php } ?>
                </div>
                <!-- Footer -->
                <footer class="dashboard-footer">
                    &copy; <?= date('Y') ?> HostelPro. All rights reserved.
                </footer>
            </div>
            <!-- Right Sidebar: Payment Section -->
            <aside class="right-sidebar">
                <div class="payment-section w-100">
                    <h6><i class="bi bi-wallet2"></i> Payment Info</h6>
                    <div class="payment-logos mb-2">
                        <img src="../assets/images/mpesa.png" alt="M-Pesa" title="M-Pesa">
                        <img src="../assets/images/tigopesa.png" alt="Tigo Pesa" title="Tigo Pesa">
                        <img src="../assets/images/halopesa.png" alt="HaloPesa" title="HaloPesa">
                        <img src="../assets/images/airtelmoney.png" alt="Airtel Money" title="Airtel Money">
                    </div>
                    <div class="mb-1">
                        <span class="payment-label">Control Number:</span>
                        <div class="control-number"><?= htmlspecialchars($control_number) ?></div>
                    </div>
                    <div class="alert alert-warning mt-2 mb-2 p-2">
                        <b>Important:</b> Fanya malipo kulingana na usajili wako.<br>
                        Baada ya malipo, piga admin kuthibitisha:
                        <div class="admin-phone">+255 764 384 905</div>
                    </div>
                    <div class="mb-1 payment-help-text">
                        <b>Jinsi ya Kulipa:</b>
                        <ul class="payment-steps">
                            <li>M-Pesa: *150*00#</li>
                            <li>Tigo Pesa: *150*01#</li>
                            <li>Airtel Money: *150*60#</li>
                            <li>HaloPesa: *150*88#</li>
                        </ul>
                        Chagua <b>Pay Bill</b>, ingiza <b>Business Number: 001001</b>, kisha control number yako na kiasi husika.
                    </div>
                </div>
            </aside>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/ui-spinner.js"></script>
</body>
</html>


