<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$errors = [];
$success = '';

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

$hasPhoneColumn = $columnExists($pdo, 'users', 'phone');
$hasProfilePhotoColumn = $columnExists($pdo, 'users', 'profile_photo');

$adminId = (int)($_SESSION['user_id'] ?? 0);
if ($adminId <= 0) {
    return [
        'errors' => ['Session expired. Please login again.'],
        'success' => '',
        'profile' => null,
        'hasPhoneColumn' => $hasPhoneColumn,
        'hasProfilePhotoColumn' => $hasProfilePhotoColumn,
    ];
}

$fetchAdminProfile = static function (PDO $db, int $id, bool $hasPhone, bool $hasPhoto): ?array {
    $columns = [
        'id',
        'username',
        'email',
        'password',
        'role',
        'created_at',
        'updated_at',
    ];

    $columns[] = $hasPhone ? 'phone' : 'NULL AS phone';
    $columns[] = $hasPhoto ? 'profile_photo' : 'NULL AS profile_photo';

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM users WHERE id = ? LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    return $profile ?: null;
};

$validateImageUpload = static function (array $file, ?string &$error = null): bool {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Image upload failed. Please try again.';
        return false;
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $error = 'Invalid uploaded image file.';
        return false;
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
        $error = 'Please upload a valid image.';
        return false;
    }

    $maxBytes = 5 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        $error = 'Image size must be less than 5MB.';
        return false;
    }

    return true;
};

$profile = $fetchAdminProfile($pdo, $adminId, $hasPhoneColumn, $hasProfilePhotoColumn);
if (!$profile) {
    return [
        'errors' => ['Admin profile not found.'],
        'success' => '',
        'profile' => null,
        'hasPhoneColumn' => $hasPhoneColumn,
        'hasProfilePhotoColumn' => $hasProfilePhotoColumn,
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
            $errors[] = 'Please enter a valid email address.';
        }

        if ($hasPhoneColumn && $phone !== '') {
            if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
                $errors[] = 'Phone number format is invalid.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $stmt->execute([$username, $adminId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Username is already in use.';
            }

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $adminId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Email is already in use.';
            }
        }

        if (empty($errors)) {
            if ($hasPhoneColumn) {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->execute([$username, $email, $phone, $adminId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                $stmt->execute([$username, $email, $adminId]);
            }

            $_SESSION['username'] = $username;
            $success = 'Profile details updated successfully.';
        }
    }

    if ($action === 'update_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '') {
            $errors[] = 'Current password is required.';
        }
        if ($newPassword === '') {
            $errors[] = 'New password is required.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($confirmPassword === '') {
            $errors[] = 'Confirm password is required.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirm password do not match.';
        }

        if (empty($errors) && !password_verify($currentPassword, (string)$profile['password'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (empty($errors) && password_verify($newPassword, (string)$profile['password'])) {
            $errors[] = 'New password must be different from current password.';
        }

        if (empty($errors)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashed, $adminId]);
            $success = 'Password updated successfully.';
        }
    }

    if ($action === 'update_photo') {
        if (!$hasProfilePhotoColumn) {
            $errors[] = 'Profile photo is not supported in this database.';
        } else {
            $file = $_FILES['profile_photo'] ?? [];
            if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Please choose an image to upload.';
            } else {
                $uploadError = null;
                $isValid = $validateImageUpload($file, $uploadError);
                if (!$isValid) {
                    $errors[] = (string)$uploadError;
                }
            }

            if (empty($errors)) {
                $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo((string)$file['name'], PATHINFO_FILENAME));
                if (!$safeName) {
                    $safeName = 'admin_profile';
                }

                $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
                $ext = preg_replace('/[^a-z0-9]/', '', $ext ?? '');
                if ($ext === '') {
                    $ext = 'jpg';
                }
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'avif'];
                if (!in_array($ext, $allowedExt, true)) {
                    $ext = 'jpg';
                }

                $targetDir = __DIR__ . '/../../assets/images';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $filename = 'profile_admin_' . $adminId . '_' . time() . '_' . $safeName . '.' . $ext;
                $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

                if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
                    $errors[] = 'Failed to save uploaded image.';
                } else {
                    $dbPath = '../assets/images/' . $filename;
                    $stmt = $pdo->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
                    $stmt->execute([$dbPath, $adminId]);

                    $_SESSION['profile_pic'] = $dbPath;
                    $success = 'Profile photo updated successfully.';
                }
            }
        }
    }

    $profile = $fetchAdminProfile($pdo, $adminId, $hasPhoneColumn, $hasProfilePhotoColumn);
}

return [
    'errors' => $errors,
    'success' => $success,
    'profile' => $profile,
    'hasPhoneColumn' => $hasPhoneColumn,
    'hasProfilePhotoColumn' => $hasProfilePhotoColumn,
];
