<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';

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
$stmt = $pdo->query("SELECT COUNT(*) FROM rooms WHERE available > 0");
$available_rooms = $stmt->fetchColumn();

// Fetch recent bookings (last 3)
$stmt = $pdo->prepare("SELECT b.id, b.booking_date, r.room_number, h.name AS hostel_name, b.status
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN hostels h ON r.hostel_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC LIMIT 3");
$stmt->execute([$user_id]);
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <style>
        :root {
            --aqua: #1ccad8;
            --aqua-dark: #11998e;
            --accent: #f6c23e;
            --white: #fff;
            --dark: #233142;
            --sidebar-width: 240px;
            --header-height: 70px;
            --footer-height: 48px;
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #f8f9fc;
        }
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            min-height: 100vh;
            width: 100vw;
        }
        .dashboard-wrapper {
            min-height: 100vh;
            width: 100vw;
            background: #f8f9fc;
        }
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(120deg, var(--aqua-dark) 60%, var(--aqua) 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            box-shadow: 2px 0 12px rgba(28, 202, 216, 0.07);
        }
        .sidebar .profile {
            width: 100%;
            padding: 2rem 0 1rem 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .sidebar .profile-pic {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent);
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.09);
            margin-bottom: 0.7rem;
        }
        .sidebar .profile-name {
            font-weight: 600;
            font-size: 1.12rem;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        .sidebar .sidebar-menu {
            width: 100%;
            margin-top: 2rem;
        }
        .sidebar .sidebar-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar .sidebar-menu li {
            width: 100%;
        }
        .sidebar .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 32px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.22s, color 0.22s;
            border-left: 4px solid transparent;
        }
        .sidebar .sidebar-menu a.active,
        .sidebar .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.09);
            color: var(--accent);
            border-left: 4px solid var(--accent);
        }
        /* Main Content + Right Sidebar Layout */
        .main-content-row {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: flex-start;
        }
        .main-content {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .dashboard-header {
            background: var(--white);
            box-shadow: 0 2px 8px rgba(28, 202, 216, 0.07);
            padding: 1.1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
            min-height: var(--header-height);
            max-height: var(--header-height);
            position: sticky;
            top: 0;
            z-index: 101;
        }
        .header-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--aqua-dark);
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .user-menu .user-name {
            font-weight: 600;
            color: var(--aqua-dark);
        }
        .user-menu .dropdown-toggle::after {
            display: none;
        }
        .dashboard-content {
            flex: 1;
            overflow-y: auto;
            padding: 2.2rem 2rem 2rem 2rem;
            background: #f8f9fc;
            min-height: 0;
        }
        .dashboard-footer {
            background: #f8f9fc;
            color: #233142;
            display: flex;
            align-items: center;
            justify-content: center;
            height: var(--footer-height);
            min-height: var(--footer-height);
            max-height: var(--footer-height);
            box-shadow: 0 -2px 8px rgba(28, 202, 216, 0.07);
            font-size: 1rem;
            position: sticky;
            bottom: 0;
            z-index: 101;
        }
        .dashboard-card {
            background: var(--white);
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(28, 202, 216, 0.08);
            padding: 2rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        /* Stat cards */
        .stat-card {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(28, 202, 216, 0.08);
            padding: 1.5rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 160px;
            text-align: center;
        }
        .stat-icon {
            font-size: 2.2rem;
            color: var(--aqua-dark);
            background: var(--aqua);
            border-radius: 12px;
            padding: 10px;
        }
        .stat-label {
            font-size: 1.05rem;
            color: var(--aqua-dark);
            font-weight: 500;
            margin-bottom: 2px;
        }
        .stat-value {
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--dark);
        }
        .quick-actions .btn {
            min-width: 180px;
            margin-bottom: 10px;
        }
        /* Payment Section (Right Sidebar) */
        .right-sidebar {
            width: 340px;
            min-width: 280px;
            max-width: 100vw;
            background: #fff;
            border-left: 1px solid #e0e0e0;
            box-shadow: -2px 0 12px rgba(28,202,216,0.04);
            padding: 2rem 1.2rem 1.2rem 1.2rem;
            height: 100vh;
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .right-sidebar .payment-section h6 {
            font-weight: 700;
            font-size: 1.03rem;
            margin-bottom: 0.7rem;
            color: #11998e;
        }
        .right-sidebar .payment-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 7px;
            margin-bottom: 0.6rem;
        }
        .right-sidebar .payment-logos img {
            height: 36px;
            filter: grayscale(0.2);
            background: #f8f9fc;
            border-radius: 6px;
            padding: 2px 4px;
            transition: filter 0.2s;
        }
        .right-sidebar .payment-logos img:hover {
            filter: grayscale(0);
        }
        .right-sidebar .control-number {
            font-size: 1.12rem;
            color: #11998e;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 0.2rem;
        }
        .right-sidebar .payment-section .alert {
            padding: 0.4rem 0.7rem;
            font-size: 0.97rem;
            margin-bottom: 0.4rem;
        }
        .right-sidebar .payment-section .admin-phone {
            color: #f6c23e;
            font-weight: 600;
            font-size: 1.03rem;
        }
        @media (max-width: 991.98px) {
            .right-sidebar {
                width: 100%;
                min-width: 0;
                position: static;
                border-left: none;
                box-shadow: none;
                margin-top: 2rem;
                height: auto;
            }
            .main-content-row {
                flex-direction: column;
                margin-left: 0;
            }
            .main-content {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
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
                        <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic" style="width:38px; height:38px; object-fit:cover;">
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
                        <span style="font-weight:600;">Control Number:</span>
                        <div class="control-number"><?= htmlspecialchars($control_number) ?></div>
                    </div>
                    <div class="alert alert-warning mt-2 mb-2 p-2">
                        <b>Important:</b> Fanya malipo kulingana na usajili wako.<br>
                        Baada ya malipo, piga admin kuthibitisha:
                        <div class="admin-phone">+255 764 384 905</div>
                    </div>
                    <div class="mb-1" style="font-size:0.96em;">
                        <b>Jinsi ya Kulipa:</b>
                        <ul style="padding-left:18px; margin-bottom:0;">
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
</body>
</html>
