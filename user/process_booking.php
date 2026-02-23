<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../permission/role_permission.php';
require_once __DIR__ . '/../includes/user_helpers.php';
rp_require_roles(['user'], '../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = (int)($_POST['room_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $booking_date = date('Y-m-d H:i:s');
    $tokenColumn = user_booking_token_column($pdo);
    $bookingLock = user_get_booking_lock_info($pdo, $userId);

    if ($roomId <= 0 || $userId <= 0) {
        header('Location: user_dashboard_layout.php?page=my_bookings&error=invalid_booking');
        exit;
    }

    if ($bookingLock['blocked']) {
        header('Location: user_dashboard_layout.php?page=my_bookings&error=booking_locked');
        exit;
    }

    $columns = ['user_id', 'room_id', 'booking_date', 'status'];
    $placeholders = ['?', '?', '?', '?'];
    $values = [$userId, $roomId, $booking_date, 'pending'];

    if ($tokenColumn !== '') {
        $columns[] = $tokenColumn;
        $placeholders[] = '?';
        $values[] = user_generate_booking_token();
    }

    $sql = 'INSERT INTO bookings (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    if (user_table_exists($pdo, 'rooms') && user_column_exists($pdo, 'rooms', 'available')) {
        $pdo->prepare('UPDATE rooms SET available = GREATEST(COALESCE(available, 0) - 1, 0) WHERE id = ?')->execute([$roomId]);
    }

    header('Location: user_dashboard_layout.php?page=my_bookings&success=1');
    exit;
}
