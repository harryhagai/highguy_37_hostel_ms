<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$holdMinutes = payment_booking_hold_minutes();

$state = [
    'errors' => [],
    'message' => null,
    'booking' => null,
    'proof' => null,
    'control_numbers' => [],
    'hold_minutes' => $holdMinutes,
    'proof_table_ready' => payment_proof_table_ready($pdo),
    'control_table_ready' => payment_control_numbers_table_ready($pdo),
    'requires_submission' => false,
    'seconds_remaining' => 0,
    'has_submitted_transaction' => false,
];

if ($userId <= 0) {
    $state['errors'][] = 'Session expired. Please login again.';
    return $state;
}

if (!user_table_exists($pdo, 'bookings') || !user_column_exists($pdo, 'bookings', 'user_id')) {
    $state['errors'][] = 'Bookings table is not ready.';
    return $state;
}

payment_expire_unpaid_pending_bookings($pdo, $holdMinutes);

$bookingHasStatus = user_column_exists($pdo, 'bookings', 'status');
$bookingHasStart = user_column_exists($pdo, 'bookings', 'start_date');
$bookingHasEnd = user_column_exists($pdo, 'bookings', 'end_date');
$bookingHasBookingDate = user_column_exists($pdo, 'bookings', 'booking_date');
$bookingHasCreatedAt = user_column_exists($pdo, 'bookings', 'created_at');
$bookingHasTotalPrice = user_column_exists($pdo, 'bookings', 'total_price');
$bookingHasMonthlyPrice = user_column_exists($pdo, 'bookings', 'monthly_price');
$bookingHasSemesterName = user_column_exists($pdo, 'bookings', 'semester_name');
$bookingHasSemesterMonths = user_column_exists($pdo, 'bookings', 'semester_months');
$bookingHasRoomId = user_column_exists($pdo, 'bookings', 'room_id');
$bookingHasBedId = user_column_exists($pdo, 'bookings', 'bed_id');
$bookingTokenColumn = user_booking_token_column($pdo);
$orderBy = user_booking_order_column($pdo);

$fetchBooking = static function (PDO $db, int $uid, int $bookingId = 0) use (
    $bookingHasStatus,
    $bookingHasStart,
    $bookingHasEnd,
    $bookingHasBookingDate,
    $bookingHasCreatedAt,
    $bookingHasTotalPrice,
    $bookingHasMonthlyPrice,
    $bookingHasSemesterName,
    $bookingHasSemesterMonths,
    $bookingHasRoomId,
    $bookingHasBedId,
    $bookingTokenColumn,
    $orderBy
): ?array {
    $select = [
        'b.id AS booking_id',
        $bookingHasStatus ? 'b.status' : "'pending' AS status",
        $bookingHasStart ? 'b.start_date' : 'NULL AS start_date',
        $bookingHasEnd ? 'b.end_date' : 'NULL AS end_date',
        $bookingHasBookingDate ? 'b.booking_date' : 'NULL AS booking_date',
        $bookingHasCreatedAt ? 'b.created_at' : 'NULL AS created_at',
        $bookingHasTotalPrice ? 'b.total_price' : '0 AS total_price',
        $bookingHasMonthlyPrice ? 'b.monthly_price' : '0 AS monthly_price',
        $bookingHasSemesterName ? 'b.semester_name' : "'' AS semester_name",
        $bookingHasSemesterMonths ? 'b.semester_months' : '0 AS semester_months',
        $bookingTokenColumn !== '' ? 'b.' . $bookingTokenColumn . ' AS booking_token' : "'' AS booking_token",
    ];

    $from = ' FROM bookings b';
    $where = ['b.user_id = ?'];
    $params = [$uid];

    if (
        $bookingHasBedId
        && user_table_exists($db, 'beds')
        && user_table_exists($db, 'rooms')
        && user_table_exists($db, 'hostels')
    ) {
        $select[] = 'bd.bed_number';
        $select[] = 'r.room_number';
        $select[] = user_column_exists($db, 'rooms', 'room_type') ? 'r.room_type' : "'' AS room_type";
        $select[] = 'h.name AS hostel_name';
        $select[] = 'h.location AS hostel_location';
        $from .= ' JOIN beds bd ON bd.id = b.bed_id';
        $from .= ' JOIN rooms r ON r.id = bd.room_id';
        $from .= ' JOIN hostels h ON h.id = r.hostel_id';
    } elseif (
        $bookingHasRoomId
        && user_table_exists($db, 'rooms')
        && user_table_exists($db, 'hostels')
    ) {
        $select[] = "'' AS bed_number";
        $select[] = 'r.room_number';
        $select[] = user_column_exists($db, 'rooms', 'room_type') ? 'r.room_type' : "'' AS room_type";
        $select[] = 'h.name AS hostel_name';
        $select[] = 'h.location AS hostel_location';
        $from .= ' JOIN rooms r ON r.id = b.room_id';
        $from .= ' JOIN hostels h ON h.id = r.hostel_id';
    } else {
        $select[] = "'' AS bed_number";
        $select[] = "'' AS room_number";
        $select[] = "'' AS room_type";
        $select[] = "'' AS hostel_name";
        $select[] = "'' AS hostel_location";
    }

    if ($bookingId > 0) {
        $where[] = 'b.id = ?';
        $params[] = $bookingId;
        $orderSql = '';
    } else {
        if ($bookingHasStatus) {
            $where[] = "LOWER(COALESCE(b.status, '')) IN ('pending', 'confirmed', 'approved', 'completed', 'cancelled')";
            $orderSql = " ORDER BY CASE
                WHEN LOWER(COALESCE(b.status, '')) = 'pending' THEN 0
                WHEN LOWER(COALESCE(b.status, '')) IN ('confirmed', 'approved') THEN 1
                WHEN LOWER(COALESCE(b.status, '')) = 'completed' THEN 2
                ELSE 3
            END, b.{$orderBy} DESC";
        } else {
            $orderSql = " ORDER BY b.{$orderBy} DESC";
        }
    }

    $sql = 'SELECT ' . implode(', ', $select) . $from . ' WHERE ' . implode(' AND ', $where) . $orderSql . ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    return $booking ?: null;
};

$fetchProof = static function (PDO $db, int $bookingId): ?array {
    if ($bookingId <= 0 || !payment_proof_table_ready($db)) {
        return null;
    }

    $hasSmsText = payment_column_exists($db, 'booking_payment_proofs', 'sms_text');
    $hasStatus = payment_column_exists($db, 'booking_payment_proofs', 'status');
    $hasSubmittedAt = payment_column_exists($db, 'booking_payment_proofs', 'submitted_at');
    $hasVerifiedAt = payment_column_exists($db, 'booking_payment_proofs', 'verified_at');
    $hasAdminNote = payment_column_exists($db, 'booking_payment_proofs', 'admin_note');

    $sql = 'SELECT id, booking_id, user_id, transaction_id, '
        . ($hasSmsText ? 'sms_text' : "'' AS sms_text") . ', '
        . ($hasStatus ? 'status' : "'pending' AS status") . ', '
        . ($hasSubmittedAt ? 'submitted_at' : 'NULL AS submitted_at') . ', '
        . ($hasVerifiedAt ? 'verified_at' : 'NULL AS verified_at') . ', '
        . ($hasAdminNote ? 'admin_note' : "'' AS admin_note")
        . ' FROM booking_payment_proofs WHERE booking_id = ? LIMIT 1';

    $stmt = $db->prepare($sql);
    $stmt->execute([$bookingId]);
    $proof = $stmt->fetch(PDO::FETCH_ASSOC);
    return $proof ?: null;
};

$requestedBookingId = (int)($_GET['booking_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestedBookingId = (int)($_POST['booking_id'] ?? $requestedBookingId);
}

$booking = $fetchBooking($pdo, $userId, $requestedBookingId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'submit_payment_proof') {
    if (!$state['proof_table_ready']) {
        $state['message'] = [
            'type' => 'danger',
            'text' => 'Payment verification table is missing. Run payment migration first.',
        ];
    } elseif (!$booking) {
        $state['message'] = [
            'type' => 'danger',
            'text' => 'Booking not found for payment verification.',
        ];
    } else {
        $bookingStatus = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
        if ($bookingStatus !== 'pending') {
            $state['message'] = [
                'type' => 'warning',
                'text' => 'This booking is already processed. Payment proof update is disabled.',
            ];
        } else {
            $proof = $fetchProof($pdo, (int)$booking['booking_id']);
            $transactionId = strtoupper(trim((string)($_POST['transaction_id'] ?? '')));
            $smsText = trim((string)($_POST['sms_text'] ?? ''));
            $holdFrom = trim((string)($booking['created_at'] ?? ''));
            if ($holdFrom === '' && !empty($booking['booking_date'])) {
                $holdFrom = trim((string)$booking['booking_date']);
            }
            $remaining = payment_seconds_remaining($holdFrom, $holdMinutes);

            if (!payment_has_transaction($proof) && $remaining <= 0) {
                payment_expire_unpaid_pending_bookings($pdo, $holdMinutes);
                $booking = $fetchBooking($pdo, $userId, (int)$booking['booking_id']);
                $state['message'] = [
                    'type' => 'danger',
                    'text' => 'Booking hold time expired before submitting transaction ID.',
                ];
            } elseif ($transactionId === '') {
                $state['message'] = [
                    'type' => 'danger',
                    'text' => 'Transaction ID is required.',
                ];
            } elseif (!preg_match('/^[A-Za-z0-9-]{4,32}$/', $transactionId)) {
                $state['message'] = [
                    'type' => 'danger',
                    'text' => 'Transaction ID format is invalid. Use letters, numbers, and dash only.',
                ];
            } else {
                $hasSmsText = payment_column_exists($pdo, 'booking_payment_proofs', 'sms_text');
                $hasStatus = payment_column_exists($pdo, 'booking_payment_proofs', 'status');
                $hasSubmittedAt = payment_column_exists($pdo, 'booking_payment_proofs', 'submitted_at');
                $hasVerifiedAt = payment_column_exists($pdo, 'booking_payment_proofs', 'verified_at');
                $hasVerifiedBy = payment_column_exists($pdo, 'booking_payment_proofs', 'verified_by');
                $hasAdminNote = payment_column_exists($pdo, 'booking_payment_proofs', 'admin_note');
                $hasUpdatedAt = payment_column_exists($pdo, 'booking_payment_proofs', 'updated_at');
                $hasCreatedAt = payment_column_exists($pdo, 'booking_payment_proofs', 'created_at');

                if ($proof) {
                    $set = ['transaction_id = ?'];
                    $params = [$transactionId];

                    if ($hasSmsText) {
                        $set[] = 'sms_text = ?';
                        $params[] = $smsText;
                    }
                    if ($hasStatus) {
                        $set[] = "status = 'pending'";
                    }
                    if ($hasSubmittedAt) {
                        $set[] = 'submitted_at = NOW()';
                    }
                    if ($hasVerifiedAt) {
                        $set[] = 'verified_at = NULL';
                    }
                    if ($hasVerifiedBy) {
                        $set[] = 'verified_by = NULL';
                    }
                    if ($hasAdminNote) {
                        $set[] = 'admin_note = NULL';
                    }
                    if ($hasUpdatedAt) {
                        $set[] = 'updated_at = NOW()';
                    }

                    $params[] = (int)$proof['id'];
                    $sql = 'UPDATE booking_payment_proofs SET ' . implode(', ', $set) . ' WHERE id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } else {
                    $columns = ['booking_id', 'user_id', 'transaction_id'];
                    $values = [(int)$booking['booking_id'], $userId, $transactionId];
                    $placeholders = ['?', '?', '?'];

                    if ($hasSmsText) {
                        $columns[] = 'sms_text';
                        $values[] = $smsText;
                        $placeholders[] = '?';
                    }
                    if ($hasStatus) {
                        $columns[] = 'status';
                        $values[] = 'pending';
                        $placeholders[] = '?';
                    }
                    if ($hasSubmittedAt) {
                        $columns[] = 'submitted_at';
                        $values[] = date('Y-m-d H:i:s');
                        $placeholders[] = '?';
                    }
                    if ($hasCreatedAt) {
                        $columns[] = 'created_at';
                        $values[] = date('Y-m-d H:i:s');
                        $placeholders[] = '?';
                    }
                    if ($hasUpdatedAt) {
                        $columns[] = 'updated_at';
                        $values[] = date('Y-m-d H:i:s');
                        $placeholders[] = '?';
                    }

                    $sql = 'INSERT INTO booking_payment_proofs (' . implode(', ', $columns) . ')
                            VALUES (' . implode(', ', $placeholders) . ')';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                }

                $state['message'] = [
                    'type' => 'success',
                    'text' => 'Payment proof submitted successfully. Admin will verify and confirm your booking.',
                ];
            }
        }
    }
}

if (!$booking) {
    $state['message'] = $state['message'] ?? [
        'type' => 'info',
        'text' => 'No booking found for payment verification.',
    ];
    $state['control_numbers'] = payment_fetch_control_numbers($pdo, true);
    return $state;
}

$proof = $fetchProof($pdo, (int)$booking['booking_id']);
$statusKey = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
$holdFrom = trim((string)($booking['created_at'] ?? ''));
if ($holdFrom === '' && !empty($booking['booking_date'])) {
    $holdFrom = trim((string)$booking['booking_date']);
}
$hasTransaction = payment_has_transaction($proof);
$secondsRemaining = 0;
$requiresSubmission = false;

if ($statusKey === 'pending' && !$hasTransaction) {
    $secondsRemaining = payment_seconds_remaining($holdFrom, $holdMinutes);
    $requiresSubmission = $secondsRemaining > 0;

    if ($secondsRemaining <= 0) {
        payment_expire_unpaid_pending_bookings($pdo, $holdMinutes);
        $booking = $fetchBooking($pdo, $userId, (int)$booking['booking_id']);
        $statusKey = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
    }
}

if ((int)($_GET['just_booked'] ?? 0) === 1 && !$state['message']) {
    $state['message'] = [
        'type' => 'success',
        'text' => 'Booking submitted. Complete payment proof within ' . $holdMinutes . ' minutes to keep this bed reserved.',
    ];
}

$booking['status_key'] = $statusKey;
$booking['status_label'] = ucfirst($statusKey);
$booking['booking_date_display'] = !empty($booking['booking_date'])
    ? date('d M Y, H:i', strtotime((string)$booking['booking_date']))
    : (!empty($booking['created_at']) ? date('d M Y, H:i', strtotime((string)$booking['created_at'])) : '-');
$booking['stay_period'] = (!empty($booking['start_date']) && !empty($booking['end_date']))
    ? date('d M Y', strtotime((string)$booking['start_date'])) . ' - ' . date('d M Y', strtotime((string)$booking['end_date']))
    : '-';
$booking['total_price'] = (float)($booking['total_price'] ?? 0);
$booking['monthly_price'] = (float)($booking['monthly_price'] ?? 0);
$booking['semester_name'] = trim((string)($booking['semester_name'] ?? ''));
$booking['semester_months'] = (int)($booking['semester_months'] ?? 0);
$booking['booking_token'] = trim((string)($booking['booking_token'] ?? ''));

if (is_array($proof)) {
    $proof['transaction_id'] = trim((string)($proof['transaction_id'] ?? ''));
    $proof['sms_text'] = trim((string)($proof['sms_text'] ?? ''));
    $proof['status'] = strtolower(trim((string)($proof['status'] ?? 'pending')));
    if (!in_array($proof['status'], ['pending', 'verified', 'rejected'], true)) {
        $proof['status'] = 'pending';
    }
    $proof['submitted_at_display'] = !empty($proof['submitted_at'])
        ? date('d M Y, H:i', strtotime((string)$proof['submitted_at']))
        : '-';
    $proof['verified_at_display'] = !empty($proof['verified_at'])
        ? date('d M Y, H:i', strtotime((string)$proof['verified_at']))
        : '-';
}

$state['booking'] = $booking;
$state['proof'] = $proof;
$state['control_numbers'] = payment_fetch_control_numbers($pdo, true);
$state['requires_submission'] = $requiresSubmission;
$state['seconds_remaining'] = $secondsRemaining;
$state['has_submitted_transaction'] = $hasTransaction;

if ($statusKey === 'cancelled' && !$state['message']) {
    $state['message'] = [
        'type' => 'warning',
        'text' => 'This booking has been cancelled. Start a new booking to continue.',
    ];
}

if ($statusKey === 'pending' && $hasTransaction && !$state['message']) {
    $state['message'] = [
        'type' => 'info',
        'text' => 'Transaction proof already submitted. Waiting for admin verification.',
    ];
}

return $state;
