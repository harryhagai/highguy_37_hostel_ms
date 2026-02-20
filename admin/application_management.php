<?php
require_once __DIR__ . '/../config/db_connection.php';

function amColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $errors[] = 'Invalid application ID.';
    } else {
        if ($action === 'approve_application') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $success = $stmt->rowCount() ? 'Application approved successfully.' : 'Application is already processed.';
        }

        if ($action === 'reject_application') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $success = $stmt->rowCount() ? 'Application rejected successfully.' : 'Application is already processed.';
        }
    }
}

$hasBookingRoom = amColumnExists($pdo, 'bookings', 'room_id');
$hasBookingBed = amColumnExists($pdo, 'bookings', 'bed_id');

if ($hasBookingRoom) {
    $stmt = $pdo->query("
        SELECT
            b.id,
            u.username,
            u.email,
            r.room_number,
            '' AS bed_number,
            h.name AS hostel_name,
            b.status,
            b.booking_date,
            b.created_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN rooms r ON b.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        ORDER BY b.created_at DESC
    ");
} elseif ($hasBookingBed) {
    $stmt = $pdo->query("
        SELECT
            b.id,
            u.username,
            u.email,
            r.room_number,
            bd.bed_number,
            h.name AS hostel_name,
            b.status,
            b.booking_date,
            b.created_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN beds bd ON b.bed_id = bd.id
        JOIN rooms r ON bd.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        ORDER BY b.created_at DESC
    ");
} else {
    $stmt = null;
    $errors[] = 'Bookings schema is not compatible: missing room_id/bed_id.';
}

$applications = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>

<div class="container-fluid px-0">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Application Management</h4>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Hostel</th>
                        <th>Room</th>
                        <th>Bed</th>
                        <th>Status</th>
                        <th>Booking Date</th>
                        <th>Applied At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $app): ?>
                    <?php $json = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td><?= (int)$app['id'] ?></td>
                        <td><?= htmlspecialchars($app['username']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?= htmlspecialchars($app['hostel_name']) ?></td>
                        <td><?= htmlspecialchars($app['room_number']) ?></td>
                        <td><?= htmlspecialchars($app['bed_number'] ?: '-') ?></td>
                        <td>
                            <?php if ($app['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($app['status'] === 'confirmed'): ?>
                                <span class="badge bg-success">Confirmed</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($app['booking_date']) ?></td>
                        <td><?= htmlspecialchars($app['created_at']) ?></td>
                        <td class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-info text-white view-app-btn" data-app="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewApplicationModal">
                                <i class="bi bi-eye"></i> View
                            </button>

                            <?php if ($app['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-success btn-sm action-app-btn" data-action="approve_application" data-app="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#applicationActionModal">
                                    Approve
                                </button>
                                <button type="button" class="btn btn-danger btn-sm action-app-btn" data-action="reject_application" data-app="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#applicationActionModal">
                                    Reject
                                </button>
                            <?php else: ?>
                                <span class="text-muted">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="viewApplicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-data"></i> Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>ID:</strong> <span id="viewAppId">-</span></p>
                <p class="mb-2"><strong>User:</strong> <span id="viewAppUser">-</span></p>
                <p class="mb-2"><strong>Email:</strong> <span id="viewAppEmail">-</span></p>
                <p class="mb-2"><strong>Hostel:</strong> <span id="viewAppHostel">-</span></p>
                <p class="mb-2"><strong>Room:</strong> <span id="viewAppRoom">-</span></p>
                <p class="mb-2"><strong>Bed:</strong> <span id="viewAppBed">-</span></p>
                <p class="mb-2"><strong>Status:</strong> <span id="viewAppStatus">-</span></p>
                <p class="mb-2"><strong>Booking Date:</strong> <span id="viewAppBookingDate">-</span></p>
                <p class="mb-0"><strong>Applied At:</strong> <span id="viewAppCreated">-</span></p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="applicationActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="applicationActionForm">
                <input type="hidden" name="action" id="applicationActionType">
                <input type="hidden" name="id" id="applicationActionId">
                <div class="modal-header">
                    <h5 class="modal-title" id="applicationActionTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="applicationActionMessage" class="mb-0">Are you sure?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="applicationActionBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="../assets/js/admin-application-management.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">


