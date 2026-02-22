<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../admin/includes/admin_post_guard.php';

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;
$addFormData = null;

$flash = admin_prg_consume('notice');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
    $openModal = (string)($flash['openModal'] ?? '');
    $editFormData = is_array($flash['editFormData'] ?? null) ? $flash['editFormData'] : null;
    $addFormData = is_array($flash['addFormData'] ?? null) ? $flash['addFormData'] : null;
}

$tableExists = static function (PDO $db, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    $cache[$table] = (int)$stmt->fetchColumn() > 0;
    return $cache[$table];
};

$columnExists = static function (PDO $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
};

$hasHostelsTable = $tableExists($pdo, 'hostels');
$hasRoomsTable = $tableExists($pdo, 'rooms');
$hasBedsTable = $tableExists($pdo, 'beds');

$supportsTargeting = $columnExists($pdo, 'notices', 'target_scope')
    && $columnExists($pdo, 'notices', 'hostel_id')
    && $columnExists($pdo, 'notices', 'room_id')
    && $columnExists($pdo, 'notices', 'bed_id');

$hostelOptions = [];
$roomOptions = [];
$bedOptions = [];
$roomMap = [];
$bedMap = [];
$hostelMap = [];

if ($supportsTargeting && $hasHostelsTable) {
    $hostelOptions = $pdo->query('SELECT id, name FROM hostels ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hostelOptions as $hostel) {
        $hostelId = (int)($hostel['id'] ?? 0);
        if ($hostelId > 0) {
            $hostelMap[$hostelId] = true;
        }
    }
}

if ($supportsTargeting && $hasRoomsTable && $hasHostelsTable) {
    $roomOptions = $pdo->query(
        'SELECT r.id, r.hostel_id, r.room_number, h.name AS hostel_name
         FROM rooms r
         JOIN hostels h ON h.id = r.hostel_id
         ORDER BY h.name ASC, r.room_number ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roomOptions as $room) {
        $roomId = (int)($room['id'] ?? 0);
        if ($roomId <= 0) {
            continue;
        }
        $roomMap[$roomId] = [
            'hostel_id' => (int)($room['hostel_id'] ?? 0),
            'room_number' => (string)($room['room_number'] ?? ''),
        ];
    }
}

if ($supportsTargeting && $hasBedsTable && $hasRoomsTable && $hasHostelsTable) {
    $bedOptions = $pdo->query(
        'SELECT b.id, b.room_id, b.bed_number, b.status, r.hostel_id, r.room_number, h.name AS hostel_name
         FROM beds b
         JOIN rooms r ON r.id = b.room_id
         JOIN hostels h ON h.id = r.hostel_id
         ORDER BY h.name ASC, r.room_number ASC, b.bed_number ASC, b.id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bedOptions as $bed) {
        $bedId = (int)($bed['id'] ?? 0);
        if ($bedId <= 0) {
            continue;
        }
        $bedMap[$bedId] = [
            'room_id' => (int)($bed['room_id'] ?? 0),
            'hostel_id' => (int)($bed['hostel_id'] ?? 0),
            'bed_number' => (string)($bed['bed_number'] ?? ''),
        ];
    }
}

$parseNoticeTarget = static function (array $input) use (
    $supportsTargeting,
    $hostelMap,
    $roomMap,
    $bedMap,
    &$errors
): array {
    if (!$supportsTargeting) {
        return [
            'target_scope' => 'public',
            'hostel_id' => null,
            'room_id' => null,
            'bed_id' => null,
        ];
    }

    $allowedScopes = ['public', 'hostel', 'room', 'bed'];
    $scope = strtolower(trim((string)($input['target_scope'] ?? 'public')));
    if (!in_array($scope, $allowedScopes, true)) {
        $scope = 'public';
    }

    $hostelId = (int)($input['hostel_id'] ?? 0);
    $roomId = (int)($input['room_id'] ?? 0);
    $bedId = (int)($input['bed_id'] ?? 0);

    if ($scope === 'public') {
        return [
            'target_scope' => 'public',
            'hostel_id' => null,
            'room_id' => null,
            'bed_id' => null,
        ];
    }

    if ($scope === 'hostel') {
        if ($hostelId <= 0 || !isset($hostelMap[$hostelId])) {
            $errors[] = 'Select a valid hostel for hostel notice.';
        }
        return [
            'target_scope' => 'hostel',
            'hostel_id' => $hostelId > 0 ? $hostelId : null,
            'room_id' => null,
            'bed_id' => null,
        ];
    }

    if ($scope === 'room') {
        if ($roomId <= 0 || !isset($roomMap[$roomId])) {
            $errors[] = 'Select a valid room for room notice.';
            return [
                'target_scope' => 'room',
                'hostel_id' => $hostelId > 0 ? $hostelId : null,
                'room_id' => $roomId > 0 ? $roomId : null,
                'bed_id' => null,
            ];
        }
        $roomHostelId = (int)($roomMap[$roomId]['hostel_id'] ?? 0);
        return [
            'target_scope' => 'room',
            'hostel_id' => $roomHostelId > 0 ? $roomHostelId : null,
            'room_id' => $roomId,
            'bed_id' => null,
        ];
    }

    if ($bedId <= 0 || !isset($bedMap[$bedId])) {
        $errors[] = 'Select a valid bed for bed notice.';
        return [
            'target_scope' => 'bed',
            'hostel_id' => $hostelId > 0 ? $hostelId : null,
            'room_id' => $roomId > 0 ? $roomId : null,
            'bed_id' => $bedId > 0 ? $bedId : null,
        ];
    }

    $bedRoomId = (int)($bedMap[$bedId]['room_id'] ?? 0);
    $bedHostelId = (int)($bedMap[$bedId]['hostel_id'] ?? 0);
    return [
        'target_scope' => 'bed',
        'hostel_id' => $bedHostelId > 0 ? $bedHostelId : null,
        'room_id' => $bedRoomId > 0 ? $bedRoomId : null,
        'bed_id' => $bedId,
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_notice') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target = $parseNoticeTarget($_POST);

        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';

        if (empty($errors)) {
            if ($supportsTargeting) {
                $stmt = $pdo->prepare(
                    'INSERT INTO notices (title, content, target_scope, hostel_id, room_id, bed_id) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $title,
                    $content,
                    (string)$target['target_scope'],
                    $target['hostel_id'],
                    $target['room_id'],
                    $target['bed_id'],
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO notices (title, content) VALUES (?, ?)');
                $stmt->execute([$title, $content]);
            }
            $success = 'Notice added successfully.';
        } else {
            $openModal = 'addNoticeModal';
            $addFormData = [
                'title' => $title,
                'content' => $content,
                'target_scope' => (string)($target['target_scope'] ?? 'public'),
                'hostel_id' => (int)($target['hostel_id'] ?? 0),
                'room_id' => (int)($target['room_id'] ?? 0),
                'bed_id' => (int)($target['bed_id'] ?? 0),
            ];
        }
    }

    if ($action === 'update_notice') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $target = $parseNoticeTarget($_POST);

        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';

        if (empty($errors)) {
            if ($supportsTargeting) {
                $stmt = $pdo->prepare(
                    'UPDATE notices
                     SET title = ?, content = ?, target_scope = ?, hostel_id = ?, room_id = ?, bed_id = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $title,
                    $content,
                    (string)$target['target_scope'],
                    $target['hostel_id'],
                    $target['room_id'],
                    $target['bed_id'],
                    $id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE notices SET title = ?, content = ? WHERE id = ?');
                $stmt->execute([$title, $content, $id]);
            }
            $success = 'Notice updated successfully.';
        } else {
            $openModal = 'editNoticeModal';
            $editFormData = [
                'id' => $id,
                'title' => $title,
                'content' => $content,
                'target_scope' => (string)($target['target_scope'] ?? 'public'),
                'hostel_id' => (int)($target['hostel_id'] ?? 0),
                'room_id' => (int)($target['room_id'] ?? 0),
                'bed_id' => (int)($target['bed_id'] ?? 0),
            ];
        }
    }

    if ($action === 'delete_notice') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM notices WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Notice deleted successfully.';
        }
    }

    admin_prg_redirect('notice', [
        'errors' => $errors,
        'success' => $success,
        'openModal' => $openModal,
        'editFormData' => $editFormData,
        'addFormData' => $addFormData,
    ]);
}

if ($supportsTargeting) {
    $notices = $pdo->query(
        'SELECT
            n.id,
            n.title,
            n.content,
            n.target_scope,
            n.hostel_id,
            n.room_id,
            n.bed_id,
            n.created_at,
            h.name AS target_hostel_name,
            r.room_number AS target_room_number,
            b.bed_number AS target_bed_number
         FROM notices n
         LEFT JOIN hostels h ON h.id = n.hostel_id
         LEFT JOIN rooms r ON r.id = n.room_id
         LEFT JOIN beds b ON b.id = n.bed_id
         ORDER BY n.id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $notices = $pdo->query(
        "SELECT
            n.id,
            n.title,
            n.content,
            'public' AS target_scope,
            NULL AS hostel_id,
            NULL AS room_id,
            NULL AS bed_id,
            n.created_at,
            NULL AS target_hostel_name,
            NULL AS target_room_number,
            NULL AS target_bed_number
         FROM notices n
         ORDER BY n.id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($notices as &$notice) {
    $scope = strtolower(trim((string)($notice['target_scope'] ?? 'public')));
    if (!in_array($scope, ['public', 'hostel', 'room', 'bed'], true)) {
        $scope = 'public';
    }
    $notice['target_scope'] = $scope;

    if ($scope === 'hostel') {
        $hostelName = trim((string)($notice['target_hostel_name'] ?? ''));
        $notice['target_label'] = $hostelName !== '' ? ('Hostel: ' . $hostelName) : 'Hostel';
    } elseif ($scope === 'room') {
        $roomNumber = trim((string)($notice['target_room_number'] ?? ''));
        $hostelName = trim((string)($notice['target_hostel_name'] ?? ''));
        $label = $roomNumber !== '' ? ('Room ' . $roomNumber) : 'Room';
        if ($hostelName !== '') {
            $label .= ' (' . $hostelName . ')';
        }
        $notice['target_label'] = $label;
    } elseif ($scope === 'bed') {
        $bedNumber = trim((string)($notice['target_bed_number'] ?? ''));
        $roomNumber = trim((string)($notice['target_room_number'] ?? ''));
        $hostelName = trim((string)($notice['target_hostel_name'] ?? ''));
        $label = $bedNumber !== '' ? ('Bed ' . $bedNumber) : 'Bed';
        if ($roomNumber !== '') {
            $label .= ' (Room ' . $roomNumber . ')';
        }
        if ($hostelName !== '') {
            $label .= ' - ' . $hostelName;
        }
        $notice['target_label'] = $label;
    } else {
        $notice['target_label'] = 'Public (All Users)';
    }
}
unset($notice);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'addFormData' => $addFormData,
    'editFormData' => $editFormData,
    'notices' => $notices,
    'supportsTargeting' => $supportsTargeting,
    'hostelOptions' => $hostelOptions,
    'roomOptions' => $roomOptions,
    'bedOptions' => $bedOptions,
];
