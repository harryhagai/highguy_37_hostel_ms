<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;
$hasCapacityCol = $columnExists($pdo, 'rooms', 'capacity');
$hasAvailableCol = $columnExists($pdo, 'rooms', 'available');

$hostels = $pdo->query('SELECT id, name FROM hostels ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_room') {
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $available = (int)($_POST['available'] ?? 0);
        $roomType = trim($_POST['room_type'] ?? '');
        $price = $_POST['price'] ?? '';

        if ($hostelId <= 0) $errors[] = 'Hostel is required.';
        if ($roomNumber === '') $errors[] = 'Room number is required.';
        if ($hasCapacityCol && $capacity <= 0) $errors[] = 'Capacity must be positive.';
        if ($hasAvailableCol && $available < 0) $errors[] = 'Available must be zero or more.';
        if ($roomType === '') $errors[] = 'Room type is required.';
        if (!is_numeric($price) || (float)$price < 0) $errors[] = 'Price must be a positive number.';

        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ?');
        $stmt->execute([$hostelId, $roomNumber]);
        if ($stmt->fetch()) {
            $errors[] = 'Room number already exists in this hostel.';
        }

        if (empty($errors)) {
            $columns = ['hostel_id', 'room_number', 'room_type', 'price'];
            $values = [$hostelId, $roomNumber, $roomType, $price];
            if ($hasCapacityCol) {
                $columns[] = 'capacity';
                $values[] = $capacity;
            }
            if ($hasAvailableCol) {
                $columns[] = 'available';
                $values[] = $available;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO rooms (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $success = 'Room added successfully.';
        } else {
            $openModal = 'addRoomModal';
        }
    }

    if ($action === 'update_room') {
        $id = (int)($_POST['id'] ?? 0);
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $available = (int)($_POST['available'] ?? 0);
        $roomType = trim($_POST['room_type'] ?? '');
        $price = $_POST['price'] ?? '';

        if ($hostelId <= 0) $errors[] = 'Hostel is required.';
        if ($roomNumber === '') $errors[] = 'Room number is required.';
        if ($hasCapacityCol && $capacity <= 0) $errors[] = 'Capacity must be positive.';
        if ($hasAvailableCol && $available < 0) $errors[] = 'Available must be zero or more.';
        if ($roomType === '') $errors[] = 'Room type is required.';
        if (!is_numeric($price) || (float)$price < 0) $errors[] = 'Price must be a positive number.';

        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ? AND id != ?');
        $stmt->execute([$hostelId, $roomNumber, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Room number already exists in this hostel.';
        }

        if (empty($errors)) {
            $set = ['hostel_id = ?', 'room_number = ?', 'room_type = ?', 'price = ?'];
            $values = [$hostelId, $roomNumber, $roomType, $price];
            if ($hasCapacityCol) {
                $set[] = 'capacity = ?';
                $values[] = $capacity;
            }
            if ($hasAvailableCol) {
                $set[] = 'available = ?';
                $values[] = $available;
            }
            $values[] = $id;

            $sql = 'UPDATE rooms SET ' . implode(', ', $set) . ' WHERE id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $success = 'Room updated successfully.';
        } else {
            $openModal = 'editRoomModal';
            $editFormData = [
                'id' => $id,
                'hostel_id' => $hostelId,
                'room_number' => $roomNumber,
                'capacity' => $capacity,
                'available' => $available,
                'room_type' => $roomType,
                'price' => $price
            ];
        }
    }

    if ($action === 'delete_room') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Room deleted successfully.';
        }
    }
}

$rooms = $pdo->query('SELECT r.*, h.name AS hostel_name FROM rooms r JOIN hostels h ON r.hostel_id = h.id ORDER BY r.id DESC')->fetchAll(PDO::FETCH_ASSOC);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'hasCapacityCol' => $hasCapacityCol,
    'hasAvailableCol' => $hasAvailableCol,
    'hostels' => $hostels,
    'rooms' => $rooms
];
