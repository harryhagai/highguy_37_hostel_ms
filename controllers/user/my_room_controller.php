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
    'room' => null,
    'can_book' => !$bookingLock['blocked'],
    'booking_lock' => $bookingLock,
    'residents' => [],
    'stats' => [
        'occupants' => 0,
        'roommates' => 0,
    ],
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
$roomHasType = user_column_exists($pdo, 'rooms', 'room_type');
$roomHasPrice = user_column_exists($pdo, 'rooms', 'price');
$hostelHasGender = user_column_exists($pdo, 'hostels', 'gender');
$usersHaveEmail = user_column_exists($pdo, 'users', 'email');
$usersHavePhone = user_column_exists($pdo, 'users', 'phone');
$orderBy = user_booking_order_column($pdo);

$statusCondition = $bookingHasStatus
    ? "LOWER(COALESCE(b.status, '')) IN ('pending', 'confirmed', 'approved')"
    : '1=1';

$bookingDateExpr = user_column_exists($pdo, 'bookings', 'booking_date')
    ? 'b.booking_date'
    : (user_column_exists($pdo, 'bookings', 'created_at') ? 'b.created_at AS booking_date' : 'NOW() AS booking_date');

$currentBooking = null;
$mode = '';

if (
    $bookingHasBedId
    && user_table_exists($pdo, 'beds')
    && user_table_exists($pdo, 'rooms')
    && user_table_exists($pdo, 'hostels')
) {
    $mode = 'bed';

    $selectFields = [
        'b.id AS booking_id',
        'h.id AS hostel_id',
        'h.name AS hostel_name',
        'h.location AS hostel_location',
        $hostelHasGender ? 'h.gender AS hostel_gender' : "'all' AS hostel_gender",
        'r.id AS room_id',
        'r.room_number',
        $roomHasType ? 'r.room_type' : "'' AS room_type",
        $roomHasPrice ? 'r.price' : '0 AS price',
        'bd.id AS bed_id',
        'bd.bed_number',
        $bookingHasStatus ? 'b.status' : "'pending' AS status",
        $bookingHasStart ? 'b.start_date' : 'NULL AS start_date',
        $bookingHasEnd ? 'b.end_date' : 'NULL AS end_date',
        $bookingDateExpr,
    ];

    $baseSql = 'SELECT ' . implode(', ', $selectFields) . "
        FROM bookings b
        JOIN beds bd ON bd.id = b.bed_id
        JOIN rooms r ON r.id = bd.room_id
        JOIN hostels h ON h.id = r.hostel_id
        WHERE b.user_id = ?
          AND {$statusCondition}";

    if ($bookingHasStart && $bookingHasEnd) {
        $stmt = $pdo->prepare($baseSql . "
            AND CURDATE() >= b.start_date
            AND CURDATE() < b.end_date
            ORDER BY b.{$orderBy} DESC
            LIMIT 1");
        $stmt->execute([$userId]);
        $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$currentBooking) {
        $stmt = $pdo->prepare($baseSql . " ORDER BY b.{$orderBy} DESC LIMIT 1");
        $stmt->execute([$userId]);
        $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} elseif (
    $bookingHasRoomId
    && user_table_exists($pdo, 'rooms')
    && user_table_exists($pdo, 'hostels')
) {
    $mode = 'room';

    $selectFields = [
        'b.id AS booking_id',
        'h.id AS hostel_id',
        'h.name AS hostel_name',
        'h.location AS hostel_location',
        $hostelHasGender ? 'h.gender AS hostel_gender' : "'all' AS hostel_gender",
        'r.id AS room_id',
        'r.room_number',
        $roomHasType ? 'r.room_type' : "'' AS room_type",
        $roomHasPrice ? 'r.price' : '0 AS price',
        'NULL AS bed_id',
        "'' AS bed_number",
        $bookingHasStatus ? 'b.status' : "'pending' AS status",
        $bookingHasStart ? 'b.start_date' : 'NULL AS start_date',
        $bookingHasEnd ? 'b.end_date' : 'NULL AS end_date',
        $bookingDateExpr,
    ];

    $baseSql = 'SELECT ' . implode(', ', $selectFields) . "
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN hostels h ON h.id = r.hostel_id
        WHERE b.user_id = ?
          AND {$statusCondition}";

    if ($bookingHasStart && $bookingHasEnd) {
        $stmt = $pdo->prepare($baseSql . "
            AND CURDATE() >= b.start_date
            AND CURDATE() < b.end_date
            ORDER BY b.{$orderBy} DESC
            LIMIT 1");
        $stmt->execute([$userId]);
        $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$currentBooking) {
        $stmt = $pdo->prepare($baseSql . " ORDER BY b.{$orderBy} DESC LIMIT 1");
        $stmt->execute([$userId]);
        $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} else {
    $state['message'] = [
        'type' => 'warning',
        'text' => 'Room assignment is not available yet. Booking schema is missing room/bed references.',
    ];
    return $state;
}

if (!$currentBooking) {
    $state['message'] = [
        'type' => 'info',
        'text' => 'You do not have an active room assignment yet. Book a bed first to see your roommates.',
    ];
    return $state;
}

$currentStatus = user_normalize_booking_status((string)($currentBooking['status'] ?? 'pending'));
if ($currentStatus !== 'confirmed') {
    $state['message'] = [
        'type' => 'info',
        'text' => 'Your booking is pending confirmation. Room details will appear after your booking is confirmed.',
    ];
    return $state;
}

if (!user_table_exists($pdo, 'users')) {
    $state['message'] = ['type' => 'danger', 'text' => 'Users table is missing.'];
    return $state;
}

$residentSelect = [
    'u.id AS user_id',
    'u.username',
    $usersHaveEmail ? 'u.email' : "'' AS email",
    $usersHavePhone ? 'u.phone' : "'' AS phone",
    $bookingHasStatus ? 'b.status' : "'pending' AS status",
    $bookingHasStart ? 'b.start_date' : 'NULL AS start_date',
    $bookingHasEnd ? 'b.end_date' : 'NULL AS end_date',
    $bookingDateExpr,
];

$residentParams = [];
$residentWhere = [$statusCondition];

if ($mode === 'bed') {
    $residentSelect[] = 'bd.bed_number';
    $residentSql = 'SELECT ' . implode(', ', $residentSelect) . "
        FROM bookings b
        JOIN beds bd ON bd.id = b.bed_id
        JOIN users u ON u.id = b.user_id
        WHERE bd.room_id = ?";
    $residentParams[] = (int)$currentBooking['room_id'];

    if ($bookingHasStart && $bookingHasEnd) {
        $bookingStart = trim((string)($currentBooking['start_date'] ?? ''));
        $bookingEnd = trim((string)($currentBooking['end_date'] ?? ''));
        if ($bookingStart !== '' && $bookingEnd !== '') {
            $residentWhere[] = 'b.start_date < ? AND b.end_date > ?';
            $residentParams[] = $bookingEnd;
            $residentParams[] = $bookingStart;
        } else {
            $residentWhere[] = 'CURDATE() >= b.start_date AND CURDATE() < b.end_date';
        }
    }

    $residentSql .= ' AND ' . implode(' AND ', $residentWhere);
    $residentSql .= " ORDER BY CASE WHEN u.id = {$userId} THEN 0 ELSE 1 END, bd.bed_number ASC, u.username ASC";
} else {
    $residentSelect[] = "'' AS bed_number";
    $residentSql = 'SELECT ' . implode(', ', $residentSelect) . "
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        WHERE b.room_id = ?";
    $residentParams[] = (int)$currentBooking['room_id'];

    if ($bookingHasStart && $bookingHasEnd) {
        $bookingStart = trim((string)($currentBooking['start_date'] ?? ''));
        $bookingEnd = trim((string)($currentBooking['end_date'] ?? ''));
        if ($bookingStart !== '' && $bookingEnd !== '') {
            $residentWhere[] = 'b.start_date < ? AND b.end_date > ?';
            $residentParams[] = $bookingEnd;
            $residentParams[] = $bookingStart;
        } else {
            $residentWhere[] = 'CURDATE() >= b.start_date AND CURDATE() < b.end_date';
        }
    }

    $residentSql .= ' AND ' . implode(' AND ', $residentWhere);
    $residentSql .= " ORDER BY CASE WHEN u.id = {$userId} THEN 0 ELSE 1 END, u.username ASC";
}

$stmt = $pdo->prepare($residentSql);
$stmt->execute($residentParams);
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($residents as &$resident) {
    $resident['is_me'] = (int)($resident['user_id'] ?? 0) === $userId;
    $resident['status_key'] = user_normalize_booking_status((string)($resident['status'] ?? 'pending'));
    $resident['status_label'] = ucfirst($resident['status_key']);
    $resident['email'] = trim((string)($resident['email'] ?? ''));
    $resident['phone'] = trim((string)($resident['phone'] ?? ''));
    $resident['bed_number'] = trim((string)($resident['bed_number'] ?? ''));

    if (!empty($resident['start_date']) && !empty($resident['end_date'])) {
        $resident['stay_period'] = date('d M Y', strtotime((string)$resident['start_date']))
            . ' - '
            . date('d M Y', strtotime((string)$resident['end_date']));
    } else {
        $resident['stay_period'] = '-';
    }
}
unset($resident);

$state['room'] = [
    'mode' => $mode,
    'hostel_id' => (int)($currentBooking['hostel_id'] ?? 0),
    'hostel_name' => (string)($currentBooking['hostel_name'] ?? ''),
    'hostel_location' => (string)($currentBooking['hostel_location'] ?? ''),
    'hostel_gender_label' => user_gender_label((string)($currentBooking['hostel_gender'] ?? 'all')),
    'room_id' => (int)($currentBooking['room_id'] ?? 0),
    'room_number' => (string)($currentBooking['room_number'] ?? '-'),
    'room_type' => (string)($currentBooking['room_type'] ?? ''),
    'price' => (float)($currentBooking['price'] ?? 0),
    'bed_number' => trim((string)($currentBooking['bed_number'] ?? '')),
    'status_key' => user_normalize_booking_status((string)($currentBooking['status'] ?? 'pending')),
    'status_label' => ucfirst(user_normalize_booking_status((string)($currentBooking['status'] ?? 'pending'))),
    'booking_date_display' => !empty($currentBooking['booking_date'])
        ? date('d M Y, H:i', strtotime((string)$currentBooking['booking_date']))
        : '-',
    'stay_period' => (!empty($currentBooking['start_date']) && !empty($currentBooking['end_date']))
        ? date('d M Y', strtotime((string)$currentBooking['start_date'])) . ' - ' . date('d M Y', strtotime((string)$currentBooking['end_date']))
        : '-',
];

$state['residents'] = $residents;
$state['stats']['occupants'] = count($residents);
$state['stats']['roommates'] = max(0, count($residents) - 1);

return $state;
