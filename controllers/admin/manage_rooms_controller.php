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

$flash = admin_prg_consume('manage_rooms');
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

$hasRoomImagesTable = $tableExists($pdo, 'room_images');
$hasRoomImageIdCol = $columnExists($pdo, 'rooms', 'room_image_id');
$supportsRoomImages = $hasRoomImagesTable && $hasRoomImageIdCol;
$hasRoomBedCapacityCol = $columnExists($pdo, 'rooms', 'bed_capacity');
$hasBedsTable = $tableExists($pdo, 'beds');
$hasBookingsTable = $tableExists($pdo, 'bookings');
$bookingHasBedId = $hasBookingsTable && $columnExists($pdo, 'bookings', 'bed_id');
$bookingHasStatus = $hasBookingsTable && $columnExists($pdo, 'bookings', 'status');
$bookingHasStartDate = $hasBookingsTable && $columnExists($pdo, 'bookings', 'start_date');
$bookingHasEndDate = $hasBookingsTable && $columnExists($pdo, 'bookings', 'end_date');
$bedsHaveStatus = $hasBedsTable && $columnExists($pdo, 'beds', 'status');

$uploadRoomImage = static function (array $file, string $targetDir, ?string &$error = null): ?string {
    if (empty($file['name'])) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $error = 'Image upload failed. Please try again.';
        return null;
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $error = 'Invalid uploaded file.';
        return null;
    }

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }

    if ($mimeType === '' || strpos($mimeType, 'image/') !== 0) {
        $error = 'Please upload a valid image file.';
        return null;
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext);
    if ($ext === null) {
        $ext = '';
    }
    if ($ext === '') {
        $mimeExt = strtolower(substr($mimeType, 6));
        $mimeExt = str_replace(['svg+xml', 'jpeg'], ['svg', 'jpg'], $mimeExt);
        $mimeExt = preg_replace('/[^a-z0-9]/', '', $mimeExt);
        $ext = $mimeExt !== '' ? $mimeExt : 'img';
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo((string)$file['name'], PATHINFO_FILENAME));
    if (!$safeName) {
        $safeName = 'room_image';
    }

    $filename = $safeName . '_' . time() . '.' . $ext;
    $fullPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $fullPath)) {
        $error = 'Failed to upload image.';
        return null;
    }

    return 'uploads/rooms/' . $filename;
};

$roomImages = [];
if ($supportsRoomImages) {
    $roomImages = $pdo->query('SELECT id, image_path, image_label, created_at FROM room_images ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

$resolveRoomImageId = static function (
    PDO $db,
    bool $supportsImages,
    int $selectedId,
    array $uploadFile,
    string $imageLabel,
    callable $uploadFn,
    array &$errorBag
): ?int {
    if (!$supportsImages) {
        return null;
    }

    $resolvedId = $selectedId > 0 ? $selectedId : null;

    if ($resolvedId !== null) {
        $stmt = $db->prepare('SELECT id FROM room_images WHERE id = ?');
        $stmt->execute([$resolvedId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $errorBag[] = 'Selected room image not found.';
            $resolvedId = null;
        }
    }

    if (!empty($uploadFile['name'])) {
        $uploadError = null;
        $path = $uploadFn($uploadFile, __DIR__ . '/../../uploads/rooms', $uploadError);
        if ($uploadError) {
            $errorBag[] = $uploadError;
        } elseif ($path !== null) {
            $label = trim($imageLabel);
            if ($label === '') {
                $label = 'Room Image ' . date('YmdHis');
            }
            $stmt = $db->prepare('INSERT INTO room_images (image_path, image_label) VALUES (?, ?)');
            $stmt->execute([$path, $label]);
            $resolvedId = (int)$db->lastInsertId();
        }
    }

    return $resolvedId;
};

$hostels = $pdo->query('SELECT id, name FROM hostels ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$hostelIdMap = [];
foreach ($hostels as $hostel) {
    $hostelId = (int)($hostel['id'] ?? 0);
    if ($hostelId > 0) {
        $hostelIdMap[$hostelId] = true;
    }
}

$initialHostelId = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;
if ($initialHostelId > 0 && !isset($hostelIdMap[$initialHostelId])) {
    $initialHostelId = 0;
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
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_room') {
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim((string)($_POST['room_number'] ?? ''));
        $bedCapacityRaw = trim((string)($_POST['bed_capacity'] ?? '4'));
        $bedCapacity = (int)$bedCapacityRaw;
        $roomType = trim((string)($_POST['room_type'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceRaw = $_POST['price'] ?? '';
        $addMode = strtolower(trim((string)($_POST['add_mode'] ?? 'single')));
        if (!in_array($addMode, ['single', 'bulk'], true)) {
            $addMode = 'single';
        }
        $bulkCountRaw = (int)($_POST['bulk_count'] ?? 2);
        $bulkCount = $bulkCountRaw;
        if ($bulkCount < 2) {
            $bulkCount = 2;
        }
        if ($bulkCount > 200) {
            $bulkCount = 200;
        }
        $requestedCount = $addMode === 'bulk' ? $bulkCount : 1;

        $selectedImageId = (int)($_POST['room_image_id'] ?? 0);
        $imageLabel = trim((string)($_POST['room_image_label'] ?? ''));

        if ($hostelId <= 0) {
            $errors[] = 'Hostel is required.';
        }
        if ($roomNumber === '') {
            $errors[] = 'Room number is required.';
        }
        if ($roomType === '') {
            $errors[] = 'Room type is required.';
        }
        if ($bedCapacityRaw === '' || !preg_match('/^\d+$/', $bedCapacityRaw)) {
            $errors[] = 'Bed capacity must be a whole number.';
        } elseif ($bedCapacity < 1 || $bedCapacity > 4) {
            $errors[] = 'Bed capacity must be between 1 and 4.';
        }
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $errors[] = 'Price must be a non-negative number.';
        }
        if ($addMode === 'bulk' && $bulkCountRaw < 2) {
            $errors[] = 'Bulk add requires at least 2 rooms.';
        }

        $roomNumbers = $buildNumberSeries($roomNumber, $requestedCount, 'Room', $errors);
        $uniqueRoomNumbers = array_values(array_unique($roomNumbers));
        if (!empty($roomNumbers) && count($uniqueRoomNumbers) !== count($roomNumbers)) {
            $errors[] = 'Generated room numbers contain duplicates.';
        }

        if (empty($errors) && !empty($uniqueRoomNumbers)) {
            $placeholders = implode(',', array_fill(0, count($uniqueRoomNumbers), '?'));
            $stmt = $pdo->prepare('SELECT room_number FROM rooms WHERE hostel_id = ? AND room_number IN (' . $placeholders . ')');
            $stmt->execute(array_merge([$hostelId], $uniqueRoomNumbers));
            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($existing)) {
                $preview = array_slice(array_map('strval', $existing), 0, 5);
                $errors[] = 'Room number already exists in this hostel: ' . implode(', ', $preview) . (count($existing) > 5 ? '...' : '');
            }
        }

        $uploadedImageFile = $_FILES['room_image_upload'] ?? ($_FILES['room_image'] ?? []);

        $resolvedImageId = $resolveRoomImageId(
            $pdo,
            $supportsRoomImages,
            $selectedImageId,
            $uploadedImageFile,
            $imageLabel,
            $uploadRoomImage,
            $errors
        );

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                if ($supportsRoomImages && $hasRoomBedCapacityCol) {
                    $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, bed_capacity, room_type, price, description, room_image_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    foreach ($uniqueRoomNumbers as $numberValue) {
                        $stmt->execute([$hostelId, $numberValue, $bedCapacity, $roomType, (float)$priceRaw, $description, $resolvedImageId]);
                    }
                } elseif ($supportsRoomImages && !$hasRoomBedCapacityCol) {
                    $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, room_type, price, description, room_image_id) VALUES (?, ?, ?, ?, ?, ?)');
                    foreach ($uniqueRoomNumbers as $numberValue) {
                        $stmt->execute([$hostelId, $numberValue, $roomType, (float)$priceRaw, $description, $resolvedImageId]);
                    }
                } elseif (!$supportsRoomImages && $hasRoomBedCapacityCol) {
                    $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, bed_capacity, room_type, price, description) VALUES (?, ?, ?, ?, ?, ?)');
                    foreach ($uniqueRoomNumbers as $numberValue) {
                        $stmt->execute([$hostelId, $numberValue, $bedCapacity, $roomType, (float)$priceRaw, $description]);
                    }
                } else {
                    $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, room_type, price, description) VALUES (?, ?, ?, ?, ?)');
                    foreach ($uniqueRoomNumbers as $numberValue) {
                        $stmt->execute([$hostelId, $numberValue, $roomType, (float)$priceRaw, $description]);
                    }
                }
                $pdo->commit();
                $success = count($uniqueRoomNumbers) > 1
                    ? count($uniqueRoomNumbers) . ' rooms added successfully.'
                    : 'Room added successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to add room(s). Please try again.';
            }
        } else {
            $openModal = 'addRoomModal';
            $addFormData = [
                'hostel_id' => $hostelId,
                'room_number' => $roomNumber,
                'bed_capacity' => $bedCapacityRaw,
                'add_mode' => $addMode,
                'bulk_count' => $bulkCountRaw,
                'room_type' => $roomType,
                'price' => $priceRaw,
                'description' => $description,
                'room_image_id' => $selectedImageId,
                'room_image_label' => $imageLabel,
            ];
        }
    }

    if ($action === 'update_room') {
        $id = (int)($_POST['id'] ?? 0);
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim((string)($_POST['room_number'] ?? ''));
        $bedCapacityRaw = trim((string)($_POST['bed_capacity'] ?? '4'));
        $bedCapacity = (int)$bedCapacityRaw;
        $roomType = trim((string)($_POST['room_type'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceRaw = $_POST['price'] ?? '';

        $selectedImageId = (int)($_POST['room_image_id'] ?? 0);
        $imageLabel = trim((string)($_POST['room_image_label'] ?? ''));

        // Preserve current image when updating details without selecting a new file.
        if ($supportsRoomImages && $id > 0 && $selectedImageId <= 0) {
            $existingImageStmt = $pdo->prepare('SELECT room_image_id FROM rooms WHERE id = ?');
            $existingImageStmt->execute([$id]);
            $existingRoom = $existingImageStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingRoom) {
                $selectedImageId = (int)($existingRoom['room_image_id'] ?? 0);
            }
        }

        if ($id <= 0) {
            $errors[] = 'Room ID is required.';
        }
        if ($hostelId <= 0) {
            $errors[] = 'Hostel is required.';
        }
        if ($roomNumber === '') {
            $errors[] = 'Room number is required.';
        }
        if ($roomType === '') {
            $errors[] = 'Room type is required.';
        }
        if ($bedCapacityRaw === '' || !preg_match('/^\d+$/', $bedCapacityRaw)) {
            $errors[] = 'Bed capacity must be a whole number.';
        } elseif ($bedCapacity < 1 || $bedCapacity > 4) {
            $errors[] = 'Bed capacity must be between 1 and 4.';
        }
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $errors[] = 'Price must be a non-negative number.';
        }

        if (empty($errors) && $id > 0 && $hasRoomBedCapacityCol && $hasBedsTable) {
            $bedCountStmt = $pdo->prepare('SELECT COUNT(*) FROM beds WHERE room_id = ?');
            $bedCountStmt->execute([$id]);
            $existingBeds = (int)$bedCountStmt->fetchColumn();
            if ($existingBeds > $bedCapacity) {
                $errors[] = 'Bed capacity cannot be less than current beds in this room (' . $existingBeds . ').';
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ? AND id != ?');
        $stmt->execute([$hostelId, $roomNumber, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Room number already exists in this hostel.';
        }

        $uploadedImageFile = $_FILES['room_image_upload'] ?? ($_FILES['room_image'] ?? []);

        $resolvedImageId = $resolveRoomImageId(
            $pdo,
            $supportsRoomImages,
            $selectedImageId,
            $uploadedImageFile,
            $imageLabel,
            $uploadRoomImage,
            $errors
        );

        if (empty($errors)) {
            if ($supportsRoomImages && $hasRoomBedCapacityCol) {
                $stmt = $pdo->prepare('UPDATE rooms SET hostel_id = ?, room_number = ?, bed_capacity = ?, room_type = ?, price = ?, description = ?, room_image_id = ? WHERE id = ?');
                $stmt->execute([$hostelId, $roomNumber, $bedCapacity, $roomType, (float)$priceRaw, $description, $resolvedImageId, $id]);
            } elseif ($supportsRoomImages && !$hasRoomBedCapacityCol) {
                $stmt = $pdo->prepare('UPDATE rooms SET hostel_id = ?, room_number = ?, room_type = ?, price = ?, description = ?, room_image_id = ? WHERE id = ?');
                $stmt->execute([$hostelId, $roomNumber, $roomType, (float)$priceRaw, $description, $resolvedImageId, $id]);
            } elseif (!$supportsRoomImages && $hasRoomBedCapacityCol) {
                $stmt = $pdo->prepare('UPDATE rooms SET hostel_id = ?, room_number = ?, bed_capacity = ?, room_type = ?, price = ?, description = ? WHERE id = ?');
                $stmt->execute([$hostelId, $roomNumber, $bedCapacity, $roomType, (float)$priceRaw, $description, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE rooms SET hostel_id = ?, room_number = ?, room_type = ?, price = ?, description = ? WHERE id = ?');
                $stmt->execute([$hostelId, $roomNumber, $roomType, (float)$priceRaw, $description, $id]);
            }
            $success = 'Room updated successfully.';
        } else {
            $openModal = 'editRoomModal';
            $editFormData = [
                'id' => $id,
                'hostel_id' => $hostelId,
                'room_number' => $roomNumber,
                'bed_capacity' => $bedCapacityRaw,
                'room_type' => $roomType,
                'price' => $priceRaw,
                'description' => $description,
                'room_image_id' => $selectedImageId,
                'room_image_label' => $imageLabel,
            ];
        }
    }

    if ($action === 'delete_room') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
                $stmt->execute([$id]);
                $success = 'Room deleted successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Unable to delete room. It may have related beds or bookings.';
            }
        }
    }

    if ($action === 'bulk_rooms') {
        $bulkAction = trim((string)($_POST['bulk_action_type'] ?? ''));
        $selectedIds = $_POST['selected_room_ids'] ?? [];
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

        if ($bulkAction !== 'delete_selected') {
            $errors[] = 'Choose a valid bulk action.';
        } elseif (empty($ids)) {
            $errors[] = 'Select at least one room for bulk action.';
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare('DELETE FROM rooms WHERE id IN (' . $placeholders . ')');
                $stmt->execute($ids);
                $success = count($ids) . ' room(s) deleted successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Bulk delete failed. Some rooms may have related beds or bookings.';
            }
        }
    }

    admin_prg_redirect('manage_rooms', [
        'errors' => $errors,
        'success' => $success,
        'openModal' => $openModal,
        'editFormData' => $editFormData,
        'addFormData' => $addFormData,
    ]);
}

$roomsSql = '
    SELECT
        r.id,
        r.hostel_id,
        h.name AS hostel_name,
        r.room_number,
        ' . ($hasRoomBedCapacityCol ? 'r.bed_capacity' : '4 AS bed_capacity') . ',
        r.room_type,
        r.price,
        r.description,
        r.created_at,
        r.updated_at';

if ($hasBedsTable) {
    $roomsSql .= ',
        COALESCE(bs.total_beds, 0) AS total_beds,
        COALESCE(bs.active_beds, 0) AS active_beds,
        COALESCE(bs.free_beds, 0) AS free_beds';
} else {
    $roomsSql .= ',
        0 AS total_beds,
        0 AS active_beds,
        0 AS free_beds';
}

if ($supportsRoomImages) {
    $roomsSql .= ', r.room_image_id, ri.image_path AS room_image_path, ri.image_label AS room_image_label';
} else {
    $roomsSql .= ', NULL AS room_image_id, NULL AS room_image_path, NULL AS room_image_label';
}

$roomsSql .= '
    FROM rooms r
    JOIN hostels h ON h.id = r.hostel_id';

if ($supportsRoomImages) {
    $roomsSql .= '
    LEFT JOIN room_images ri ON ri.id = r.room_image_id';
}

if ($hasBedsTable) {
    $activeBedCondition = $bedsHaveStatus ? "b.status = 'active'" : '1=1';

    if ($bookingHasBedId) {
        $bookingJoinConditions = [];
        if ($bookingHasStatus) {
            $bookingJoinConditions[] = "LOWER(COALESCE(bk.status, '')) IN ('pending', 'confirmed', 'approved')";
        }
        if ($bookingHasStartDate && $bookingHasEndDate) {
            $bookingJoinConditions[] = 'CURDATE() >= bk.start_date';
            $bookingJoinConditions[] = 'CURDATE() < bk.end_date';
        }
        $bookingJoinSql = !empty($bookingJoinConditions)
            ? implode(' AND ', $bookingJoinConditions)
            : '1=1';

        $roomsSql .= '
    LEFT JOIN (
        SELECT
            b.room_id,
            COUNT(b.id) AS total_beds,
            SUM(CASE WHEN ' . $activeBedCondition . ' THEN 1 ELSE 0 END) AS active_beds,
            SUM(CASE WHEN ' . $activeBedCondition . ' AND bk.id IS NULL THEN 1 ELSE 0 END) AS free_beds
        FROM beds b
        LEFT JOIN bookings bk
            ON bk.bed_id = b.id
           AND ' . $bookingJoinSql . '
        GROUP BY b.room_id
    ) bs ON bs.room_id = r.id';
    } else {
        $roomsSql .= '
    LEFT JOIN (
        SELECT
            b.room_id,
            COUNT(b.id) AS total_beds,
            SUM(CASE WHEN ' . $activeBedCondition . ' THEN 1 ELSE 0 END) AS active_beds,
            SUM(CASE WHEN ' . $activeBedCondition . ' THEN 1 ELSE 0 END) AS free_beds
        FROM beds b
        GROUP BY b.room_id
    ) bs ON bs.room_id = r.id';
    }
}

$roomsSql .= '
    ORDER BY r.id DESC';

$rooms = $pdo->query($roomsSql)->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total_rooms' => 0,
    'new_today' => 0,
    'new_week' => 0,
    'avg_price' => 0.0,
    'room_types' => 0,
];

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));
$sumPrice = 0.0;
$typeSet = [];

foreach ($rooms as &$room) {
    $room['price'] = (float)$room['price'];
    $room['price_display'] = number_format((float)$room['price'], 2);
    $capacityValue = (int)($room['bed_capacity'] ?? 4);
    if ($capacityValue < 1) {
        $capacityValue = 1;
    } elseif ($capacityValue > 4) {
        $capacityValue = 4;
    }
    $room['bed_capacity'] = $capacityValue;
    $totalBeds = (int)($room['total_beds'] ?? 0);
    $activeBeds = (int)($room['active_beds'] ?? 0);
    $freeBeds = (int)($room['free_beds'] ?? 0);
    if ($totalBeds < 0) {
        $totalBeds = 0;
    }
    if ($activeBeds < 0) {
        $activeBeds = 0;
    }
    if ($freeBeds < 0) {
        $freeBeds = 0;
    }

    $room['beds_count'] = $totalBeds;
    $room['active_beds'] = $activeBeds;

    if ($hasBedsTable && $totalBeds > 0) {
        $freeBedsRemaining = $activeBeds > 0 ? min($activeBeds, $freeBeds) : 0;
    } else {
        // Fallback for setups that still rely on room capacity without explicit beds rows.
        $freeBedsRemaining = $capacityValue;
    }
    $room['free_beds_remaining'] = $freeBedsRemaining;

    $createdAt = (string)($room['created_at'] ?? '');
    $updatedAt = (string)($room['updated_at'] ?? '');

    $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';
    $room['created_date'] = $createdDate;
    $room['created_at_display'] = $createdAt !== '' ? date('d M Y', strtotime($createdAt)) : '-';
    $room['updated_at_display'] = $updatedAt !== '' ? date('d M Y', strtotime($updatedAt)) : '-';

    $roomType = strtolower(trim((string)($room['room_type'] ?? '')));
    $room['room_type_key'] = $roomType;
    if ($roomType !== '') {
        $typeSet[$roomType] = true;
    }

    $room['price_tier'] = ((float)$room['price'] > 0) ? 'paid' : 'free';
    $room['has_image'] = !empty($room['room_image_path']) ? 'yes' : 'no';

    if ($createdDate === $today) {
        $stats['new_today']++;
    }
    if ($createdDate !== '' && $createdDate >= $weekAgo && $createdDate <= $today) {
        $stats['new_week']++;
    }

    $sumPrice += (float)$room['price'];
}
unset($room);

$stats['total_rooms'] = count($rooms);
$stats['room_types'] = count($typeSet);
$stats['avg_price'] = $stats['total_rooms'] > 0 ? ($sumPrice / $stats['total_rooms']) : 0.0;

$roomTypeOptions = [];
foreach ($rooms as $room) {
    $type = trim((string)($room['room_type'] ?? ''));
    if ($type !== '') {
        $roomTypeOptions[strtolower($type)] = $type;
    }
}
natcasesort($roomTypeOptions);
$roomTypeOptions = array_values($roomTypeOptions);

if ($addFormData === null && $initialHostelId > 0) {
    $addFormData = [
        'hostel_id' => $initialHostelId,
        'bed_capacity' => 4,
    ];
}

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'addFormData' => $addFormData,
    'hostels' => $hostels,
    'rooms' => $rooms,
    'stats' => $stats,
    'roomTypeOptions' => $roomTypeOptions,
    'supportsRoomImages' => $supportsRoomImages,
    'roomImages' => $roomImages,
    'initialHostelId' => $initialHostelId,
];
