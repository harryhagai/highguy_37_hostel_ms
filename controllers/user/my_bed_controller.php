<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
payment_expire_unpaid_pending_bookings($pdo, payment_booking_hold_minutes());
$bookingLock = user_get_booking_lock_info($pdo, $userId);

$state = [
    'message' => null,
    'booking' => null,
    'can_book' => !$bookingLock['blocked'],
    'booking_lock' => $bookingLock,
];

if ($userId <= 0) {
    $state['message'] = ['type' => 'danger', 'text' => 'Session expired. Please login again.'];
    return $state;
}

if (!user_table_exists($pdo, 'bookings')) {
    $state['message'] = ['type' => 'danger', 'text' => 'Bookings table is missing.'];
    return $state;
}

if (!user_column_exists($pdo, 'bookings', 'user_id')) {
    $state['message'] = ['type' => 'danger', 'text' => 'Bookings schema is missing user_id column.'];
    return $state;
}

$bookingHasStatus = user_column_exists($pdo, 'bookings', 'status');
$bookingHasStart = user_column_exists($pdo, 'bookings', 'start_date');
$bookingHasEnd = user_column_exists($pdo, 'bookings', 'end_date');
$bookingHasBedId = user_column_exists($pdo, 'bookings', 'bed_id');
$bookingHasRoomId = user_column_exists($pdo, 'bookings', 'room_id');
$bookingTokenColumn = user_booking_token_column($pdo);
$roomHasType = user_column_exists($pdo, 'rooms', 'room_type');
$roomHasPrice = user_column_exists($pdo, 'rooms', 'price');
$orderBy = user_booking_order_column($pdo);

$bookingDateExpr = user_column_exists($pdo, 'bookings', 'booking_date')
    ? 'b.booking_date'
    : (user_column_exists($pdo, 'bookings', 'created_at') ? 'b.created_at AS booking_date' : 'NOW() AS booking_date');

$tokenExpr = $bookingTokenColumn !== '' ? 'b.' . $bookingTokenColumn . ' AS booking_token' : "'' AS booking_token";

$statusWhere = $bookingHasStatus
    ? "LOWER(COALESCE(b.status, '')) IN ('pending', 'confirmed', 'approved', 'completed')"
    : '1=1';

$orderExpr = $bookingHasStatus
    ? "CASE WHEN LOWER(COALESCE(b.status, '')) IN ('confirmed', 'approved') THEN 0 WHEN LOWER(COALESCE(b.status, '')) = 'pending' THEN 1 ELSE 2 END, b.{$orderBy} DESC"
    : "b.{$orderBy} DESC";

$booking = null;

if (
    $bookingHasBedId
    && user_table_exists($pdo, 'beds')
    && user_table_exists($pdo, 'rooms')
    && user_table_exists($pdo, 'hostels')
) {
    $sql = "SELECT
                b.id AS booking_id,
                {$bookingDateExpr},
                {$tokenExpr},
                " . ($bookingHasStatus ? 'b.status' : "'pending' AS status") . ',
                ' . ($bookingHasStart ? 'b.start_date' : 'NULL AS start_date') . ',
                ' . ($bookingHasEnd ? 'b.end_date' : 'NULL AS end_date') . ',
                bd.id AS bed_id,
                bd.bed_number,
                r.id AS room_id,
                r.room_number,
                ' . ($roomHasType ? 'r.room_type' : "'' AS room_type") . ',
                ' . ($roomHasPrice ? 'r.price' : '0 AS price') . ",
                h.id AS hostel_id,
                h.name AS hostel_name,
                h.location AS hostel_location
            FROM bookings b
            JOIN beds bd ON bd.id = b.bed_id
            JOIN rooms r ON r.id = bd.room_id
            JOIN hostels h ON h.id = r.hostel_id
            WHERE b.user_id = ?
              AND {$statusWhere}
            ORDER BY {$orderExpr}
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif (
    $bookingHasRoomId
    && user_table_exists($pdo, 'rooms')
    && user_table_exists($pdo, 'hostels')
) {
    $sql = "SELECT
                b.id AS booking_id,
                {$bookingDateExpr},
                {$tokenExpr},
                " . ($bookingHasStatus ? 'b.status' : "'pending' AS status") . ',
                ' . ($bookingHasStart ? 'b.start_date' : 'NULL AS start_date') . ',
                ' . ($bookingHasEnd ? 'b.end_date' : 'NULL AS end_date') . ",
                0 AS bed_id,
                '' AS bed_number,
                r.id AS room_id,
                r.room_number,
                " . ($roomHasType ? 'r.room_type' : "'' AS room_type") . ',
                ' . ($roomHasPrice ? 'r.price' : '0 AS price') . ",
                h.id AS hostel_id,
                h.name AS hostel_name,
                h.location AS hostel_location
            FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            JOIN hostels h ON h.id = r.hostel_id
            WHERE b.user_id = ?
              AND {$statusWhere}
            ORDER BY {$orderExpr}
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} else {
    $state['message'] = [
        'type' => 'warning',
        'text' => 'Bookings schema is not compatible for bed details page.',
    ];
    return $state;
}

if (!$booking) {
    $state['message'] = [
        'type' => 'info',
        'text' => 'No bed booking information found for your account yet.',
    ];
    return $state;
}

$statusKey = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
$stayPeriod = '-';
if (!empty($booking['start_date']) && !empty($booking['end_date'])) {
    $stayPeriod = date('d M Y', strtotime((string)$booking['start_date']))
        . ' - '
        . date('d M Y', strtotime((string)$booking['end_date']));
}

$state['booking'] = [
    'booking_id' => (int)($booking['booking_id'] ?? 0),
    'status_key' => $statusKey,
    'status_label' => ucfirst($statusKey),
    'booking_date_display' => !empty($booking['booking_date'])
        ? date('d M Y, H:i', strtotime((string)$booking['booking_date']))
        : '-',
    'token' => trim((string)($booking['booking_token'] ?? '')),
    'hostel_name' => (string)($booking['hostel_name'] ?? ''),
    'hostel_location' => (string)($booking['hostel_location'] ?? ''),
    'room_number' => (string)($booking['room_number'] ?? '-'),
    'room_type' => (string)($booking['room_type'] ?? ''),
    'bed_number' => trim((string)($booking['bed_number'] ?? '')),
    'price' => (float)($booking['price'] ?? 0),
    'stay_period' => $stayPeriod,
];

if (payment_proof_table_ready($pdo)) {
    $hasStatus = payment_column_exists($pdo, 'booking_payment_proofs', 'status');
    $hasSubmittedAt = payment_column_exists($pdo, 'booking_payment_proofs', 'submitted_at');

    $sql = 'SELECT transaction_id, '
        . ($hasStatus ? 'status' : "'pending' AS status") . ', '
        . ($hasSubmittedAt ? 'submitted_at' : 'NULL AS submitted_at')
        . ' FROM booking_payment_proofs WHERE booking_id = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$booking['booking_id']]);
    $proof = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($proof) {
        $state['booking']['payment_transaction_id'] = trim((string)($proof['transaction_id'] ?? ''));
        $state['booking']['payment_status'] = strtolower(trim((string)($proof['status'] ?? 'pending')));
        $state['booking']['payment_submitted_at'] = !empty($proof['submitted_at'])
            ? date('d M Y, H:i', strtotime((string)$proof['submitted_at']))
            : '-';
    } else {
        $state['booking']['payment_transaction_id'] = '';
        $state['booking']['payment_status'] = 'not_submitted';
        $state['booking']['payment_submitted_at'] = '-';
    }
} else {
    $state['booking']['payment_transaction_id'] = '';
    $state['booking']['payment_status'] = 'not_supported';
    $state['booking']['payment_submitted_at'] = '-';
}

return $state;
