<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/application_management_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$applications = $state['applications'];
$proofTableReady = (bool)($state['proof_table_ready'] ?? false);

$stats = [
    'total_applications' => count($applications),
    'pending' => 0,
    'confirmed' => 0,
    'cancelled' => 0,
    'today' => 0,
    'payment_waiting' => 0,
];

$today = date('Y-m-d');
foreach ($applications as $appStat) {
    $statusStat = strtolower(trim((string)($appStat['status'] ?? 'pending')));
    if ($statusStat === 'approved') {
        $statusStat = 'confirmed';
    }
    if (!in_array($statusStat, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
        $statusStat = 'pending';
    }

    if ($statusStat === 'pending') {
        $stats['pending']++;
    } elseif ($statusStat === 'confirmed' || $statusStat === 'completed') {
        $stats['confirmed']++;
    } elseif ($statusStat === 'cancelled') {
        $stats['cancelled']++;
    }

    $createdDate = !empty($appStat['created_at']) ? date('Y-m-d', strtotime((string)$appStat['created_at'])) : '';
    if ($createdDate === $today) {
        $stats['today']++;
    }

    $paymentStatus = strtolower(trim((string)($appStat['payment_status'] ?? 'not_submitted')));
    if ($statusStat === 'pending' && in_array($paymentStatus, ['pending', 'verified'], true)) {
        $stats['payment_waiting']++;
    }
}
?>
<div class="container-fluid px-0 users-page applications-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!$proofTableReady): ?>
        <div class="alert alert-warning">
            Payment proof table is not configured. Approving pending applications is blocked until migration is applied.
        </div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Applications</p>
            <h5 class="mb-0"><?= (int)$stats['total_applications'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Pending</p>
            <h5 class="mb-0"><?= (int)$stats['pending'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Approved</p>
            <h5 class="mb-0"><?= (int)$stats['confirmed'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Rejected</p>
            <h5 class="mb-0"><?= (int)$stats['cancelled'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Submitted Today</p>
            <h5 class="mb-0"><?= (int)$stats['today'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Payment Waiting</p>
            <h5 class="mb-0"><?= (int)$stats['payment_waiting'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Applications</h4>
                <p class="text-muted mb-0">Review booking requests and verify payment proof before approval.</p>
            </div>
        </div>

        <div class="users-filters mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-xl-4 col-lg-6">
                    <div class="users-field-icon users-field-search">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            class="form-control form-control-sm"
                            id="applicationsSearchInput"
                            placeholder="Search token, user, hostel, room, transaction">
                        <button type="button" id="clearApplicationsSearch" class="users-field-clear" aria-label="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-funnel"></i>
                        <select class="form-select form-select-sm" id="applicationsStatusFilter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Approved</option>
                            <option value="cancelled">Rejected</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-wallet2"></i>
                        <select class="form-select form-select-sm" id="applicationsPaymentFilter">
                            <option value="">All Payments</option>
                            <option value="not_submitted">Not Submitted</option>
                            <option value="pending">Submitted</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-calendar3"></i>
                        <select class="form-select form-select-sm" id="applicationsDateFilter">
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">Last 7 Days</option>
                            <option value="month">Last 30 Days</option>
                            <option value="older">Older</option>
                        </select>
                    </div>
                </div>
                <div class="col-xl-2 d-flex justify-content-xl-end align-items-center">
                    <span class="users-result-count text-muted small" id="applicationsResultCount">
                        <?= (int)count($applications) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0" id="applicationsTable">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Assignment</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $app): ?>
                    <?php
                    $json = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
                    $statusKey = strtolower(trim((string)($app['status'] ?? 'pending')));
                    if ($statusKey === 'approved') {
                        $statusKey = 'confirmed';
                    }
                    if (!in_array($statusKey, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
                        $statusKey = 'pending';
                    }
                    $statusLabel = $statusKey === 'completed' ? 'Completed' : ucfirst($statusKey);
                    $statusClass = $statusKey === 'pending'
                        ? 'user-status-maintenance'
                        : ($statusKey === 'confirmed' || $statusKey === 'completed' ? 'user-status-active' : 'user-status-suspended');

                    $paymentStatus = strtolower(trim((string)($app['payment_status'] ?? 'not_submitted')));
                    if (!in_array($paymentStatus, ['not_submitted', 'pending', 'verified', 'rejected'], true)) {
                        $paymentStatus = 'not_submitted';
                    }
                    $paymentLabel = $paymentStatus === 'not_submitted'
                        ? 'Not Submitted'
                        : ($paymentStatus === 'pending' ? 'Submitted' : ucfirst($paymentStatus));
                    $paymentClass = $paymentStatus === 'verified'
                        ? 'user-status-active'
                        : ($paymentStatus === 'pending' ? 'user-status-maintenance' : ($paymentStatus === 'rejected' ? 'user-status-suspended' : 'text-bg-light'));

                    $paymentTxn = trim((string)($app['payment_transaction_id'] ?? ''));
                    $canApprove = $statusKey === 'pending' && $proofTableReady && $paymentTxn !== '';

                    $bookingDateRaw = trim((string)($app['booking_date'] ?? ''));
                    $createdAtRaw = trim((string)($app['created_at'] ?? ''));
                    $bookingDateDisplay = $bookingDateRaw !== '' ? date('d M Y', strtotime($bookingDateRaw)) : '-';
                    $createdAtDisplay = $createdAtRaw !== '' ? date('d M Y H:i', strtotime($createdAtRaw)) : '-';
                    $createdDate = $createdAtRaw !== '' ? date('Y-m-d', strtotime($createdAtRaw)) : '';
                    $searchText = strtolower(trim(
                        (string)($app['application_token'] ?? '') . ' ' .
                        (string)($app['username'] ?? '') . ' ' .
                        (string)($app['phone'] ?? '') . ' ' .
                        (string)($app['hostel_name'] ?? '') . ' ' .
                        (string)($app['room_number'] ?? '') . ' ' .
                        (string)($app['bed_number'] ?? '') . ' ' .
                        (string)$paymentTxn . ' ' .
                        (string)($app['payment_sms_text'] ?? '') . ' ' .
                        $statusLabel . ' ' . $paymentLabel . ' ' . $bookingDateDisplay . ' ' . $createdAtDisplay
                    ));
                    ?>
                    <tr
                        class="application-row"
                        data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                        data-status="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"
                        data-payment-status="<?= htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') ?>"
                        data-created-date="<?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td>
                            <div class="user-identity">
                                <div class="user-avatar-shell">
                                    <span class="user-avatar-fallback"><i class="bi bi-clipboard-check"></i></span>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)($app['username'] ?? '-')) ?></div>
                                    <small class="text-muted d-block">Phone: <?= htmlspecialchars((string)($app['phone'] ?? '-')) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars((string)($app['hostel_name'] ?? '-')) ?></div>
                            <small class="text-muted">
                                Room <?= htmlspecialchars((string)($app['room_number'] ?? '-')) ?> | Bed <?= htmlspecialchars((string)($app['bed_number'] ?? '-')) ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge user-status-badge <?= $paymentClass ?>"><?= htmlspecialchars($paymentLabel) ?></span>
                            <div class="small text-muted mt-1">Txn: <?= $paymentTxn !== '' ? htmlspecialchars($paymentTxn) : '-' ?></div>
                        </td>
                        <td>
                            <span class="badge user-status-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </td>
                        <td>
                            <div><strong>Booking:</strong> <?= htmlspecialchars($bookingDateDisplay) ?></div>
                            <small class="text-muted"><strong>Applied:</strong> <?= htmlspecialchars($createdAtDisplay) ?></small>
                        </td>
                        <td class="d-flex gap-1 flex-nowrap actions-cell application-actions-cell">
                            <button type="button" class="btn btn-sm btn-outline-secondary view-app-btn" data-app="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewApplicationModal">
                                <i class="bi bi-eye me-1"></i>View
                            </button>

                            <?php if ($statusKey === 'pending'): ?>
                                <form method="post" data-confirm="Approve application for <?= htmlspecialchars((string)($app['username'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?>?" class="d-inline">
                                    <input type="hidden" name="action" value="approve_application">
                                    <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" <?= $canApprove ? '' : 'disabled' ?> title="<?= $canApprove ? 'Approve booking' : 'Transaction ID is required before approval' ?>">
                                        <i class="bi bi-check-circle me-1"></i>Approve
                                    </button>
                                </form>
                                <form method="post" data-confirm="Reject application for <?= htmlspecialchars((string)($app['username'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?>?" class="d-inline">
                                    <input type="hidden" name="action" value="reject_application">
                                    <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-x-circle me-1"></i>Reject
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge text-bg-light">Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="applicationsNoResultsRow" class="<?= empty($applications) ? '' : 'd-none' ?>">
                        <td colspan="6" class="text-center text-muted py-4">
                            <?= empty($applications) ? 'No applications found.' : 'No applications match your filters.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="users-lazy-bar mt-3">
            <span class="small text-muted" id="applicationsLoadedInfo">Showing 0 of 0</span>
            <span class="small text-muted">Scroll down to load more</span>
        </div>
        <div id="applicationsLazySentinel" class="users-lazy-sentinel" aria-hidden="true"></div>
    </div>
</div>

<div class="modal fade users-modal" id="viewApplicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-data"></i> Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Application Token</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppToken">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppStatus">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppUser">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppPhone">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hostel</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppHostel">-</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Room</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppRoom">-</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bed</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppBed">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Transaction ID</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppPaymentTxn">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Status</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppPaymentStatus">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Submitted At</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppPaymentSubmitted">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Verified At</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppPaymentVerified">-</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">SMS Proof</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppPaymentSms" style="min-height: 80px; white-space: pre-wrap;">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Booking Date</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppBookingDate">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Applied At</label>
                        <div class="form-control form-control-sm bg-light" id="viewAppCreated">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form method="post" id="viewModalApproveForm" class="d-inline d-none" data-confirm="Approve application?">
                    <input type="hidden" name="action" value="approve_application">
                    <input type="hidden" name="id" id="viewModalApproveId" value="">
                    <button type="submit" class="btn btn-outline-success" id="viewModalApproveBtn">
                        <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                </form>
                <form method="post" id="viewModalRejectForm" class="d-inline d-none" data-confirm="Reject application?">
                    <input type="hidden" name="action" value="reject_application">
                    <input type="hidden" name="id" id="viewModalRejectId" value="">
                    <button type="submit" class="btn btn-outline-danger" id="viewModalRejectBtn">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin-application-management.js"></script>
