<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/dashboard_controller.php';
$stats = $state['stats'];
$recentBookings = $state['recent_bookings'];
$announcement = $state['announcement'];
$suggestedHostels = $state['suggested_hostels'];
?>
<div class="dashboard-intro-card dashboard-card mb-3 user-hero-card">
    <div class="dashboard-intro-left">
        <p class="dashboard-kicker mb-1">Student Overview</p>
        <h4 class="mb-1">Welcome to your hostel space</h4>
        <p class="text-muted mb-0">Track your booking progress, find available hostels, and reserve rooms faster.</p>
    </div>
    <div class="dashboard-intro-right d-flex gap-2 flex-wrap">
        <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-buildings me-1"></i>Browse Hostels
        </a>
        <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-check me-1"></i>My Bookings
        </a>
    </div>
</div>

<div class="stats-grid mb-3 user-stats-grid">
    <article class="stat-card">
        <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
        <div>
            <p class="stat-label">Total Bookings</p>
            <h3 class="stat-value"><?= (int)$stats['total_bookings'] ?></h3>
        </div>
    </article>
    <article class="stat-card">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <p class="stat-label">Pending</p>
            <h3 class="stat-value"><?= (int)$stats['pending'] ?></h3>
        </div>
    </article>
    <article class="stat-card">
        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
        <div>
            <p class="stat-label">Confirmed</p>
            <h3 class="stat-value"><?= (int)$stats['confirmed'] ?></h3>
        </div>
    </article>
    <article class="stat-card">
        <div class="stat-icon"><i class="bi bi-door-open"></i></div>
        <div>
            <p class="stat-label">Rooms Available</p>
            <h3 class="stat-value"><?= (int)$stats['available_rooms'] ?></h3>
        </div>
    </article>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="dashboard-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Bookings</h5>
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

    <div class="col-xl-4">
        <div class="dashboard-card h-100">
            <h5 class="mb-3"><i class="bi bi-megaphone me-2"></i>Announcement</h5>
            <?php if (!empty($announcement)): ?>
                <h6 class="mb-1"><?= htmlspecialchars((string)$announcement['title']) ?></h6>
                <p class="text-muted mb-2"><?= nl2br(htmlspecialchars((string)$announcement['content'])) ?></p>
                <small class="text-muted">Posted: <?= date('d M Y', strtotime((string)$announcement['created_at'])) ?></small>
            <?php else: ?>
                <div class="alert alert-light border mb-0">No announcements at this time.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-buildings me-2"></i>Recommended Hostels</h5>
        <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="btn btn-sm btn-outline-primary">Open all hostels</a>
    </div>

    <?php if (empty($suggestedHostels)): ?>
        <div class="alert alert-light border mb-0">No hostels available right now.</div>
    <?php else: ?>
        <div class="student-hostel-grid compact-grid">
            <?php foreach ($suggestedHostels as $hostel): ?>
                <article class="student-hostel-card">
                    <img src="<?= htmlspecialchars((string)$hostel['hostel_image_url']) ?>" alt="Hostel" class="student-hostel-thumb">
                    <div class="student-hostel-body">
                        <h6 class="mb-1"><?= htmlspecialchars((string)$hostel['name']) ?></h6>
                        <p class="mb-2 small text-muted"><?= htmlspecialchars((string)$hostel['location']) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge text-bg-light"><?= htmlspecialchars((string)($hostel['gender_label'] ?? 'All Genders')) ?></span>
                            <span class="badge text-bg-success"><?= (int)$hostel['free_rooms'] ?> free rooms</span>
                        </div>
                        <a href="user_dashboard_layout.php?page=book_bed&hostel_id=<?= (int)$hostel['id'] ?>" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-primary">Book Bed</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
