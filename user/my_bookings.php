<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/my_bookings_controller.php';
$message = $state['message'];
$bookings = $state['bookings'];
$stats = $state['stats'];
?>
<div class="container-fluid px-0 user-bookings-page">
    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Bookings</p>
            <h5 class="mb-0"><?= (int)$stats['total'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Pending</p>
            <h5 class="mb-0"><?= (int)$stats['pending'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Confirmed</p>
            <h5 class="mb-0"><?= (int)$stats['confirmed'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Cancelled</p>
            <h5 class="mb-0"><?= (int)$stats['cancelled'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">My Bookings</h4>
                <p class="text-muted mb-0">Track, review, and remove your booking requests.</p>
            </div>
            <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-calendar-plus me-1"></i>Book New Bed
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string)$message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($bookings)): ?>
        <div class="alert alert-info">You have not made any bookings yet.</div>
    <?php else: ?>
        <div class="student-booking-grid wide-grid">
            <?php foreach ($bookings as $booking): ?>
                <article class="student-booking-card status-<?= htmlspecialchars((string)$booking['status_key']) ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars((string)$booking['hostel_name']) ?></h5>
                            <p class="mb-0 text-muted">
                                Room <?= htmlspecialchars((string)$booking['room_number']) ?>
                                <?php if (!empty($booking['bed_number'])): ?>
                                    | Bed <?= htmlspecialchars((string)$booking['bed_number']) ?>
                                <?php endif; ?>
                                <?php if (!empty($booking['room_type'])): ?>
                                    (<?= htmlspecialchars((string)$booking['room_type']) ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="badge rounded-pill text-bg-light"><?= htmlspecialchars((string)$booking['status_label']) ?></span>
                    </div>

                    <p class="mb-1 small text-muted"><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string)$booking['booking_date_display']) ?></p>
                    <?php if (!empty($booking['stay_period'])): ?>
                        <p class="mb-3 small"><i class="bi bi-calendar-range me-1"></i><?= htmlspecialchars((string)$booking['stay_period']) ?></p>
                    <?php else: ?>
                        <div class="mb-3"></div>
                    <?php endif; ?>

                    <form method="post" data-confirm="Are you sure you want to delete this booking?" class="mt-auto">
                        <input type="hidden" name="action" value="delete_booking">
                        <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
