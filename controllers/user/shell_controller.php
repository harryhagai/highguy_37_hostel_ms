<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

$profile = [
    'username' => 'Student',
    'email' => '',
    'profile_photo' => '../assets/images/prof.jpg',
];

if ($userId > 0 && user_table_exists($pdo, 'users')) {
    $select = ['username'];
    if (user_column_exists($pdo, 'users', 'email')) {
        $select[] = 'email';
    }
    if (user_column_exists($pdo, 'users', 'profile_photo')) {
        $select[] = 'profile_photo';
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $profile['username'] = (string)($row['username'] ?? 'Student');
        $profile['email'] = (string)($row['email'] ?? '');
        $profile['profile_photo'] = user_to_public_asset_path((string)($row['profile_photo'] ?? ''), '../assets/images/prof.jpg');
    }
}

$displayUsername = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)$profile['username'])) ?? '');
$displayUsername = $displayUsername === '' ? 'Student' : ucwords(strtolower($displayUsername));

$myBookings = 0;
$pendingBookings = 0;
if ($userId > 0 && user_table_exists($pdo, 'bookings') && user_column_exists($pdo, 'bookings', 'user_id')) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $myBookings = (int)$stmt->fetchColumn();

    if (user_column_exists($pdo, 'bookings', 'status')) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM bookings
             WHERE user_id = ?
               AND LOWER(COALESCE(status, '')) = 'pending'"
        );
        $stmt->execute([$userId]);
        $pendingBookings = (int)$stmt->fetchColumn();
    }
}

$availableRooms = user_count_available_rooms($pdo);
$hostelCount = user_table_exists($pdo, 'hostels') ? (int)$pdo->query('SELECT COUNT(*) FROM hostels')->fetchColumn() : 0;
$latestNotice = user_get_latest_notice($pdo);

return [
    'user_id' => $userId,
    'username' => (string)$profile['username'],
    'display_username' => $displayUsername,
    'email' => (string)$profile['email'],
    'profile_pic' => (string)$profile['profile_photo'],
    'my_bookings' => $myBookings,
    'pending_bookings' => $pendingBookings,
    'available_rooms' => $availableRooms,
    'hostel_count' => $hostelCount,
    'latest_notice' => $latestNotice,
    'control_number' => '991234567890',
];
