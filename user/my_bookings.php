<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/my_bookings_controller.php';
$message = $state['message'];
$bookings = $state['bookings'];
$canBook = (bool)($state['can_book'] ?? true);
$bookingLock = is_array($state['booking_lock'] ?? null) ? $state['booking_lock'] : ['blocked' => false, 'message' => ''];
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
                <p class="text-muted mb-0">Track and manage your booking requests.</p>
            </div>
            <?php if ($canBook): ?>
                <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-calendar-plus me-1"></i>Book New Bed
                </a>
            <?php else: ?>
                <a href="user_dashboard_layout.php?page=my_bed" data-spa-page="my_bed" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-house-check me-1"></i>My Bed
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($bookingLock['blocked'])): ?>
        <div class="alert alert-warning">
            <i class="bi bi-lock me-1"></i><?= htmlspecialchars((string)($bookingLock['message'] ?? 'Booking is currently locked for your account.')) ?>
        </div>
    <?php endif; ?>

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
                    <?php if (!empty($booking['booking_token'])): ?>
                        <p class="mb-3 small text-muted"><i class="bi bi-key me-1"></i>Token: <?= htmlspecialchars((string)$booking['booking_token']) ?></p>
                    <?php endif; ?>
                    <p class="mb-2 small text-muted">
                        <i class="bi bi-credit-card-2-front me-1"></i>Payment:
                        <span class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', (string)($booking['payment_status'] ?? 'not_submitted'))) ?></span>
                        <?php if (!empty($booking['payment_transaction_id'])): ?>
                            | Txn <?= htmlspecialchars((string)$booking['payment_transaction_id']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (($booking['status_key'] ?? '') === 'pending'): ?>
                        <a href="user_dashboard_layout.php?page=payment_verification&booking_id=<?= (int)$booking['id'] ?>" data-spa-page="payment_verification" data-no-spinner="true" class="btn btn-sm btn-outline-success mb-2">
                            <i class="bi bi-patch-check me-1"></i>Payment Verification
                        </a>
                    <?php endif; ?>

                    <?php if (($booking['status_key'] ?? '') === 'pending'): ?>
                        <form method="post" data-confirm="Cancel this pending booking request?" class="mt-auto">
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-circle me-1"></i>Cancel Booking
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge text-bg-light mt-auto">Processed</span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
