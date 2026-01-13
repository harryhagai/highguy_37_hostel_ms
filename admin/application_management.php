<?php
require_once __DIR__ . '/../config/db_connection.php';

// Approve application
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?")->execute([$id]);
    header("Location: admin_dashboard_layout.php?page=application_management");
    exit;
}

// Reject application
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$id]);
    header("Location: admin_dashboard_layout.php?page=application_management");
    exit;
}

// Fetch all applications/bookings
$stmt = $pdo->query("
    SELECT b.id, u.username, u.email, r.room_number, h.name AS hostel_name, b.status, b.booking_date, b.created_at
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    JOIN hostels h ON r.hostel_id = h.id
    ORDER BY b.created_at DESC
");
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid px-0">
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
                        <th>Status</th>
                        <th>Booking Date</th>
                        <th>Applied At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= htmlspecialchars($app['username']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?= htmlspecialchars($app['hostel_name']) ?></td>
                        <td><?= htmlspecialchars($app['room_number']) ?></td>
                        <td>
                            <?php if ($app['status'] == 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($app['status'] == 'confirmed'): ?>
                                <span class="badge bg-success">Confirmed</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $app['booking_date'] ?></td>
                        <td><?= $app['created_at'] ?></td>
                        <td>
                            <?php if ($app['status'] == 'pending'): ?>
                                <a href="admin_dashboard_layout.php?page=application_management&approve=<?= $app['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this application?')">Approve</a>
                                <a href="admin_dashboard_layout.php?page=application_management&reject=<?= $app['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this application?')">Reject</a>
                            <?php else: ?>
                                <span class="text-muted">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">