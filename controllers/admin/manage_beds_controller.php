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
$allowedStatuses = ['active', 'maintenance', 'inactive'];

$flash = admin_prg_consume('manage_beds');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
    $openModal = (string)($flash['openModal'] ?? '');
    $editFormData = is_array($flash['editFormData'] ?? null) ? $flash['editFormData'] : null;
    $addFormData = is_array($flash['addFormData'] ?? null) ? $flash['addFormData'] : null;
}

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

$hasRoomBedCapacityCol = $columnExists($pdo, 'rooms', 'bed_capacity');

try {
    $rooms = $pdo->query("
        SELECT r.id, r.hostel_id, r.room_number, " . ($hasRoomBedCapacityCol ? 'r.bed_capacity' : '4 AS bed_capacity') . ", h.name AS hostel_name
        FROM rooms r
        JOIN hostels h ON h.id = r.hostel_id
        ORDER BY h.name ASC, r.room_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rooms = [];
    $errors[] = "Unable to load rooms. Ensure tables `rooms` and `hostels` exist.";
}

$roomIdToHostelId = [];
$roomIdToCapacity = [];
$roomIdToRoomNumber = [];
$roomOptions = [];
foreach ($rooms as $room) {
    $roomId = (int)($room['id'] ?? 0);
    $roomHostelId = (int)($room['hostel_id'] ?? 0);
    $roomCapacity = (int)($room['bed_capacity'] ?? 4);
    if ($roomCapacity < 1) {
        $roomCapacity = 1;
    } elseif ($roomCapacity > 4) {
        $roomCapacity = 4;
    }
    if ($roomId <= 0) {
        continue;
    }
    $roomIdToHostelId[$roomId] = $roomHostelId;
    $roomIdToCapacity[$roomId] = $roomCapacity;
    $roomIdToRoomNumber[$roomId] = (string)($room['room_number'] ?? '');
    $roomOptions[] = [
        'id' => $roomId,
        'room_number' => (string)($room['room_number'] ?? ''),
        'hostel_id' => $roomHostelId,
        'hostel_name' => (string)($room['hostel_name'] ?? ''),
        'bed_capacity' => $roomCapacity,
    ];
}

try {
    $hostelOptions = $pdo->query("
        SELECT id, name
        FROM hostels
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $hostelOptions = [];
    $hostelNameById = [];
    foreach ($roomOptions as $roomOption) {
        $roomHostelId = (int)($roomOption['hostel_id'] ?? 0);
        $roomHostelName = trim((string)($roomOption['hostel_name'] ?? ''));
        if ($roomHostelId > 0 && $roomHostelName !== '') {
            $hostelNameById[$roomHostelId] = $roomHostelName;
        }
    }
    natcasesort($hostelNameById);
    foreach ($hostelNameById as $id => $name) {
        $hostelOptions[] = [
            'id' => (int)$id,
            'name' => $name,
        ];
    }
}

$hostelIdMap = [];
foreach ($hostelOptions as $hostel) {
    $hostelId = (int)($hostel['id'] ?? 0);
    if ($hostelId > 0) {
        $hostelIdMap[$hostelId] = true;
    }
}

$initialHostelId = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;
$initialRoomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

if ($initialRoomId > 0 && isset($roomIdToHostelId[$initialRoomId])) {
    $roomHostelId = (int)$roomIdToHostelId[$initialRoomId];
    if ($initialHostelId <= 0) {
        $initialHostelId = $roomHostelId;
    } elseif ($roomHostelId !== $initialHostelId) {
        $initialRoomId = 0;
    }
}

if ($initialHostelId > 0 && !isset($hostelIdMap[$initialHostelId])) {
    $initialHostelId = 0;
    $initialRoomId = 0;
}

$buildNumberSeries = static function (string $seed, int $count, string $entityLabel, array &$errorBag): array {
    $value = trim($seed);
    $safeLabel = trim($entityLabel) !== '' ? trim($entityLabel) : 'Item';
    $amount = max(1, $count);

    if ($value === '') {
        $errorBag[] = $safeLabel . ' number is required.';
        return [];
    }

    if ($amount === 1) {
        return [$value];
    }

    if (!preg_match('/^(.*?)(\d+)$/', $value, $matches)) {
        $errorBag[] = $safeLabel . ' number must end with digits for bulk add.';
        return [];
    }

    $prefix = (string)$matches[1];
    $digits = (string)$matches[2];
    $width = max(1, strlen($digits));
    $start = (int)$digits;

    $series = [];
    for ($index = 0; $index < $amount; $index++) {
        $series[] = $prefix . str_pad((string)($start + $index), $width, '0', STR_PAD_LEFT);
    }

    return $series;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_bed') {
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomIds = [];
        $roomIdsRaw = $_POST['room_ids'] ?? [];
        if (is_array($roomIdsRaw)) {
            foreach ($roomIdsRaw as $rawRoomId) {
                $roomId = (int)$rawRoomId;
                if ($roomId > 0) {
                    $roomIds[$roomId] = $roomId;
                }
            }
        }
        $legacyRoomId = (int)($_POST['room_id'] ?? 0);
        if ($legacyRoomId > 0) {
            $roomIds[$legacyRoomId] = $legacyRoomId;
        }
        $roomIds = array_values($roomIds);
        $primaryRoomId = isset($roomIds[0]) ? (int)$roomIds[0] : 0;
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = 'active';
        $addMode = strtolower(trim((string)($_POST['add_mode'] ?? 'single')));
        if (!in_array($addMode, ['single', 'bulk'], true)) {
            $addMode = 'single';
        }
        $bulkCountRaw = (int)($_POST['bulk_count'] ?? 1);
        $bulkCount = $bulkCountRaw;
        if ($bulkCount < 1) {
            $bulkCount = 1;
        }
        if ($bulkCount > 200) {
            $bulkCount = 200;
        }
        $requestedCount = $addMode === 'bulk' ? $bulkCount : 1;

        if ($hostelId <= 0) $errors[] = 'Hostel is required.';
        if (empty($roomIds)) $errors[] = 'Select at least one room.';
        if ($bedNumber === '') $errors[] = 'Bed number is required.';
        if ($addMode === 'bulk' && $bulkCountRaw < 1) {
            $errors[] = 'Bulk add requires at least 1 bed.';
        }
        if ($hostelId > 0 && !empty($roomIds)) {
            foreach ($roomIds as $roomId) {
                $roomHostelId = (int)($roomIdToHostelId[$roomId] ?? 0);
                if ($roomHostelId <= 0 || $roomHostelId !== $hostelId) {
                    $errors[] = 'One or more selected rooms do not belong to the selected hostel.';
                    break;
                }
            }
        }

        $bedNumbers = $buildNumberSeries($bedNumber, $requestedCount, 'Bed', $errors);
        $uniqueBedNumbers = array_values(array_unique($bedNumbers));
        if (!empty($bedNumbers) && count($uniqueBedNumbers) !== count($bedNumbers)) {
            $errors[] = 'Generated bed numbers contain duplicates.';
        }

        if (empty($errors) && !empty($roomIds) && !empty($uniqueBedNumbers)) {
            $incomingCount = count($uniqueBedNumbers);
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM beds WHERE room_id = ?');

            foreach ($roomIds as $roomId) {
                $roomCapacity = (int)($roomIdToCapacity[$roomId] ?? 4);
                if ($roomCapacity < 1) {
                    $roomCapacity = 1;
                } elseif ($roomCapacity > 4) {
                    $roomCapacity = 4;
                }

                $countStmt->execute([$roomId]);
                $existingCount = (int)$countStmt->fetchColumn();

                if (($existingCount + $incomingCount) > $roomCapacity) {
                    $availableSlots = $roomCapacity - $existingCount;
                    if ($availableSlots < 0) {
                        $availableSlots = 0;
                    }
                    $roomNumber = trim((string)($roomIdToRoomNumber[$roomId] ?? ''));
                    $roomLabel = $roomNumber !== '' ? ('Room ' . $roomNumber) : ('Room #' . $roomId);
                    $errors[] = $roomLabel . ' capacity exceeded. Capacity is ' . $roomCapacity . ' bed(s), available slot(s): ' . $availableSlots . '.';
                    break;
                }
            }
        }

        if (empty($errors) && !empty($uniqueBedNumbers) && !empty($roomIds)) {
            $placeholders = implode(',', array_fill(0, count($uniqueBedNumbers), '?'));
            $stmt = $pdo->prepare('SELECT bed_number FROM beds WHERE room_id = ? AND bed_number IN (' . $placeholders . ')');

            foreach ($roomIds as $roomId) {
                $stmt->execute(array_merge([$roomId], $uniqueBedNumbers));
                $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($existing)) {
                    $preview = array_slice(array_map('strval', $existing), 0, 5);
                    $roomNumber = trim((string)($roomIdToRoomNumber[$roomId] ?? ''));
                    $roomLabel = $roomNumber !== '' ? ('Room ' . $roomNumber) : ('Room #' . $roomId);
                    $errors[] = 'Bed number already exists in ' . $roomLabel . ': ' . implode(', ', $preview) . (count($existing) > 5 ? '...' : '');
                    break;
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO beds (room_id, bed_number, status) VALUES (?, ?, ?)');
                foreach ($roomIds as $roomId) {
                    foreach ($uniqueBedNumbers as $numberValue) {
                        $stmt->execute([$roomId, $numberValue, $status]);
                    }
                }
                $pdo->commit();
                $createdCount = count($roomIds) * count($uniqueBedNumbers);
                if ($createdCount === 1) {
                    $success = 'Bed added successfully.';
                } else {
                    $success = $createdCount . ' beds added across ' . count($roomIds) . ' room(s) successfully.';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to add bed(s). Please try again.';
            }
        } else {
            $openModal = 'addBedModal';
            $addFormData = [
                'hostel_id' => $hostelId,
                'room_id' => $primaryRoomId,
                'room_ids' => $roomIds,
                'bed_number' => $bedNumber,
                'add_mode' => $addMode,
                'bulk_count' => $bulkCountRaw,
            ];
        }
    }

    if ($action === 'update_bed') {
        $id = (int)($_POST['id'] ?? 0);
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }

        if ($hostelId <= 0) $errors[] = 'Hostel is required.';
        if ($roomId <= 0) $errors[] = 'Room is required.';
        if ($bedNumber === '') $errors[] = 'Bed number is required.';
        if ($hostelId > 0 && $roomId > 0) {
            $roomHostelId = (int)($roomIdToHostelId[$roomId] ?? 0);
            if ($roomHostelId <= 0 || $roomHostelId !== $hostelId) {
                $errors[] = 'Selected room does not belong to the selected hostel.';
            }
        }

        if (empty($errors) && $roomId > 0 && $id > 0) {
            $roomCapacity = (int)($roomIdToCapacity[$roomId] ?? 4);
            if ($roomCapacity < 1) {
                $roomCapacity = 1;
            } elseif ($roomCapacity > 4) {
                $roomCapacity = 4;
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM beds WHERE room_id = ? AND id != ?');
            $countStmt->execute([$roomId, $id]);
            $existingWithoutCurrent = (int)$countStmt->fetchColumn();
            if (($existingWithoutCurrent + 1) > $roomCapacity) {
                $errors[] = 'Room capacity exceeded. Capacity is ' . $roomCapacity . ' bed(s).';
            }
        }

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
                'hostel_id' => $hostelId,
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

    admin_prg_redirect('manage_beds', [
        'errors' => $errors,
        'success' => $success,
        'openModal' => $openModal,
        'editFormData' => $editFormData,
        'addFormData' => $addFormData,
    ]);
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

}
unset($bed);

if ($addFormData === null && $initialHostelId > 0) {
    $addFormData = [
        'hostel_id' => $initialHostelId,
        'room_id' => $initialRoomId,
        'room_ids' => $initialRoomId > 0 ? [$initialRoomId] : [],
    ];
}

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
    'initialHostelId' => $initialHostelId,
    'initialRoomId' => $initialRoomId,
];
