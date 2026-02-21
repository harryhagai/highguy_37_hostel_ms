<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$userId = $_SESSION['user_id'];

// Handle delete booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking_id'])) {
    $bookingId = intval($_POST['delete_booking_id']);

    // Get room_id before deleting (to make the room available again)
    $stmt = $pdo->prepare("SELECT room_id FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$bookingId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $roomId = $row['room_id'];

        // Delete the booking
        $delStmt = $pdo->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?");
        $delStmt->execute([$bookingId, $userId]);

        // Make the room available again
        $updateRoom = $pdo->prepare("UPDATE rooms SET available = 1 WHERE id = ?");
        $updateRoom->execute([$roomId]);

        $message = [
            'type' => 'success',
            'text' => 'Booking deleted successfully.'
        ];
    } else {
        $message = [
            'type' => 'danger',
            'text' => 'Booking not found or unauthorized action.'
        ];
    }
}

// Fetch all bookings for this user
$stmt = $pdo->prepare("SELECT b.id, b.booking_date, b.status, r.room_number, r.room_type, h.name AS hostel_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN hostels h ON r.hostel_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC");
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../assets/css/user-my-bookings.css">

<h2 class="mb-4 fw-bold section-title-aqua">
    <i class="bi bi-calendar-check"></i> My Bookings
</h2>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($message['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message['text']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($bookings)): ?>
    <div class="alert alert-info">You have not made any bookings yet.</div>
<?php else: ?>
    <div class="booking-tile-list mb-5">
        <?php foreach ($bookings as $b): ?>
            <div class="booking-tile">
                <div class="tile-header d-flex align-items-center mb-2">
                    <div class="tile-icon me-2">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <div class="tile-hostel"><?= htmlspecialchars($b['hostel_name']) ?></div>
                        <div class="tile-room">Room <?= htmlspecialchars($b['room_number']) ?> (<?= htmlspecialchars($b['room_type']) ?>)</div>
                    </div>
                </div>
                <div class="tile-body mb-2">
                    <div><i class="bi bi-clock"></i> <span class="tile-date"><?= date('d M Y, H:i', strtotime($b['booking_date'])) ?></span></div>
                    <div>
                        <span class="badge tile-status
                            <?php
                                if ($b['status'] === 'pending') echo 'bg-warning text-dark';
                                elseif ($b['status'] === 'approved') echo 'bg-success';
                                elseif ($b['status'] === 'cancelled') echo 'bg-danger';
                                else echo 'bg-secondary';
                            ?>">
                            <?= htmlspecialchars(ucfirst($b['status'])) ?>
                        </span>
                    </div>
                </div>
                <div class="tile-actions text-end">
                    <form method="post" data-confirm="Are you sure you want to delete this booking?" class="inline-form">
                        <input type="hidden" name="delete_booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger tile-delete-btn">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


