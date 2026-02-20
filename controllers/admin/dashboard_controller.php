<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$dbTableExists = static function (PDO $db, string $table): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
};

$dbColumnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};

$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_hostels = (int)$pdo->query("SELECT COUNT(*) FROM hostels")->fetchColumn();

$hasRoomsAvailableColumn = $dbColumnExists($pdo, 'rooms', 'available');
$hasBedsTable = $dbTableExists($pdo, 'beds');
$hasBookingBed = $dbColumnExists($pdo, 'bookings', 'bed_id');
$hasBookingStart = $dbColumnExists($pdo, 'bookings', 'start_date');
$hasBookingEnd = $dbColumnExists($pdo, 'bookings', 'end_date');

if ($hasRoomsAvailableColumn) {
    $rooms_available = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE available > 0")->fetchColumn();
    $full_rooms = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE available = 0")->fetchColumn();
} elseif ($hasBedsTable && $hasBookingBed && $hasBookingStart && $hasBookingEnd) {
    $rooms_available = (int)$pdo->query("
        SELECT COUNT(DISTINCT b.room_id)
        FROM beds b
        LEFT JOIN bookings bk
            ON bk.bed_id = b.id
           AND bk.status IN ('pending', 'confirmed')
           AND CURDATE() >= bk.start_date
           AND CURDATE() < bk.end_date
        WHERE b.status = 'active'
          AND bk.id IS NULL
    ")->fetchColumn();

    $full_rooms = (int)$pdo->query("
        SELECT COUNT(*)
        FROM (
            SELECT b.room_id,
                   SUM(CASE WHEN bk.id IS NULL THEN 1 ELSE 0 END) AS free_beds
            FROM beds b
            LEFT JOIN bookings bk
                ON bk.bed_id = b.id
               AND bk.status IN ('pending', 'confirmed')
               AND CURDATE() >= bk.start_date
               AND CURDATE() < bk.end_date
            WHERE b.status = 'active'
            GROUP BY b.room_id
            HAVING free_beds = 0
        ) room_state
    ")->fetchColumn();
} else {
    $rooms_available = 0;
    $full_rooms = 0;
}

$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$confirmed_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
$cancelled_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();

$months = [];
$applications = [];
$approved = [];
$rejected = [];
for ($i = 5; $i >= 0; $i--) {
    $monthDate = strtotime("-$i months");
    $months[] = date('M', $monthDate);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE MONTH(booking_date) = ? AND YEAR(booking_date) = ?");
    $stmt->execute([date('n', $monthDate), date('Y', $monthDate)]);
    $applications[] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND MONTH(booking_date) = ? AND YEAR(booking_date) = ?");
    $stmt->execute([date('n', $monthDate), date('Y', $monthDate)]);
    $approved[] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled' AND MONTH(booking_date) = ? AND YEAR(booking_date) = ?");
    $stmt->execute([date('n', $monthDate), date('Y', $monthDate)]);
    $rejected[] = (int)$stmt->fetchColumn();
}
$trend_data_mode = 'live';
if (array_sum($applications) === 0 && array_sum($approved) === 0 && array_sum($rejected) === 0) {
    $applications = [14, 18, 17, 22, 25, 29];
    $approved = [8, 11, 10, 14, 16, 19];
    $rejected = [1, 2, 2, 3, 2, 3];
    $trend_data_mode = 'demo';
}

$total_rooms_count = max(0, $rooms_available + $full_rooms);
$occupancy_rate = $total_rooms_count > 0 ? (int)round(($full_rooms / $total_rooms_count) * 100) : 0;

return [
    'total_users' => $total_users,
    'total_hostels' => $total_hostels,
    'rooms_available' => $rooms_available,
    'full_rooms' => $full_rooms,
    'pending_count' => $pending_count,
    'confirmed_count' => $confirmed_count,
    'cancelled_count' => $cancelled_count,
    'months' => $months,
    'applications' => $applications,
    'approved' => $approved,
    'rejected' => $rejected,
    'trend_data_mode' => $trend_data_mode,
    'total_rooms_count' => $total_rooms_count,
    'occupancy_rate' => $occupancy_rate
];
