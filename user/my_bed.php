<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/my_bed_controller.php';
$message = $state['message'];
$booking = $state['booking'];
$canBook = (bool)($state['can_book'] ?? true);
$bookingLock = is_array($state['booking_lock'] ?? null) ? $state['booking_lock'] : ['blocked' => false, 'message' => ''];
?>
<div class="container-fluid px-0 user-my-bed-page">
    <div class="dashboard-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><i class="bi bi-house-check me-2"></i>My Bed</h4>
                <p class="text-muted mb-0">View your latest booked bed information and booking status.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($canBook): ?>
                    <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-calendar-plus me-1"></i>Book Bed
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-lock me-1"></i>Booking Locked
                    </button>
                <?php endif; ?>
                <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-check me-1"></i>My Bookings
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($bookingLock['blocked'])): ?>
        <div class="alert alert-warning mb-3">
            <i class="bi bi-lock me-1"></i><?= htmlspecialchars((string)($bookingLock['message'] ?? 'Booking is currently locked for your account.')) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> mb-3">
            <?= htmlspecialchars((string)$message['text']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$booking): ?>
        <div class="dashboard-card my-room-empty-state text-center">
            <div class="mb-2"><i class="bi bi-door-open my-room-empty-icon"></i></div>
            <h5 class="mb-2">No bed booking yet</h5>
            <p class="text-muted mb-3">Once you place a booking request, your bed information will appear here.</p>
            <?php if ($canBook): ?>
                <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-outline-success">
                    <i class="bi bi-search me-1"></i>Find Bed
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="users-quick-stats mb-3">
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Status</p>
                <h6 class="mb-0"><?= htmlspecialchars((string)$booking['status_label']) ?></h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Hostel</p>
                <h6 class="mb-0"><?= htmlspecialchars((string)$booking['hostel_name']) ?></h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Room / Bed</p>
                <h6 class="mb-0">
                    Room <?= htmlspecialchars((string)$booking['room_number']) ?>
                    <?php if (!empty($booking['bed_number'])): ?>
                        - Bed <?= htmlspecialchars((string)$booking['bed_number']) ?>
                    <?php endif; ?>
                </h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Token</p>
                <h6 class="mb-0"><?= $booking['token'] !== '' ? htmlspecialchars((string)$booking['token']) : '-' ?></h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Payment</p>
                <h6 class="mb-0 text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', (string)($booking['payment_status'] ?? 'not_submitted'))) ?></h6>
            </article>
        </div>

        <div class="dashboard-card">
            <div class="row g-3">
                <div class="col-lg-7">
                    <h5 class="mb-1"><?= htmlspecialchars((string)$booking['hostel_name']) ?></h5>
                    <p class="text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)$booking['hostel_location']) ?></p>
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <span class="badge text-bg-light">Status: <?= htmlspecialchars((string)$booking['status_label']) ?></span>
                        <?php if (!empty($booking['room_type'])): ?>
                            <span class="badge text-bg-info">Type: <?= htmlspecialchars((string)$booking['room_type']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($booking['bed_number'])): ?>
                            <span class="badge text-bg-success">Bed: <?= htmlspecialchars((string)$booking['bed_number']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($booking['payment_transaction_id'])): ?>
                            <span class="badge text-bg-warning">Txn: <?= htmlspecialchars((string)$booking['payment_transaction_id']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-0"><strong>Stay Period:</strong> <?= htmlspecialchars((string)$booking['stay_period']) ?></p>
                </div>
                <div class="col-lg-5">
                    <div class="my-room-meta-box">
                        <p class="mb-1"><strong>Room:</strong> <?= htmlspecialchars((string)$booking['room_number']) ?></p>
                        <p class="mb-1"><strong>Price:</strong> TSh <?= number_format((float)$booking['price'], 2) ?></p>
                        <p class="mb-1"><strong>Booked At:</strong> <?= htmlspecialchars((string)$booking['booking_date_display']) ?></p>
                        <p class="mb-1"><strong>Payment Submitted:</strong> <?= htmlspecialchars((string)($booking['payment_submitted_at'] ?? '-')) ?></p>
                        <p class="mb-0"><strong>Token:</strong> <?= $booking['token'] !== '' ? htmlspecialchars((string)$booking['token']) : '-' ?></p>
                    </div>
                    <?php if (($booking['status_key'] ?? '') === 'pending'): ?>
                        <a href="user_dashboard_layout.php?page=payment_verification&booking_id=<?= (int)($booking['booking_id'] ?? 0) ?>" data-spa-page="payment_verification" data-no-spinner="true" class="btn btn-sm btn-outline-success mt-2">
                            <i class="bi bi-credit-card-2-front me-1"></i>Payment Verification
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
