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
$hostelId = isset($_GET['hostel_id']) ? intval($_GET['hostel_id']) : 0;

if ($hostelId <= 0) {
    echo '<div class="alert alert-danger">Invalid hostel ID.</div>';
    return;
}

// Get hostel info
$hostelStmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostelStmt->execute([$hostelId]);
$hostel = $hostelStmt->fetch();

if (!$hostel) {
    echo '<div class="alert alert-danger">Hostel not found.</div>';
    return;
}

// Check if user already has a booking
$checkBookingStmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ?");
$checkBookingStmt->execute([$userId]);
$existingBooking = $checkBookingStmt->fetch();

// Handle booking form submission
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id']) && !$existingBooking) {
    $roomId = intval($_POST['room_id']);
    $phone = trim($_POST['phone'] ?? '');

    // Check current occupancy
    $roomCheckStmt = $pdo->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $roomCheckStmt->execute([$roomId]);
    $roomData = $roomCheckStmt->fetch();

    if (!$roomData) {
        $message = [
            'type' => 'danger',
            'text' => 'Room not found.'
        ];
    } else {
        $capacity = $roomData['capacity'];
        $occupancyStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ?");
        $occupancyStmt->execute([$roomId]);
        $currentOccupancy = $occupancyStmt->fetchColumn();

        if ($currentOccupancy >= $capacity) {
            $message = [
                'type' => 'warning',
                'text' => 'This room is already full.'
            ];
        } else {
            // Insert booking
            $insertBookingStmt = $pdo->prepare("INSERT INTO bookings (user_id, room_id, status, booking_date) VALUES (?, ?, 'pending', NOW())");
            $insertBookingStmt->execute([$userId, $roomId]);
            $message = [
                'type' => 'success',
                'text' => 'Room successfully booked! 🎉'
            ];
            // Refresh existingBooking so user can't book again
            $checkBookingStmt->execute([$userId]);
            $existingBooking = $checkBookingStmt->fetch();
        }
    }
}

// Get all rooms in this hostel, with their booking counts
$roomsStmt = $pdo->prepare("SELECT * FROM rooms WHERE hostel_id = ?");
$roomsStmt->execute([$hostelId]);
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

// For each room, get current occupancy
$roomOccupancy = [];
if ($rooms) {
    $roomIds = array_column($rooms, 'id');
    if (!empty($roomIds)) {
        $inQuery = implode(',', array_fill(0, count($roomIds), '?'));
        $occupancyStmt = $pdo->prepare(
            "SELECT room_id, COUNT(*) as count FROM bookings WHERE room_id IN ($inQuery) GROUP BY room_id"
        );
        $occupancyStmt->execute($roomIds);
        foreach ($occupancyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roomOccupancy[$row['room_id']] = $row['count'];
        }
    }
}
?>

<link rel="stylesheet" href="../assets/css/user-book-room.css">

<h2 class="mb-4 fw-bold section-title-aqua">
    <i class="bi bi-building"></i> Hostel: <?= htmlspecialchars($hostel['name']) ?>
</h2>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message['text']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<a href="user_dashboard_layout.php?page=view_hostels" class="btn btn-secondary mb-4">
    <i class="bi bi-arrow-left-circle"></i> Back to Hostels
</a>

<?php if (!$rooms): ?>
    <div class="alert alert-info">No rooms found for this hostel.</div>
<?php else: ?>
    <div class="room-card-list mb-5">
        <?php foreach ($rooms as $room): 
            $roomId = $room['id'];
            $capacity = $room['capacity'];
            $currentOccupancy = $roomOccupancy[$roomId] ?? 0;
            $spotsLeft = $capacity - $currentOccupancy;
            $isFull = $spotsLeft <= 0;
        ?>
            <div class="room-card">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Room <?= htmlspecialchars($room['room_number']) ?></h5>
                    <p class="card-text mb-1">Type: <?= htmlspecialchars($room['room_type']) ?></p>
                    <p class="card-text mb-1">Capacity: <?= intval($room['capacity']) ?> 
                        <span class="text-muted">(<?= $currentOccupancy ?> booked, <?= max(0, $spotsLeft) ?> left)</span>
                    </p>
                    <p class="card-text card-price mb-3">$<?= number_format($room['price'], 2) ?></p>
                    <p class="card-availability mb-3">
                        <?php if ($isFull): ?>
                            <span class="badge bg-danger">Full</span>
                        <?php else: ?>
                            <span class="badge bg-success"><?= $spotsLeft ?> spot<?= $spotsLeft > 1 ? 's' : '' ?> left</span>
                        <?php endif; ?>
                    </p>
                    <div class="mt-auto">
                        <?php if ($existingBooking): ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="bi bi-lock"></i> Already Booked a Room
                            </button>
                        <?php elseif ($isFull): ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="bi bi-x-circle"></i> Room Full
                            </button>
                        <?php else: ?>
                            <button type="button" 
                                    class="btn book-room-btn w-100" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#bookRoomModal" 
                                    data-room-id="<?= $roomId ?>"
                                    data-room-number="<?= htmlspecialchars($room['room_number']) ?>"
                                    data-room-price="<?= number_format($room['price'], 2) ?>">
                                <i class="bi bi-calendar-plus"></i> Book This Room
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Booking Modal -->
<div class="modal fade" id="bookRoomModal" tabindex="-1" aria-labelledby="bookRoomModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="bookingForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bookRoomModalLabel">
            <i class="bi bi-calendar-plus"></i> Book Room
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="room_id" id="modalRoomId" />
          <div class="mb-3">
              <label for="modalRoomNumber" class="form-label">Room Number</label>
              <input type="text" class="form-control" id="modalRoomNumber" readonly />
          </div>
          <div class="mb-3">
              <label for="modalRoomPrice" class="form-label">Price</label>
              <input type="text" class="form-control" id="modalRoomPrice" readonly />
          </div>
          <div class="mb-3">
              <label for="phone" class="form-label">Your Phone Number <span class="text-danger">*</span></label>
              <input type="tel" class="form-control" name="phone" id="phone" required placeholder="Enter your phone number" />
          </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Confirm Booking</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>


<script src="../assets/js/user-book-room.js"></script>




