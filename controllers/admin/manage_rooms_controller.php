<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;
$addFormData = null;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_room') {
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim((string)($_POST['room_number'] ?? ''));
        $roomType = trim((string)($_POST['room_type'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceRaw = $_POST['price'] ?? '';

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
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $errors[] = 'Price must be a non-negative number.';
        }

        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ?');
        $stmt->execute([$hostelId, $roomNumber]);
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
            if ($supportsRoomImages) {
                $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, room_type, price, description, room_image_id) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$hostelId, $roomNumber, $roomType, (float)$priceRaw, $description, $resolvedImageId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, room_type, price, description) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$hostelId, $roomNumber, $roomType, (float)$priceRaw, $description]);
            }
            $success = 'Room added successfully.';
        } else {
            $openModal = 'addRoomModal';
            $addFormData = [
                'hostel_id' => $hostelId,
                'room_number' => $roomNumber,
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
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $errors[] = 'Price must be a non-negative number.';
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
            if ($supportsRoomImages) {
                $stmt = $pdo->prepare('UPDATE rooms SET hostel_id = ?, room_number = ?, room_type = ?, price = ?, description = ?, room_image_id = ? WHERE id = ?');
                $stmt->execute([$hostelId, $roomNumber, $roomType, (float)$priceRaw, $description, $resolvedImageId, $id]);
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
}

$roomsSql = '
    SELECT
        r.id,
        r.hostel_id,
        h.name AS hostel_name,
        r.room_number,
        r.room_type,
        r.price,
        r.description,
        r.created_at,
        r.updated_at';

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
];
