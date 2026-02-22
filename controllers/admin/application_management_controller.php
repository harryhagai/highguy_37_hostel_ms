<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../admin/includes/admin_post_guard.php';

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};

$success = '';
$errors = [];

$flash = admin_prg_consume('application_management');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $errors[] = 'Invalid application ID.';
    } else {
        if ($action === 'approve_application') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $success = $stmt->rowCount() ? 'Application approved successfully.' : 'Application is already processed.';
        }

        if ($action === 'reject_application') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $success = $stmt->rowCount() ? 'Application rejected successfully.' : 'Application is already processed.';
        }
    }

    admin_prg_redirect('application_management', [
        'errors' => $errors,
        'success' => $success,
    ]);
}

$hasBookingRoom = $columnExists($pdo, 'bookings', 'room_id');
$hasBookingBed = $columnExists($pdo, 'bookings', 'bed_id');

if ($hasBookingRoom) {
    $stmt = $pdo->query("
        SELECT
            b.id,
            u.username,
            u.email,
            r.room_number,
            '' AS bed_number,
            h.name AS hostel_name,
            b.status,
            b.booking_date,
            b.created_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN rooms r ON b.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        ORDER BY b.created_at DESC
    ");
} elseif ($hasBookingBed) {
    $stmt = $pdo->query("
        SELECT
            b.id,
            u.username,
            u.email,
            r.room_number,
            bd.bed_number,
            h.name AS hostel_name,
            b.status,
            b.booking_date,
            b.created_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN beds bd ON b.bed_id = bd.id
        JOIN rooms r ON bd.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        ORDER BY b.created_at DESC
    ");
} else {
    $stmt = null;
    $errors[] = 'Bookings schema is not compatible: missing room_id/bed_id.';
}

$applications = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

return [
    'errors' => $errors,
    'success' => $success,
    'applications' => $applications
];
