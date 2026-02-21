<?php
session_start();
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'];
    $user_id = $_SESSION['user_id'];
    $booking_date = date('Y-m-d H:i:s');

    // Insert booking
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, room_id, booking_date, status) VALUES (?, ?, ?, 'Pending')");
    $stmt->execute([$user_id, $room_id, $booking_date]);

    // Reduce room availability by 1
    $pdo->prepare("UPDATE rooms SET available = available - 1 WHERE id = ?")->execute([$room_id]);

    // Redirect with message
    header("Location: user_dashboard_layout.php?page=my_bookings&success=1");
    exit;
}
