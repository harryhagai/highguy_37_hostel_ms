<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

try {
    $rooms = $pdo->query("
        SELECT r.id, r.room_number, h.name AS hostel_name
        FROM rooms r
        JOIN hostels h ON h.id = r.hostel_id
        ORDER BY h.name ASC, r.room_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rooms = [];
    $errors[] = "Unable to load rooms. Ensure tables `rooms` and `hostels` exist.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_bed') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'maintenance', 'inactive'], true)) {
            $status = 'active';
        }

        if ($roomId <= 0) $errors[] = 'Room is required.';
        if ($bedNumber === '') $errors[] = 'Bed number is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM beds WHERE room_id = ? AND bed_number = ?');
            $stmt->execute([$roomId, $bedNumber]);
            if ($stmt->fetch()) {
                $errors[] = 'Bed number already exists in this room.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO beds (room_id, bed_number, status) VALUES (?, ?, ?)');
            $stmt->execute([$roomId, $bedNumber, $status]);
            $success = 'Bed added successfully.';
        } else {
            $openModal = 'addBedModal';
        }
    }

    if ($action === 'update_bed') {
        $id = (int)($_POST['id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'maintenance', 'inactive'], true)) {
            $status = 'active';
        }

        if ($roomId <= 0) $errors[] = 'Room is required.';
        if ($bedNumber === '') $errors[] = 'Bed number is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM beds WHERE room_id = ? AND bed_number = ? AND id != ?');
            $stmt->execute([$roomId, $bedNumber, $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Bed number already exists in this room.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE beds SET room_id = ?, bed_number = ?, status = ? WHERE id = ?');
            $stmt->execute([$roomId, $bedNumber, $status, $id]);
            $success = 'Bed updated successfully.';
        } else {
            $openModal = 'editBedModal';
            $editFormData = [
                'id' => $id,
                'room_id' => $roomId,
                'bed_number' => $bedNumber,
                'status' => $status
            ];
        }
    }

    if ($action === 'delete_bed') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM beds WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Bed deleted successfully.';
        }
    }
}

try {
    $beds = $pdo->query("
        SELECT b.id, b.room_id, b.bed_number, b.status, b.created_at, r.room_number, h.name AS hostel_name
        FROM beds b
        JOIN rooms r ON r.id = b.room_id
        JOIN hostels h ON h.id = r.hostel_id
        ORDER BY b.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $beds = [];
    if (empty($errors)) {
        $errors[] = "Unable to load beds. Ensure table `beds` exists (run updated SQL).";
    }
}

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'rooms' => $rooms,
    'beds' => $beds
];
