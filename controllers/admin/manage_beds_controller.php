<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;
$addFormData = null;
$allowedStatuses = ['active', 'maintenance', 'inactive'];

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
        if (!in_array($status, $allowedStatuses, true)) {
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
            $addFormData = [
                'room_id' => $roomId,
                'bed_number' => $bedNumber,
                'status' => $status,
            ];
        }
    }

    if ($action === 'update_bed') {
        $id = (int)($_POST['id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, $allowedStatuses, true)) {
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

    if ($action === 'set_bed_inactive') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE beds SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Bed set to inactive successfully.';
        }
    }

    if ($action === 'bulk_beds') {
        $bulkAction = trim((string)($_POST['bulk_action_type'] ?? ''));
        $selectedIds = $_POST['selected_bed_ids'] ?? [];
        $ids = [];

        if (is_array($selectedIds)) {
            foreach ($selectedIds as $rawId) {
                $id = (int)$rawId;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        $ids = array_values($ids);

        $statusByAction = [
            'set_active' => 'active',
            'set_maintenance' => 'maintenance',
            'set_inactive' => 'inactive',
        ];
        $validActions = array_keys($statusByAction);

        if (!in_array($bulkAction, $validActions, true)) {
            $errors[] = 'Choose a valid bulk action.';
        } elseif (empty($ids)) {
            $errors[] = 'Select at least one bed for bulk action.';
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statusValue = $statusByAction[$bulkAction];
                $params = array_merge([$statusValue], $ids);
                $stmt = $pdo->prepare('UPDATE beds SET status = ? WHERE id IN (' . $placeholders . ')');
                $stmt->execute($params);
                $success = count($ids) . ' bed(s) updated to ' . ucfirst($statusValue) . '.';
            } catch (Throwable $e) {
                $errors[] = 'Bulk action failed. Some beds may have related records.';
            }
        }
    }
}

try {
    $beds = $pdo->query("
        SELECT
            b.id,
            b.room_id,
            b.bed_number,
            b.status,
            b.created_at,
            b.updated_at,
            r.room_number,
            h.id AS hostel_id,
            h.name AS hostel_name
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

$stats = [
    'total_beds' => 0,
    'active_beds' => 0,
    'maintenance_beds' => 0,
    'inactive_beds' => 0,
    'new_today' => 0,
];
$today = date('Y-m-d');

$hostelMap = [];
$roomMap = [];

foreach ($beds as &$bed) {
    $status = strtolower(trim((string)($bed['status'] ?? 'active')));
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'active';
    }
    $bed['status'] = $status;

    $createdAt = (string)($bed['created_at'] ?? '');
    $updatedAt = (string)($bed['updated_at'] ?? '');
    $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';

    $bed['created_date'] = $createdDate;
    $bed['created_at_display'] = $createdAt !== '' ? date('d M Y', strtotime($createdAt)) : '-';
    $bed['updated_at_display'] = $updatedAt !== '' ? date('d M Y', strtotime($updatedAt)) : '-';

    $stats['total_beds']++;
    if ($status === 'active') {
        $stats['active_beds']++;
    } elseif ($status === 'maintenance') {
        $stats['maintenance_beds']++;
    } else {
        $stats['inactive_beds']++;
    }
    if ($createdDate === $today) {
        $stats['new_today']++;
    }

    $hostelId = (int)($bed['hostel_id'] ?? 0);
    $hostelName = trim((string)($bed['hostel_name'] ?? ''));
    if ($hostelId > 0 && $hostelName !== '') {
        $hostelMap[$hostelId] = $hostelName;
    }

    $roomId = (int)($bed['room_id'] ?? 0);
    $roomNumber = trim((string)($bed['room_number'] ?? ''));
    if ($roomId > 0 && $roomNumber !== '') {
        $roomMap[$roomId] = [
            'id' => $roomId,
            'room_number' => $roomNumber,
            'hostel_id' => $hostelId,
            'hostel_name' => $hostelName,
        ];
    }
}
unset($bed);

natcasesort($hostelMap);
$hostelOptions = [];
foreach ($hostelMap as $id => $name) {
    $hostelOptions[] = [
        'id' => (int)$id,
        'name' => $name,
    ];
}

uasort($roomMap, static function (array $a, array $b): int {
    $byRoom = strnatcasecmp((string)$a['room_number'], (string)$b['room_number']);
    if ($byRoom !== 0) {
        return $byRoom;
    }
    return strnatcasecmp((string)$a['hostel_name'], (string)$b['hostel_name']);
});
$roomOptions = array_values($roomMap);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'addFormData' => $addFormData,
    'rooms' => $rooms,
    'beds' => $beds,
    'stats' => $stats,
    'hostelOptions' => $hostelOptions,
    'roomOptions' => $roomOptions,
];
