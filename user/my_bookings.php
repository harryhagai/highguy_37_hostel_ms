<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

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

<h2 class="mb-4 fw-bold" style="color:#11998e;">
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
                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this booking?');" style="display:inline;">
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

<style>
.booking-tile-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 28px;
    margin-bottom: 2rem;
}
.booking-tile {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(28, 202, 216, 0.09);
    padding: 1.5rem 1.3rem 1.2rem 1.3rem;
    display: flex;
    flex-direction: column;
    min-height: 180px;
    position: relative;
    transition: box-shadow 0.22s, transform 0.18s;
}
.booking-tile:hover {
    box-shadow: 0 8px 36px rgba(28, 202, 216, 0.15);
    transform: translateY(-2px) scale(1.015);
}
.tile-header {
    font-size: 1.08rem;
    font-weight: 600;
    color: #11998e;
}
.tile-icon {
    font-size: 2.1rem;
    color: #1ccad8;
}
.tile-hostel {
    font-size: 1.11rem;
    font-weight: 600;
    color: #11998e;
}
.tile-room {
    font-size: 1.01rem;
    color: #233142;
}
.tile-body {
    margin-top: 0.2rem;
    margin-bottom: 0.3rem;
    font-size: 1.02rem;
    color: #233142;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tile-date {
    font-size: 0.99rem;
    color: #666;
}
.tile-status {
    font-size: 0.98rem;
    padding: 0.5em 1em;
    border-radius: 8px;
    font-weight: 500;
    letter-spacing: 0.5px;
}
.tile-actions {
    margin-top: auto;
}
.tile-delete-btn {
    border-radius: 8px;
    font-weight: 500;
    background: linear-gradient(90deg, #ff5858 70%, #f09819 100%);
    border: none;
    color: #fff;
    transition: background 0.19s;
}
.tile-delete-btn:hover {
    background: linear-gradient(90deg, #f09819 70%, #ff5858 100%);
    color: #fff;
}
@media (max-width: 900px) {
    .booking-tile-list {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 600px) {
    .booking-tile-list {
        grid-template-columns: 1fr;
    }
    .booking-tile {
        min-height: 150px;
    }
}
</style>
