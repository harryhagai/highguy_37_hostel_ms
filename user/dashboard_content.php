<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/dashboard_controller.php';
$stats = $state['stats'];
$recentBookings = $state['recent_bookings'];
$announcements = is_array($state['announcements'] ?? null) ? $state['announcements'] : [];
$announcementCount = (int)($state['announcement_count'] ?? count($announcements));
$canBook = (bool)($state['can_book'] ?? true);
$studentDisplayName = trim((string)($state['student_display_name'] ?? 'Student'));
$studentDisplayName = $studentDisplayName !== '' ? $studentDisplayName : 'Student';
$bookingLock = is_array($state['booking_lock'] ?? null) ? $state['booking_lock'] : ['blocked' => false, 'message' => ''];
?>
<div class="dashboard-intro-card dashboard-card mb-3 user-hero-card">
    <div class="dashboard-intro-left">
        <p class="dashboard-kicker mb-1">Student Overview</p>
        <h4 class="mb-1">Welcome <span class="student-name-highlight"><?= htmlspecialchars($studentDisplayName) ?></span> to your hostel space</h4>
        <p class="text-muted mb-0">Track your booking progress, find available hostels, and reserve rooms faster.</p>
    </div>
    <div class="dashboard-intro-right d-flex gap-2 flex-wrap">
        <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-buildings me-1"></i>Browse Hostels
        </a>
        <?php if ($canBook): ?>
            <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-success">
                <i class="bi bi-calendar-plus me-1"></i>Book Bed
            </a>
        <?php else: ?>
            <a href="user_dashboard_layout.php?page=my_bed" data-spa-page="my_bed" data-no-spinner="true" class="btn btn-sm btn-outline-success">
                <i class="bi bi-house-check me-1"></i>View My Bed
            </a>
        <?php endif; ?>
        <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-check me-1"></i>My Bookings
        </a>
        <a href="user_dashboard_layout.php?page=my_room" data-spa-page="my_room" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-house-heart me-1"></i>My Room
        </a>
    </div>
</div>

<div class="alert alert-info py-2 px-3 small mb-3 hostel-booking-flow-note">
    <i class="bi bi-info-circle me-1"></i>Select hostel first, then room, then bed. Booking flow: choose room, then select an available bed inside that room.
</div>

<?php if (!empty($bookingLock['blocked'])): ?>
    <div class="alert alert-warning mb-3">
        <i class="bi bi-lock me-1"></i><?= htmlspecialchars((string)($bookingLock['message'] ?? 'Booking is currently locked for your account.')) ?>
    </div>
<?php endif; ?>

<div class="stats-grid mb-3 user-stats-grid">
    <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
        <div>
            <p class="stat-label">Total Bookings</p>
            <h3 class="stat-value"><?= (int)$stats['total_bookings'] ?></h3>
        </div>
    </a>
    <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <p class="stat-label">Pending</p>
            <h3 class="stat-value"><?= (int)$stats['pending'] ?></h3>
        </div>
    </a>
    <a href="user_dashboard_layout.php?page=my_bed" data-spa-page="my_bed" data-no-spinner="true" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
        <div>
            <p class="stat-label">Confirmed</p>
            <h3 class="stat-value"><?= (int)$stats['confirmed'] ?></h3>
        </div>
    </a>
    <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="bi bi-door-open"></i></div>
        <div>
            <p class="stat-label">Rooms Available</p>
            <h3 class="stat-value"><?= (int)$stats['available_rooms'] ?></h3>
        </div>
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6 order-2 order-xl-1">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0 dashboard-section-title dashboard-section-title-bookings"><i class="bi bi-clock-history me-2"></i>Recent Bookings</h5>
                <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>

            <?php if (empty($recentBookings)): ?>
                <div class="alert alert-light border mb-0">You do not have recent bookings yet.</div>
            <?php else: ?>
                <div class="student-booking-grid">
                    <?php foreach ($recentBookings as $booking): ?>
                        <article class="student-booking-card status-<?= htmlspecialchars($booking['status_key']) ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h6 class="mb-0"><?= htmlspecialchars((string)$booking['hostel_name']) ?></h6>
                                <span class="badge rounded-pill text-bg-light"><?= htmlspecialchars((string)$booking['status_label']) ?></span>
                            </div>
                            <p class="mb-1 text-muted small">
                                Room <?= htmlspecialchars((string)$booking['room_number']) ?>
                                <?php if (!empty($booking['bed_number'])): ?>
                                    | Bed <?= htmlspecialchars((string)$booking['bed_number']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($booking['stay_period'])): ?>
                                <p class="mb-1 small"><i class="bi bi-calendar-range me-1"></i><?= htmlspecialchars((string)$booking['stay_period']) ?></p>
                            <?php endif; ?>
                            <p class="mb-0 small text-muted"><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string)$booking['booking_date_display']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-6 order-1 order-xl-2">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0 dashboard-section-title dashboard-section-title-announcement"><i class="bi bi-megaphone me-2"></i>Announcement</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge text-bg-light announcement-count-badge"><?= (int)$announcementCount ?> total</span>
                    <a href="user_dashboard_layout.php?page=notices" data-spa-page="notices" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">View all</a>
                </div>
            </div>
            <?php if (empty($announcements)): ?>
                <div class="alert alert-light border mb-0">No announcements at this time.</div>
            <?php else: ?>
                <div class="announcement-scroll-list">
                    <?php foreach ($announcements as $notice): ?>
                        <article class="announcement-item">
                            <h6 class="mb-1"><?= htmlspecialchars((string)($notice['title'] ?? 'Untitled Notice')) ?></h6>
                            <p class="mb-2 small text-muted"><?= nl2br(htmlspecialchars((string)($notice['content'] ?? ''))) ?></p>
                            <small class="text-muted">Posted: <?= htmlspecialchars((string)($notice['created_at_display'] ?? '-')) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
