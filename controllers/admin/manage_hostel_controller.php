<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/admin_post_guard.php';

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;
$addFormData = null;

$flash = admin_prg_consume('manage_hostel');
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

$hasHostelGender = $columnExists($pdo, 'hostels', 'gender');
$hasHostelStatus = $columnExists($pdo, 'hostels', 'status');
$hasHostelDescription = $columnExists($pdo, 'hostels', 'description');

$uploadHostelImage = static function (array $file, string $targetDir, ?string &$error = null): ?string {
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
        $safeName = 'hostel_image';
    }
    $filename = $safeName . '_' . time() . '.' . $ext;
    $fullPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $fullPath)) {
        $error = 'Failed to upload image.';
        return null;
    }

    return 'uploads/hostels/' . $filename;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hostel') {
        $name = trim($_POST['name'] ?? '');
        $description = trim((string)($_POST['description'] ?? ''));
        $location = trim($_POST['location'] ?? '');
        $gender = strtolower(trim((string)($_POST['gender'] ?? 'all')));

        if ($name === '') {
            $errors[] = 'Hostel name is required.';
        }
        if ($location === '') {
            $errors[] = 'Location is required.';
        }
        if (!in_array($gender, ['male', 'female', 'all'], true)) {
            $errors[] = 'Gender must be male, female, or all.';
        }

        $stmt = $pdo->prepare('SELECT id FROM hostels WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $errors[] = 'Hostel name already exists.';
        }

        $hostelImage = null;
        if (!empty($_FILES['hostel_image']['name'])) {
            $uploadError = null;
            $hostelImage = $uploadHostelImage($_FILES['hostel_image'], __DIR__ . '/../../uploads/hostels', $uploadError);
            if ($uploadError) {
                $errors[] = $uploadError;
            }
        }

        if (empty($errors)) {
            $insertData = [
                'name' => $name,
                'location' => $location,
            ];
            if ($hasHostelDescription) {
                $insertData['description'] = $description;
            }
            if ($hasHostelGender) {
                $insertData['gender'] = $gender;
            }
            if ($hasHostelStatus) {
                $insertData['status'] = 'active';
            }
            $insertData['hostel_image'] = $hostelImage;

            $insertColumns = array_keys($insertData);
            $insertPlaceholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            $stmt = $pdo->prepare(
                'INSERT INTO hostels (' . implode(', ', $insertColumns) . ') VALUES (' . $insertPlaceholders . ')'
            );
            $stmt->execute(array_values($insertData));
            $success = 'Hostel added successfully.';
        } else {
            $openModal = 'addHostelModal';
            $addFormData = [
                'name' => $name,
                'description' => $description,
                'location' => $location,
                'gender' => $gender,
            ];
        }
    }

    if ($action === 'update_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim((string)($_POST['description'] ?? ''));
        $location = trim($_POST['location'] ?? '');
        $gender = strtolower(trim((string)($_POST['gender'] ?? 'all')));
        $existingImage = trim($_POST['existing_image'] ?? '');
        $hostelImage = $existingImage !== '' ? $existingImage : null;

        if ($name === '') {
            $errors[] = 'Hostel name is required.';
        }
        if ($location === '') {
            $errors[] = 'Location is required.';
        }
        if (!in_array($gender, ['male', 'female', 'all'], true)) {
            $errors[] = 'Gender must be male, female, or all.';
        }

        $stmt = $pdo->prepare('SELECT id FROM hostels WHERE name = ? AND id != ?');
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Hostel name already exists.';
        }

        if (!empty($_FILES['hostel_image']['name'])) {
            $uploadError = null;
            $newImage = $uploadHostelImage($_FILES['hostel_image'], __DIR__ . '/../../uploads/hostels', $uploadError);
            if ($uploadError) {
                $errors[] = $uploadError;
            } elseif ($newImage !== null) {
                if ($hostelImage && file_exists(__DIR__ . '/../../' . $hostelImage)) {
                    unlink(__DIR__ . '/../../' . $hostelImage);
                }
                $hostelImage = $newImage;
            }
        }

        if (empty($errors)) {
            $updateData = [
                'name' => $name,
                'location' => $location,
            ];
            if ($hasHostelDescription) {
                $updateData['description'] = $description;
            }
            if ($hasHostelGender) {
                $updateData['gender'] = $gender;
            }
            $updateData['hostel_image'] = $hostelImage;

            $setClause = implode(', ', array_map(
                static function (string $column): string {
                    return $column . ' = ?';
                },
                array_keys($updateData)
            ));

            $params = array_values($updateData);
            $params[] = $id;

            $stmt = $pdo->prepare('UPDATE hostels SET ' . $setClause . ' WHERE id = ?');
            $stmt->execute($params);
            $success = 'Hostel updated successfully.';
        } else {
            $openModal = 'editHostelModal';
            $editFormData = [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'location' => $location,
                'gender' => $gender,
                'hostel_image' => $hostelImage
            ];
        }
    }

    if ($action === 'disable_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if (!$hasHostelStatus) {
                $errors[] = 'Hostel status column is missing.';
            } else {
                $stmt = $pdo->prepare("UPDATE hostels SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Hostel disabled successfully.';
            }
        }
    }

    if ($action === 'activate_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if (!$hasHostelStatus) {
                $errors[] = 'Hostel status column is missing.';
            } else {
                $stmt = $pdo->prepare("UPDATE hostels SET status = 'active' WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Hostel activated successfully.';
            }
        }
    }

    if ($action === 'bulk_hostels') {
        $bulkAction = trim((string)($_POST['bulk_action_type'] ?? ''));
        $selectedIds = $_POST['selected_hostel_ids'] ?? [];
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

        if (!in_array($bulkAction, ['set_inactive', 'set_active'], true)) {
            $errors[] = 'Choose a valid bulk action.';
        } elseif (empty($ids)) {
            $errors[] = 'Select at least one hostel for bulk action.';
        } elseif (!$hasHostelStatus) {
            $errors[] = 'Hostel status column is missing.';
        } else {
            $targetStatus = $bulkAction === 'set_active' ? 'active' : 'inactive';
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE hostels SET status = '" . $targetStatus . "' WHERE id IN (" . $placeholders . ")");
            $stmt->execute($ids);
            $success = count($ids) . ' hostel(s) ' . ($targetStatus === 'active' ? 'activated' : 'disabled') . ' successfully.';
        }
    }

    admin_prg_redirect('manage_hostel', [
        'errors' => $errors,
        'success' => $success,
        'openModal' => $openModal,
        'editFormData' => $editFormData,
        'addFormData' => $addFormData,
    ]);
}

$hasRoomsTable = $tableExists($pdo, 'rooms');
$hasBedsTable = $tableExists($pdo, 'beds');

$hostelsSql = 'SELECT h.id, h.name, h.location, h.hostel_image, h.created_at';
if ($hasHostelDescription) {
    $hostelsSql .= ', h.description';
} else {
    $hostelsSql .= ", '' AS description";
}
if ($hasHostelGender) {
    $hostelsSql .= ', h.gender';
} else {
    $hostelsSql .= ", 'all' AS gender";
}
if ($hasHostelStatus) {
    $hostelsSql .= ', h.status';
} else {
    $hostelsSql .= ", 'active' AS status";
}

if ($hasRoomsTable) {
    $groupBy = 'h.id, h.name, h.location, h.hostel_image, h.created_at';
    if ($hasHostelDescription) {
        $groupBy .= ', h.description';
    }
    if ($hasHostelGender) {
        $groupBy .= ', h.gender';
    }
    if ($hasHostelStatus) {
        $groupBy .= ', h.status';
    }

    if ($hasBedsTable) {
        $hostelsSql .= ', COUNT(DISTINCT r.id) AS room_count, COUNT(b.id) AS bed_capacity
        FROM hostels h
        LEFT JOIN rooms r ON r.hostel_id = h.id
        LEFT JOIN beds b ON b.room_id = r.id
        GROUP BY ' . $groupBy;
    } else {
        $hostelsSql .= ', COUNT(DISTINCT r.id) AS room_count, 0 AS bed_capacity
        FROM hostels h
        LEFT JOIN rooms r ON r.hostel_id = h.id
        GROUP BY ' . $groupBy;
    }
} else {
    $hostelsSql .= ', 0 AS room_count, 0 AS bed_capacity
        FROM hostels h';
}
$hostelsSql .= ' ORDER BY h.id DESC';

$hostels = $pdo->query($hostelsSql)->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total_hostels' => 0,
    'new_today' => 0,
    'new_week' => 0,
    'with_images' => 0,
    'unique_locations' => 0,
];

$uniqueLocations = [];
$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));

foreach ($hostels as &$hostel) {
    $genderValue = strtolower(trim((string)($hostel['gender'] ?? 'all')));
    if (!in_array($genderValue, ['male', 'female', 'all'], true)) {
        $genderValue = 'all';
    }
    $hostel['gender'] = $genderValue;

    $createdAt = (string)($hostel['created_at'] ?? '');
    $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';
    $hostel['created_date'] = $createdDate;
    $hostel['created_at_display'] = $createdAt !== '' ? date('d M Y', strtotime($createdAt)) : '-';
    $hostel['bed_capacity'] = (int)($hostel['bed_capacity'] ?? 0);
    $statusValue = strtolower(trim((string)($hostel['status'] ?? 'active')));
    if (!in_array($statusValue, ['active', 'inactive'], true)) {
        $statusValue = 'active';
    }
    $hostel['status'] = $statusValue;

    if (!empty($hostel['location'])) {
        $uniqueLocations[strtolower(trim((string)$hostel['location']))] = trim((string)$hostel['location']);
    }

    if (!empty($hostel['hostel_image'])) {
        $stats['with_images']++;
    }

    if ($createdDate === $today) {
        $stats['new_today']++;
    }
    if ($createdDate !== '' && $createdDate >= $weekAgo && $createdDate <= $today) {
        $stats['new_week']++;
    }
}
unset($hostel);

$stats['total_hostels'] = count($hostels);
$stats['unique_locations'] = count($uniqueLocations);

$locationOptions = array_values($uniqueLocations);
natcasesort($locationOptions);
$locationOptions = array_values($locationOptions);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'addFormData' => $addFormData,
    'hostels' => $hostels,
    'stats' => $stats,
    'locationOptions' => $locationOptions,
];
