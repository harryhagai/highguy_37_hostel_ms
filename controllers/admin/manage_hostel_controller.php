<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

$uploadHostelImage = static function (array $file, string $targetDir, ?string &$error = null): ?string {
    if (empty($file['name'])) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        $error = 'Only JPG, JPEG, PNG, GIF, and WEBP are allowed.';
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $filename = $safeName . '_' . time() . '.' . $ext;
    $fullPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        $error = 'Failed to upload image.';
        return null;
    }

    return 'uploads/hostels/' . $filename;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hostel') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name === '') {
            $errors[] = 'Hostel name is required.';
        }
        if ($location === '') {
            $errors[] = 'Location is required.';
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
            $stmt = $pdo->prepare('INSERT INTO hostels (name, description, location, hostel_image) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $location, $hostelImage]);
            $success = 'Hostel added successfully.';
        } else {
            $openModal = 'addHostelModal';
        }
    }

    if ($action === 'update_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $existingImage = trim($_POST['existing_image'] ?? '');
        $hostelImage = $existingImage !== '' ? $existingImage : null;

        if ($name === '') {
            $errors[] = 'Hostel name is required.';
        }
        if ($location === '') {
            $errors[] = 'Location is required.';
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
            $stmt = $pdo->prepare('UPDATE hostels SET name = ?, description = ?, location = ?, hostel_image = ? WHERE id = ?');
            $stmt->execute([$name, $description, $location, $hostelImage, $id]);
            $success = 'Hostel updated successfully.';
        } else {
            $openModal = 'editHostelModal';
            $editFormData = [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'location' => $location,
                'hostel_image' => $hostelImage
            ];
        }
    }

    if ($action === 'delete_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT hostel_image FROM hostels WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare('DELETE FROM hostels WHERE id = ?');
            $stmt->execute([$id]);

            if (!empty($row['hostel_image']) && file_exists(__DIR__ . '/../../' . $row['hostel_image'])) {
                unlink(__DIR__ . '/../../' . $row['hostel_image']);
            }

            $success = 'Hostel deleted successfully.';
        }
    }
}

$hostels = $pdo->query('SELECT id, name, description, location, hostel_image, created_at FROM hostels ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'hostels' => $hostels
];
