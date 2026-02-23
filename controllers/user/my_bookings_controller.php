<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
payment_expire_unpaid_pending_bookings($pdo, payment_booking_hold_minutes());
$bookingLock = user_get_booking_lock_info($pdo, $userId);

$message = null;
$bookings = [];

if ($userId <= 0) {
    return [
        'message' => ['type' => 'danger', 'text' => 'Session expired. Please login again.'],
        'bookings' => [],
        'can_book' => false,
        'booking_lock' => $bookingLock,
        'stats' => ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0],
    ];
}

if (!user_table_exists($pdo, 'bookings')) {
    return [
        'message' => ['type' => 'danger', 'text' => 'Bookings table is missing.'],
        'bookings' => [],
        'can_book' => false,
        'booking_lock' => $bookingLock,
        'stats' => ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0],
    ];
}

$bookingHasRoomId = user_column_exists($pdo, 'bookings', 'room_id');
$bookingHasBedId = user_column_exists($pdo, 'bookings', 'bed_id');
$bookingHasStatus = user_column_exists($pdo, 'bookings', 'status');
$bookingHasStart = user_column_exists($pdo, 'bookings', 'start_date');
$bookingHasEnd = user_column_exists($pdo, 'bookings', 'end_date');
$bookingHasBookingDate = user_column_exists($pdo, 'bookings', 'booking_date');
$bookingTokenColumn = user_booking_token_column($pdo);
$roomsHaveAvailable = user_column_exists($pdo, 'rooms', 'available');
$orderBy = user_booking_order_column($pdo);
$proofTableReady = payment_proof_table_ready($pdo);
$proofHasStatus = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'status');
$proofHasAdminNote = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'admin_note');
$proofHasUpdatedAt = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'updated_at');
$proofHasVerifiedAt = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'verified_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($bookingId <= 0) {
        $message = ['type' => 'danger', 'text' => 'Invalid booking ID.'];
    } elseif (!$bookingHasStatus) {
        $message = ['type' => 'danger', 'text' => 'Booking cancellation is not supported by current schema.'];
    } else {
        try {
            $pdo->beginTransaction();

            $select = ['id', 'status'];
            if ($bookingHasRoomId) {
                $select[] = 'room_id';
            }
            if ($bookingHasBedId) {
                $select[] = 'bed_id';
            }
            $stmt = $pdo->prepare(
                'SELECT ' . implode(', ', $select) . ' FROM bookings WHERE id = ? AND user_id = ? LIMIT 1'
            );
            $stmt->execute([$bookingId, $userId]);
            $bookingRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bookingRow) {
                throw new RuntimeException('Booking not found or unauthorized action.');
            }

            $statusKey = user_normalize_booking_status((string)($bookingRow['status'] ?? 'pending'));
            if ($statusKey !== 'pending') {
                throw new RuntimeException('Only pending bookings can be cancelled.');
            }

            $stmt = $pdo->prepare(
                "UPDATE bookings
                 SET status = 'cancelled'
                 WHERE id = ? AND user_id = ? AND LOWER(COALESCE(status, '')) = 'pending'"
            );
            $stmt->execute([$bookingId, $userId]);

            if ($stmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to cancel booking.');
            }

            $roomId = (int)($bookingRow['room_id'] ?? 0);
            $bedId = (int)($bookingRow['bed_id'] ?? 0);
            if ($roomId !== null && $roomId > 0 && $roomsHaveAvailable) {
                if ($bedId <= 0) {
                    $stmt = $pdo->prepare('UPDATE rooms SET available = COALESCE(available, 0) + 1 WHERE id = ?');
                    $stmt->execute([$roomId]);
                }
            }

            if ($proofTableReady) {
                $set = [];
                if ($proofHasStatus) {
                    $set[] = "status = 'rejected'";
                }
                if ($proofHasAdminNote) {
                    $set[] = "admin_note = 'Cancelled by student'";
                }
                if ($proofHasVerifiedAt) {
                    $set[] = 'verified_at = NOW()';
                }
                if ($proofHasUpdatedAt) {
                    $set[] = 'updated_at = NOW()';
                }
                if (!empty($set)) {
                    $proofSql = 'UPDATE booking_payment_proofs SET ' . implode(', ', $set) . ' WHERE booking_id = ?';
                    $proofStmt = $pdo->prepare($proofSql);
                    $proofStmt->execute([$bookingId]);
                }
            }

            $pdo->commit();
            $message = ['type' => 'success', 'text' => 'Booking cancelled successfully.'];
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
    $bookingTokenColumn !== '' ? 'b.' . $bookingTokenColumn . ' AS booking_token' : "'' AS booking_token",
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

    $booking['booking_token'] = trim((string)($booking['booking_token'] ?? ''));

    if (array_key_exists($booking['status_key'], $stats)) {
        $stats[$booking['status_key']]++;
    }
}
unset($booking);

if (!empty($bookings) && $proofTableReady) {
    $bookingIds = array_values(array_filter(array_map(static function (array $item): int {
        return (int)($item['id'] ?? 0);
    }, $bookings), static fn(int $id): bool => $id > 0));

    if (!empty($bookingIds)) {
        $hasStatus = payment_column_exists($pdo, 'booking_payment_proofs', 'status');
        $hasSubmittedAt = payment_column_exists($pdo, 'booking_payment_proofs', 'submitted_at');

        $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
        $sql = 'SELECT booking_id, transaction_id, '
            . ($hasStatus ? 'status' : "'pending' AS status") . ', '
            . ($hasSubmittedAt ? 'submitted_at' : 'NULL AS submitted_at')
            . " FROM booking_payment_proofs WHERE booking_id IN ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bookingIds);
        $proofRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $proofMap = [];
        foreach ($proofRows as $row) {
            $proofMap[(int)($row['booking_id'] ?? 0)] = $row;
        }

        foreach ($bookings as &$booking) {
            $proof = $proofMap[(int)($booking['id'] ?? 0)] ?? null;
            if ($proof) {
                $booking['payment_transaction_id'] = trim((string)($proof['transaction_id'] ?? ''));
                $booking['payment_status'] = strtolower(trim((string)($proof['status'] ?? 'pending')));
                $booking['payment_submitted_at'] = !empty($proof['submitted_at'])
                    ? date('d M Y, H:i', strtotime((string)$proof['submitted_at']))
                    : '-';
            } else {
                $booking['payment_transaction_id'] = '';
                $booking['payment_status'] = 'not_submitted';
                $booking['payment_submitted_at'] = '-';
            }
        }
        unset($booking);
    }
} else {
    foreach ($bookings as &$booking) {
        $booking['payment_transaction_id'] = '';
        $booking['payment_status'] = 'not_submitted';
        $booking['payment_submitted_at'] = '-';
    }
    unset($booking);
}

return [
    'message' => $message,
    'bookings' => $bookings,
    'can_book' => !$bookingLock['blocked'],
    'booking_lock' => $bookingLock,
    'stats' => $stats,
];
