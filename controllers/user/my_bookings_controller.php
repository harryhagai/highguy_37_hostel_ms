<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

$message = null;
$bookings = [];

if ($userId <= 0) {
    return [
        'message' => ['type' => 'danger', 'text' => 'Session expired. Please login again.'],
        'bookings' => [],
        'stats' => ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0],
    ];
}

if (!user_table_exists($pdo, 'bookings')) {
    return [
        'message' => ['type' => 'danger', 'text' => 'Bookings table is missing.'],
        'bookings' => [],
        'stats' => ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0],
    ];
}

$bookingHasRoomId = user_column_exists($pdo, 'bookings', 'room_id');
$bookingHasBedId = user_column_exists($pdo, 'bookings', 'bed_id');
$bookingHasStatus = user_column_exists($pdo, 'bookings', 'status');
$bookingHasStart = user_column_exists($pdo, 'bookings', 'start_date');
$bookingHasEnd = user_column_exists($pdo, 'bookings', 'end_date');
$bookingHasBookingDate = user_column_exists($pdo, 'bookings', 'booking_date');
$roomsHaveAvailable = user_column_exists($pdo, 'rooms', 'available');
$orderBy = user_booking_order_column($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'delete_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($bookingId <= 0) {
        $message = ['type' => 'danger', 'text' => 'Invalid booking ID.'];
    } else {
        try {
            $pdo->beginTransaction();

            $roomId = null;
            if ($bookingHasRoomId) {
                $stmt = $pdo->prepare('SELECT room_id FROM bookings WHERE id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([$bookingId, $userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('Booking not found or unauthorized action.');
                }
                $roomId = (int)($row['room_id'] ?? 0);
            } else {
                $stmt = $pdo->prepare('SELECT id FROM bookings WHERE id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([$bookingId, $userId]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException('Booking not found or unauthorized action.');
                }
            }

            $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ? AND user_id = ?');
            $stmt->execute([$bookingId, $userId]);

            if ($stmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to delete booking.');
            }

            if ($roomId !== null && $roomId > 0 && $roomsHaveAvailable) {
                $stmt = $pdo->prepare('UPDATE rooms SET available = COALESCE(available, 0) + 1 WHERE id = ?');
                $stmt->execute([$roomId]);
            }

            $pdo->commit();
            $message = ['type' => 'success', 'text' => 'Booking deleted successfully.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }
}

$selectFields = [
    'b.id',
    $bookingHasStatus ? 'b.status' : "'pending' AS status",
    $bookingHasBookingDate ? 'b.booking_date' : (user_column_exists($pdo, 'bookings', 'created_at') ? 'b.created_at AS booking_date' : 'NOW() AS booking_date'),
    $bookingHasStart ? 'b.start_date' : 'NULL AS start_date',
    $bookingHasEnd ? 'b.end_date' : 'NULL AS end_date',
];

if ($bookingHasRoomId && user_table_exists($pdo, 'rooms') && user_table_exists($pdo, 'hostels')) {
    $sql = 'SELECT ' . implode(', ', $selectFields) . ",
            r.room_number,
            r.room_type,
            h.name AS hostel_name,
            '' AS bed_number
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN hostels h ON h.id = r.hostel_id
        WHERE b.user_id = ?
        ORDER BY b." . $orderBy . ' DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($bookingHasBedId && user_table_exists($pdo, 'beds') && user_table_exists($pdo, 'rooms') && user_table_exists($pdo, 'hostels')) {
    $sql = 'SELECT ' . implode(', ', $selectFields) . ",
            r.room_number,
            r.room_type,
            h.name AS hostel_name,
            bd.bed_number
        FROM bookings b
        JOIN beds bd ON bd.id = b.bed_id
        JOIN rooms r ON r.id = bd.room_id
        JOIN hostels h ON h.id = r.hostel_id
        WHERE b.user_id = ?
        ORDER BY b." . $orderBy . ' DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = $message ?: ['type' => 'danger', 'text' => 'Bookings schema is not compatible with this page.'];
}

$stats = [
    'total' => count($bookings),
    'pending' => 0,
    'confirmed' => 0,
    'cancelled' => 0,
    'completed' => 0,
];

foreach ($bookings as &$booking) {
    $booking['status_key'] = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
    $booking['status_label'] = ucfirst($booking['status_key']);
    $booking['booking_date_display'] = !empty($booking['booking_date'])
        ? date('d M Y, H:i', strtotime((string)$booking['booking_date']))
        : '-';

    if (!empty($booking['start_date']) && !empty($booking['end_date'])) {
        $booking['stay_period'] = date('d M Y', strtotime((string)$booking['start_date']))
            . ' - '
            . date('d M Y', strtotime((string)$booking['end_date']));
    } else {
        $booking['stay_period'] = '';
    }

    if (array_key_exists($booking['status_key'], $stats)) {
        $stats[$booking['status_key']]++;
    }
}
unset($booking);

return [
    'message' => $message,
    'bookings' => $bookings,
    'stats' => $stats,
];
