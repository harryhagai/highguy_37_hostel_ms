<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

payment_expire_unpaid_pending_bookings($pdo, payment_booking_hold_minutes());

$profile = [
    'username' => 'Student',
    'email' => '',
    'profile_photo' => '../assets/images/prof.jpg',
    'has_profile_photo' => false,
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
        $rawPhoto = trim((string)($row['profile_photo'] ?? ''));
        $profile['has_profile_photo'] = $rawPhoto !== '';
        $profile['profile_photo'] = user_to_public_asset_path($rawPhoto, '../assets/images/prof.jpg');
    }
}

$displayUsername = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string)$profile['username'])) ?? '');
$displayUsername = $displayUsername === '' ? 'Student' : ucwords(strtolower($displayUsername));

$myBookings = 0;
$pendingBookings = 0;
$bookingLock = [
    'blocked' => false,
    'reason' => 'none',
    'message' => '',
    'booking' => null,
];
if ($userId > 0 && user_table_exists($pdo, 'bookings') && user_column_exists($pdo, 'bookings', 'user_id')) {
    $bookingLock = user_get_booking_lock_info($pdo, $userId);

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
$hostelCount = 0;
if (user_table_exists($pdo, 'hostels')) {
    if (user_column_exists($pdo, 'hostels', 'status')) {
        $hostelCount = (int)$pdo->query("SELECT COUNT(*) FROM hostels WHERE status = 'active'")->fetchColumn();
    } else {
        $hostelCount = (int)$pdo->query('SELECT COUNT(*) FROM hostels')->fetchColumn();
    }
}
$latestNotice = user_get_latest_notice($pdo);
$primaryBookingPage = !empty($bookingLock['blocked']) ? 'my_bed' : 'book_bed';
$primaryBookingLabel = !empty($bookingLock['blocked']) ? 'My Bed' : 'Book Bed';
$roomMenuPage = !empty($bookingLock['blocked']) ? 'my_bed' : 'view_hostels';
$roomMenuLabel = !empty($bookingLock['blocked']) ? 'My Bed' : 'View Rooms';
$controlNumbers = payment_fetch_control_numbers($pdo, true);
$defaultControlNumber = !empty($controlNumbers)
    ? trim((string)($controlNumbers[0]['control_number'] ?? ''))
    : '';

return [
    'user_id' => $userId,
    'username' => (string)$profile['username'],
    'display_username' => $displayUsername,
    'email' => (string)$profile['email'],
    'profile_pic' => (string)$profile['profile_photo'],
    'has_profile_photo' => (bool)$profile['has_profile_photo'],
    'my_bookings' => $myBookings,
    'pending_bookings' => $pendingBookings,
    'available_rooms' => $availableRooms,
    'hostel_count' => $hostelCount,
    'latest_notice' => $latestNotice,
    'booking_lock' => $bookingLock,
    'can_book' => empty($bookingLock['blocked']),
    'primary_booking_page' => $primaryBookingPage,
    'primary_booking_label' => $primaryBookingLabel,
    'room_menu_page' => $roomMenuPage,
    'room_menu_label' => $roomMenuLabel,
    'control_number' => $defaultControlNumber,
];
