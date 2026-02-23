<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/payment_verification_controller.php';
$errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
$message = $state['message'] ?? null;
$booking = $state['booking'] ?? null;
$proof = $state['proof'] ?? null;
$controlNumbers = is_array($state['control_numbers'] ?? null) ? $state['control_numbers'] : [];
$holdMinutes = (int)($state['hold_minutes'] ?? 30);
$requiresSubmission = (bool)($state['requires_submission'] ?? false);
$secondsRemaining = (int)($state['seconds_remaining'] ?? 0);
$hasSubmittedTransaction = (bool)($state['has_submitted_transaction'] ?? false);
$proofTableReady = (bool)($state['proof_table_ready'] ?? false);
?>

<div class="container-fluid px-0 user-payment-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
            <?= implode('<br>', array_map(static fn($err) => htmlspecialchars((string)$err), $errors)) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> mb-3">
            <?= htmlspecialchars((string)$message['text']) ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><i class="bi bi-credit-card-2-front me-2"></i>Payment Verification</h4>
                <p class="text-muted mb-0">Submit transaction ID after paying hostel fees to reserve your bed.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="user_dashboard_layout.php?page=my_bed" data-spa-page="my_bed" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-house-check me-1"></i>My Bed
                </a>
                <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-check me-1"></i>My Bookings
                </a>
            </div>
        </div>
    </div>

    <?php if (!$booking): ?>
        <div class="dashboard-card my-room-empty-state text-center">
            <div class="mb-2"><i class="bi bi-wallet2 my-room-empty-icon"></i></div>
            <h5 class="mb-2">No booking found</h5>
            <p class="text-muted mb-3">Create a bed booking first, then submit payment proof from here.</p>
            <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-outline-success">
                <i class="bi bi-calendar-plus me-1"></i>Book Bed
            </a>
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
                <h6 class="mb-0">Room <?= htmlspecialchars((string)$booking['room_number']) ?><?= !empty($booking['bed_number']) ? ' | Bed ' . htmlspecialchars((string)$booking['bed_number']) : '' ?></h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Amount</p>
                <h6 class="mb-0">TSh <?= number_format((float)$booking['total_price'], 2) ?></h6>
            </article>
        </div>

        <div class="dashboard-card mb-3">
            <div class="row g-3">
                <div class="col-lg-7">
                    <h6 class="fw-semibold mb-2">Booking Summary</h6>
                    <div class="my-room-meta-box">
                        <p class="mb-1"><strong>Hostel:</strong> <?= htmlspecialchars((string)$booking['hostel_name']) ?></p>
                        <p class="mb-1"><strong>Location:</strong> <?= htmlspecialchars((string)$booking['hostel_location']) ?></p>
                        <p class="mb-1"><strong>Room:</strong> <?= htmlspecialchars((string)$booking['room_number']) ?><?= !empty($booking['bed_number']) ? ' | Bed ' . htmlspecialchars((string)$booking['bed_number']) : '' ?></p>
                        <p class="mb-1"><strong>Booking Date:</strong> <?= htmlspecialchars((string)$booking['booking_date_display']) ?></p>
                        <p class="mb-1"><strong>Stay Period:</strong> <?= htmlspecialchars((string)$booking['stay_period']) ?></p>
                        <?php if (!empty($booking['booking_token'])): ?>
                            <p class="mb-0"><strong>Application Token:</strong> <?= htmlspecialchars((string)$booking['booking_token']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <h6 class="fw-semibold mb-2">Payment Hold</h6>
                    <div class="my-room-meta-box">
                        <?php if ($booking['status_key'] === 'pending' && !$hasSubmittedTransaction): ?>
                            <p class="mb-1"><strong>Reserve Window:</strong> <?= (int)$holdMinutes ?> minutes</p>
                            <p class="mb-1"><strong>Time Left:</strong> <span id="paymentCountdown" data-seconds="<?= (int)$secondsRemaining ?>">--:--</span></p>
                            <p class="mb-0 text-muted small">If time runs out before submitting transaction ID, booking will be cancelled automatically.</p>
                        <?php elseif ($booking['status_key'] === 'pending' && $hasSubmittedTransaction): ?>
                            <p class="mb-1"><strong>Transaction ID:</strong> <?= htmlspecialchars((string)($proof['transaction_id'] ?? '-')) ?></p>
                            <p class="mb-1"><strong>Submitted:</strong> <?= htmlspecialchars((string)($proof['submitted_at_display'] ?? '-')) ?></p>
                            <p class="mb-0 text-muted small">Countdown stopped. Waiting admin approval.</p>
                        <?php elseif ($booking['status_key'] === 'confirmed'): ?>
                            <p class="mb-0 text-success"><strong>Payment verified:</strong> Booking confirmed.</p>
                        <?php else: ?>
                            <p class="mb-0 text-muted">This booking is no longer pending payment verification.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-card mb-3">
            <h5 class="mb-2"><i class="bi bi-wallet2 me-2"></i>Payment Instructions</h5>
            <p class="text-muted mb-3">Pay <strong>TSh <?= number_format((float)$booking['total_price'], 2) ?></strong> using one of the control numbers below, then submit your transaction ID.</p>

            <?php if (empty($controlNumbers)): ?>
                <div class="alert alert-warning mb-0">
                    Payment control numbers are not configured yet. Contact admin.
                </div>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($controlNumbers as $network): ?>
                        <?php
                        $iconPath = trim((string)($network['network_icon'] ?? ''));
                        $iconUrl = $iconPath !== '' ? user_to_public_asset_path($iconPath, '../assets/images/logo.png') : '../assets/images/logo.png';
                        ?>
                        <div class="col-md-6">
                            <div class="my-room-meta-box h-100">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <img src="<?= htmlspecialchars($iconUrl) ?>" alt="<?= htmlspecialchars((string)$network['network_name']) ?>" width="30" height="30" class="rounded-circle">
                                    <strong><?= htmlspecialchars((string)$network['network_name']) ?></strong>
                                </div>
                                <p class="mb-1"><strong>Control Number:</strong> <?= htmlspecialchars((string)$network['control_number']) ?></p>
                                <p class="mb-1"><strong>Company:</strong> <?= htmlspecialchars((string)($network['company_name'] ?? '-')) ?></p>
                                <p class="mb-0 text-muted small"><?= htmlspecialchars((string)($network['info'] ?? '')) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-card">
            <h5 class="mb-2"><i class="bi bi-patch-check me-2"></i>Submit Payment Proof</h5>
            <?php if (!$proofTableReady): ?>
                <div class="alert alert-danger mb-0">Payment verification feature is not ready. Missing `booking_payment_proofs` table.</div>
            <?php elseif ($booking['status_key'] !== 'pending'): ?>
                <div class="alert alert-light border mb-0">Submission is disabled because booking status is <strong><?= htmlspecialchars((string)$booking['status_label']) ?></strong>.</div>
            <?php elseif ($hasSubmittedTransaction): ?>
                <div class="alert alert-success mb-0">
                    Transaction proof already submitted (<strong><?= htmlspecialchars((string)($proof['transaction_id'] ?? '-')) ?></strong>).
                    Waiting for admin verification.
                </div>
            <?php elseif (!$requiresSubmission && !$hasSubmittedTransaction): ?>
                <div class="alert alert-warning mb-0">Booking hold time expired before payment proof submission.</div>
            <?php else: ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="submit_payment_proof">
                    <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">
                    <div class="col-md-6">
                        <label class="form-label">Transaction ID *</label>
                        <input
                            type="text"
                            name="transaction_id"
                            class="form-control"
                            placeholder="Mfano: SCL012345"
                            value="<?= htmlspecialchars((string)($proof['transaction_id'] ?? '')) ?>"
                            maxlength="32"
                            required>
                        <div class="form-text">Use code kutoka SMS ya malipo (letters/numbers).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Status</label>
                        <input type="text" class="form-control" readonly value="<?= $hasSubmittedTransaction ? 'Submitted - Waiting Admin' : 'Not submitted yet' ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">SMS Message (Optional)</label>
                        <textarea name="sms_text" class="form-control" rows="4" placeholder="Paste SMS ya malipo hapa"><?= htmlspecialchars((string)($proof['sms_text'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-success">
                            <i class="bi bi-send-check me-1"></i>Submit Proof
                        </button>
                        <span class="text-muted small align-self-center">Admin atahakiki malipo na kukupa status ndani ya saa 1.</span>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var countdown = document.getElementById('paymentCountdown');
    if (!countdown) return;

    var totalSeconds = parseInt(countdown.getAttribute('data-seconds') || '0', 10);
    if (!Number.isFinite(totalSeconds) || totalSeconds < 0) {
        totalSeconds = 0;
    }

    function formatTime(value) {
        var mins = Math.floor(value / 60);
        var secs = value % 60;
        return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }

    function render() {
        countdown.textContent = formatTime(totalSeconds);
    }

    render();
    if (totalSeconds <= 0) return;

    var timer = window.setInterval(function () {
        totalSeconds -= 1;
        if (totalSeconds <= 0) {
            totalSeconds = 0;
            render();
            window.clearInterval(timer);
            window.location.reload();
            return;
        }
        render();
    }, 1000);
})();
</script>
