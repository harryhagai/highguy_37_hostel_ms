<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

$stats = [
    'total_bookings' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'cancelled' => 0,
    'completed' => 0,
    'available_rooms' => user_count_available_rooms($pdo),
];

$recentBookings = [];

if ($userId > 0 && user_table_exists($pdo, 'bookings') && user_column_exists($pdo, 'bookings', 'user_id')) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $stats['total_bookings'] = (int)$stmt->fetchColumn();

    if (user_column_exists($pdo, 'bookings', 'status')) {
        $stmt = $pdo->prepare(
            "SELECT LOWER(COALESCE(status, 'pending')) AS status_key, COUNT(*) AS total_count
             FROM bookings
             WHERE user_id = ?
             GROUP BY status_key"
        );
        $stmt->execute([$userId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = user_normalize_booking_status((string)($row['status_key'] ?? 'pending'));
            if (array_key_exists($status, $stats)) {
                $stats[$status] += (int)($row['total_count'] ?? 0);
            }
        }
    }

    $hasBookingRoom = user_column_exists($pdo, 'bookings', 'room_id') && user_table_exists($pdo, 'rooms');
    $hasBookingBed = user_column_exists($pdo, 'bookings', 'bed_id') && user_table_exists($pdo, 'beds');
    $hasStartDate = user_column_exists($pdo, 'bookings', 'start_date');
    $hasEndDate = user_column_exists($pdo, 'bookings', 'end_date');
    $hasStatus = user_column_exists($pdo, 'bookings', 'status');
    $orderBy = user_booking_order_column($pdo);

    $bookingDateField = user_column_exists($pdo, 'bookings', 'booking_date')
        ? 'b.booking_date'
        : (user_column_exists($pdo, 'bookings', 'created_at') ? 'b.created_at AS booking_date' : 'NOW() AS booking_date');

    $selectFields = [
        'b.id',
        $hasStatus ? 'b.status' : "'pending' AS status",
        $bookingDateField,
        $hasStartDate ? 'b.start_date' : 'NULL AS start_date',
        $hasEndDate ? 'b.end_date' : 'NULL AS end_date',
    ];

    if ($hasBookingRoom) {
        $sql = 'SELECT ' . implode(', ', $selectFields) . ",
                r.room_number,
                h.name AS hostel_name,
                '' AS bed_number
            FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            JOIN hostels h ON h.id = r.hostel_id
            WHERE b.user_id = ?
            ORDER BY b." . $orderBy . ' DESC
            LIMIT 5';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($hasBookingBed && user_table_exists($pdo, 'rooms') && user_table_exists($pdo, 'hostels')) {
        $sql = 'SELECT ' . implode(', ', $selectFields) . ",
                r.room_number,
                h.name AS hostel_name,
                bd.bed_number
            FROM bookings b
            JOIN beds bd ON bd.id = b.bed_id
            JOIN rooms r ON r.id = bd.room_id
            JOIN hostels h ON h.id = r.hostel_id
            WHERE b.user_id = ?
            ORDER BY b." . $orderBy . ' DESC
            LIMIT 5';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

foreach ($recentBookings as &$booking) {
    $booking['status_key'] = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
    $booking['status_label'] = ucfirst($booking['status_key']);
    $booking['booking_date_display'] = !empty($booking['booking_date'])
        ? date('d M Y', strtotime((string)$booking['booking_date']))
        : '-';

    if (!empty($booking['start_date']) && !empty($booking['end_date'])) {
        $booking['stay_period'] = date('d M Y', strtotime((string)$booking['start_date']))
            . ' - '
            . date('d M Y', strtotime((string)$booking['end_date']));
    } else {
        $booking['stay_period'] = '';
    }
}
unset($booking);

$announcement = user_get_latest_notice($pdo);

$suggestedHostels = user_fetch_hostel_cards($pdo, true);
foreach ($suggestedHostels as &$suggestedHostel) {
    $suggestedHostel['gender_label'] = user_gender_label((string)($suggestedHostel['gender'] ?? 'all'));
}
unset($suggestedHostel);
usort($suggestedHostels, static function (array $a, array $b): int {
    $freeA = (int)($a['free_rooms'] ?? 0);
    $freeB = (int)($b['free_rooms'] ?? 0);
    if ($freeA === $freeB) {
        return strcmp((string)($b['created_date'] ?? ''), (string)($a['created_date'] ?? ''));
    }
    return $freeB <=> $freeA;
});
$suggestedHostels = array_slice($suggestedHostels, 0, 4);

return [
    'stats' => $stats,
    'recent_bookings' => $recentBookings,
    'announcement' => $announcement,
    'suggested_hostels' => $suggestedHostels,
];
