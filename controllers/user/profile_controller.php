<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

$errors = [];
$success = '';

if ($userId <= 0) {
    return [
        'errors' => ['Session expired. Please login again.'],
        'success' => '',
        'profile' => null,
        'supports_phone' => false,
        'supports_photo' => false,
    ];
}

$supportsPhone = user_column_exists($pdo, 'users', 'phone');
$supportsPhoto = user_column_exists($pdo, 'users', 'profile_photo');

$fetchProfile = static function () use ($pdo, $userId, $supportsPhone, $supportsPhoto): ?array {
    if (!user_table_exists($pdo, 'users')) {
        return null;
    }

    $columns = ['id', 'username', 'email'];
    $columns[] = $supportsPhone ? 'phone' : "'' AS phone";
    $columns[] = $supportsPhoto ? 'profile_photo' : "'' AS profile_photo";
    if (user_column_exists($pdo, 'users', 'role')) {
        $columns[] = 'role';
    } else {
        $columns[] = "'user' AS role";
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $columns) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['profile_photo_url'] = user_to_public_asset_path((string)($row['profile_photo'] ?? ''), '../assets/images/prof.jpg');
    return $row;
};

$profile = $fetchProfile();
if (!$profile) {
    return [
        'errors' => ['User profile not found.'],
        'success' => '',
        'profile' => null,
        'supports_phone' => $supportsPhone,
        'supports_photo' => $supportsPhoto,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'update_account') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be between 3 and 50 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($supportsPhone && $phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = 'Phone number format is invalid.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Username is already in use.';
            }

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Email is already in use.';
            }
        }

        if (empty($errors)) {
            if ($supportsPhone) {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->execute([$username, $email, $phone, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                $stmt->execute([$username, $email, $userId]);
            }

            $_SESSION['username'] = $username;
            $success = 'Profile updated successfully.';
        }
    }

    if ($action === 'update_photo') {
        if (!$supportsPhoto) {
            $errors[] = 'Profile photo is not supported in this database.';
        } else {
            $file = $_FILES['profile_photo'] ?? [];
            if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Please choose an image to upload.';
            } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[] = 'Image upload failed. Please try again.';
            } else {
                $tmpPath = (string)($file['tmp_name'] ?? '');
                $mimeType = '';

                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mimeType = (string)finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                    }
                }

                if ($tmpPath === '' || !is_uploaded_file($tmpPath) || strpos($mimeType, 'image/') !== 0) {
                    $errors[] = 'Please upload a valid image file.';
                } else {
                    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                    $ext = preg_replace('/[^a-z0-9]/', '', $ext ?? '');
                    if ($ext === '') {
                        $ext = 'jpg';
                    }

                    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo((string)$file['name'], PATHINFO_FILENAME));
                    if (!$safeName) {
                        $safeName = 'user_photo';
                    }

                    $targetDir = __DIR__ . '/../../uploads/profiles';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }

                    $filename = 'profile_' . $userId . '_' . time() . '_' . $safeName . '.' . $ext;
                    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

                    if (!move_uploaded_file($tmpPath, $targetPath)) {
                        $errors[] = 'Failed to save uploaded image.';
                    } else {
                        $photoPath = 'uploads/profiles/' . $filename;
                        $stmt = $pdo->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
                        $stmt->execute([$photoPath, $userId]);
                        $_SESSION['profile_pic'] = user_to_public_asset_path($photoPath, '../assets/images/prof.jpg');
                        $success = 'Profile photo updated successfully.';
                    }
                }
            }
        }
    }

    $profile = $fetchProfile();
}

return [
    'errors' => $errors,
    'success' => $success,
    'profile' => $profile,
    'supports_phone' => $supportsPhone,
    'supports_photo' => $supportsPhoto,
];
