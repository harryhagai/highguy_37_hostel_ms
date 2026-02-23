<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

require_once __DIR__ . '/../common/activity_logger.php';
require_once __DIR__ . '/../../includes/admin_post_guard.php';

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

$flash = admin_prg_consume('manage_users');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
    $openModal = (string)($flash['openModal'] ?? '');
    $editFormData = is_array($flash['editFormData'] ?? null) ? $flash['editFormData'] : null;
}

$actorUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$userColumnExists = static function (PDO $db, string $column): bool {
    static $cache = [];
    $key = 'users.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['users', $column]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
};

$ensureUserColumn = static function (PDO $db, string $column, string $definition) use ($userColumnExists): bool {
    if ($userColumnExists($db, $column)) {
        return true;
    }

    try {
        $db->exec('ALTER TABLE users ADD COLUMN ' . $column . ' ' . $definition);
    } catch (Throwable $e) {
        return $userColumnExists($db, $column);
    }

    return $userColumnExists($db, $column);
};

$hasStatusColumn = $ensureUserColumn($pdo, 'status', "ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
$hasLastLoginColumn = $ensureUserColumn($pdo, 'last_login_at', 'DATETIME NULL');
$hasLastSeenColumn = $ensureUserColumn($pdo, 'last_seen_at', 'DATETIME NULL');
$hasProfilePhotoColumn = $userColumnExists($pdo, 'profile_photo');
$hasGenderColumn = $userColumnExists($pdo, 'gender');

$normalizeGender = static function (?string $value): string {
    $gender = strtolower(trim((string)$value));
    if (!in_array($gender, ['male', 'female'], true)) {
        return '';
    }
    return $gender;
};

$countAdmins = static function (PDO $db): int {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    return (int)$stmt->fetchColumn();
};

$countActiveAdmins = static function (PDO $db) use ($hasStatusColumn): int {
    if (!$hasStatusColumn) {
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        return (int)$stmt->fetchColumn();
    }

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status <> 'suspended'");
    return (int)$stmt->fetchColumn();
};

$humanDate = static function (?string $date): string {
    if (!$date) {
        return '-';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '-';
    }
    return date('d M Y H:i', $timestamp);
};

$deriveStatus = static function (array $user) use ($hasStatusColumn): string {
    $status = strtolower(trim((string)($user['status'] ?? '')));
    if ($hasStatusColumn && in_array($status, ['active', 'inactive', 'suspended'], true)) {
        return $status;
    }

    $lastSeen = (string)($user['last_seen_at'] ?? '');
    if ($lastSeen !== '' && strtotime($lastSeen) >= strtotime('-15 minutes')) {
        return 'active';
    }

    return 'inactive';
};

$defaultAvatar = '../assets/images/prof.jpg';
$avatarPath = static function (array $user) use ($hasProfilePhotoColumn, $defaultAvatar): string {
    if (!$hasProfilePhotoColumn) {
        return $defaultAvatar;
    }

    $raw = trim((string)($user['profile_photo'] ?? ''));
    if ($raw === '') {
        return $defaultAvatar;
    }

    if (strpos($raw, '../') === 0 || strpos($raw, './') === 0 || strpos($raw, 'http://') === 0 || strpos($raw, 'https://') === 0) {
        return $raw;
    }

    return '../' . ltrim($raw, '/');
};

$initials = static function (string $username): string {
    $clean = trim($username);
    if ($clean === '') {
        return 'U';
    }
    $parts = preg_split('/\s+/', $clean);
    if (!$parts || count($parts) === 0) {
        return strtoupper(substr($clean, 0, 1));
    }
    $first = strtoupper(substr((string)$parts[0], 0, 1));
    $second = count($parts) > 1 ? strtoupper(substr((string)$parts[1], 0, 1)) : strtoupper(substr($clean, 1, 1));
    return trim($first . $second) !== '' ? trim($first . $second) : 'U';
};

$sendPasswordReset = static function (PDO $db, int $userId, string $email, string $username) use (&$errors, &$success, $actorUserId): bool {
    if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid user or email for password reset.';
        return false;
    }

    $temporaryPassword = substr(bin2hex(random_bytes(8)), 0, 12);
    $hashed = password_hash($temporaryPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hashed, $userId]);

    $subject = 'HostelPro Password Reset';
    $message = "Hello {$username},\n\nYour password has been reset by admin.\nTemporary password: {$temporaryPassword}\n\nPlease log in and change your password immediately.";
    $headers = "From: no-reply@hostelpro.local\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $mailSent = @mail($email, $subject, $message, $headers);

    activity_log($db, $userId, 'password_reset', $actorUserId, [
        'mail_sent' => $mailSent ? 'yes' : 'no',
    ]);

    if ($mailSent) {
        $success = 'Password reset email sent successfully.';
    } else {
        $success = 'Mail service failed. Temporary password for ' . $username . ': ' . $temporaryPassword;
    }

    return true;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $normalizeGender($_POST['gender'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $status = strtolower(trim((string)($_POST['status'] ?? 'active')));
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_]+(?: [A-Za-z0-9_]+)*$/', $username)) {
            $errors[] = 'Username can include letters, numbers, underscores, and spaces between words.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($hasGenderColumn && $gender === '') {
            $errors[] = 'Select a valid gender (Male or Female).';
        }
        if ($hasStatusColumn && !in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists.';
        }

        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            if ($hasStatusColumn) {
                if ($hasGenderColumn) {
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone, gender, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$username, $email, $hashed, $phone, $gender, $role, $status]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone, role, status) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$username, $email, $hashed, $phone, $role, $status]);
                }
            } else {
                if ($hasGenderColumn) {
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone, gender, role) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$username, $email, $hashed, $phone, $gender, $role]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone, role) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$username, $email, $hashed, $phone, $role]);
                }
            }
            $newUserId = (int)$pdo->lastInsertId();
            activity_log($pdo, $newUserId, 'user_created', $actorUserId, ['role' => $role]);
            $success = 'User added successfully.';
        } else {
            $openModal = 'addUserModal';
        }
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $normalizeGender($_POST['gender'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $status = strtolower(trim((string)($_POST['status'] ?? 'active')));
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_]+(?: [A-Za-z0-9_]+)*$/', $username)) {
            $errors[] = 'Username can include letters, numbers, underscores, and spaces between words.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
        $stmt->execute([$username, $email, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists.';
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($hasGenderColumn && $gender === '') {
            $errors[] = 'Select a valid gender (Male or Female).';
        }
        if ($hasStatusColumn && !in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $errors[] = 'Invalid status selected.';
        }
        if ($id === (int)($actorUserId ?? 0) && $hasStatusColumn && $status === 'suspended') {
            $errors[] = 'You cannot suspend your own account.';
        }
        if ($id === (int)($actorUserId ?? 0) && $role !== 'admin') {
            $errors[] = 'You cannot remove your own admin role.';
        }
        if ($role !== 'admin' && $countAdmins($pdo) <= 1) {
            $adminCheck = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $adminCheck->execute([$id]);
            $targetRole = (string)$adminCheck->fetchColumn();
            if ($targetRole === 'admin') {
                $errors[] = 'At least one admin must remain.';
            }
        }

        if (empty($errors)) {
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                if ($hasStatusColumn) {
                    if ($hasGenderColumn) {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, gender = ?, role = ?, status = ?, password = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $gender, $role, $status, $hashed, $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $role, $status, $hashed, $id]);
                    }
                } else {
                    if ($hasGenderColumn) {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, gender = ?, role = ?, password = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $gender, $role, $hashed, $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $role, $hashed, $id]);
                    }
                }
            } else {
                if ($hasStatusColumn) {
                    if ($hasGenderColumn) {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, gender = ?, role = ?, status = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $gender, $role, $status, $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $role, $status, $id]);
                    }
                } else {
                    if ($hasGenderColumn) {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, gender = ?, role = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $gender, $role, $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, role = ? WHERE id = ?');
                        $stmt->execute([$username, $email, $phone, $role, $id]);
                    }
                }
            }
            activity_log($pdo, $id, 'user_updated', $actorUserId, ['role' => $role, 'status' => $status]);
            $success = 'User updated successfully.';
        } else {
            $openModal = 'editUserModal';
            $editFormData = [
                'id' => $id,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'gender' => $gender,
                'role' => $role,
                'status' => $status,
            ];
        }
    }

    if ($action === 'disable_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($id === (int)($actorUserId ?? 0)) {
                $errors[] = 'You cannot disable your own account.';
            } else {
                $roleCheck = $pdo->prepare('SELECT role, status FROM users WHERE id = ?');
                $roleCheck->execute([$id]);
                $target = $roleCheck->fetch(PDO::FETCH_ASSOC);
                if (!$target) {
                    $errors[] = 'Selected user not found.';
                } else {
                    $targetRole = (string)($target['role'] ?? 'user');
                    $targetStatus = strtolower(trim((string)($target['status'] ?? 'active')));

                    if ($targetStatus === 'suspended') {
                        $success = 'User is already disabled.';
                    } elseif ($targetRole === 'admin' && $countActiveAdmins($pdo) <= 1) {
                        $errors[] = 'Cannot disable the last active admin account.';
                    } else {
                        if ($hasStatusColumn) {
                            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
                            $stmt->execute(['suspended', $id]);
                            activity_log($pdo, $id, 'user_disabled', $actorUserId);
                            $success = 'User account disabled successfully.';
                        } else {
                            $errors[] = 'Status column is not available. Unable to disable account.';
                        }
                    }
                }
            }
        }
    }

    if ($action === 'send_password_reset') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                $errors[] = 'Selected user not found.';
            } else {
                $sendPasswordReset($pdo, $id, (string)$target['email'], (string)$target['username']);
            }
        }
    }

    if ($action === 'bulk_users') {
        $bulkType = trim((string)($_POST['bulk_action_type'] ?? ''));
        $ids = $_POST['selected_user_ids'] ?? [];
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), static function (int $id): bool {
            return $id > 0;
        })));

        if ($bulkType === '' || empty($selectedIds)) {
            $errors[] = 'Select users and choose a bulk action.';
        } elseif ($bulkType === 'delete') {
            $errors[] = 'This action is disabled for security. Use Disable (Suspended) instead.';
        } else {
            $updated = 0;
            $mailProcessed = 0;

            foreach ($selectedIds as $id) {
                if ($bulkType === 'make_admin' || $bulkType === 'make_user') {
                    if ($bulkType === 'make_user' && $id === (int)($actorUserId ?? 0)) {
                        continue;
                    }
                    if ($bulkType === 'make_user') {
                        $roleCheck = $pdo->prepare('SELECT role FROM users WHERE id = ?');
                        $roleCheck->execute([$id]);
                        $targetRole = (string)$roleCheck->fetchColumn();
                        if ($targetRole === 'admin' && $countAdmins($pdo) <= 1) {
                            continue;
                        }
                    }
                    $newRole = $bulkType === 'make_admin' ? 'admin' : 'user';
                    $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                    $stmt->execute([$newRole, $id]);
                    activity_log($pdo, $id, 'role_changed_bulk', $actorUserId, ['role' => $newRole]);
                    $updated++;
                    continue;
                }

                if (in_array($bulkType, ['set_active', 'set_inactive', 'set_suspended'], true)) {
                    if (!$hasStatusColumn) {
                        continue;
                    }
                    $newStatus = str_replace('set_', '', $bulkType);
                    if ($id === (int)($actorUserId ?? 0) && $newStatus === 'suspended') {
                        continue;
                    }
                    if ($newStatus === 'suspended') {
                        $roleCheck = $pdo->prepare('SELECT role, status FROM users WHERE id = ?');
                        $roleCheck->execute([$id]);
                        $target = $roleCheck->fetch(PDO::FETCH_ASSOC);
                        $targetRole = (string)($target['role'] ?? 'user');
                        $targetStatus = strtolower(trim((string)($target['status'] ?? 'active')));
                        if ($targetRole === 'admin' && $targetStatus !== 'suspended' && $countActiveAdmins($pdo) <= 1) {
                            continue;
                        }
                    }
                    $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
                    $stmt->execute([$newStatus, $id]);
                    activity_log($pdo, $id, 'status_changed_bulk', $actorUserId, ['status' => $newStatus]);
                    $updated++;
                    continue;
                }

                if ($bulkType === 'send_reset') {
                    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
                    $stmt->execute([$id]);
                    $target = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($target && $sendPasswordReset($pdo, $id, (string)$target['email'], (string)$target['username'])) {
                        $mailProcessed++;
                    }
                }
            }

            if ($bulkType === 'send_reset') {
                $success = $mailProcessed . ' password reset request(s) processed.';
            } else {
                $success = $updated . ' user(s) updated.';
            }
        }
    }

    admin_prg_redirect('manage_users', [
        'errors' => $errors,
        'success' => $success,
        'openModal' => $openModal,
        'editFormData' => $editFormData,
    ]);
}

$selectColumns = ['id', 'username', 'email', 'phone', 'role', 'created_at'];
if ($hasGenderColumn) {
    $selectColumns[] = 'gender';
}
if ($hasStatusColumn) {
    $selectColumns[] = 'status';
}
if ($hasLastLoginColumn) {
    $selectColumns[] = 'last_login_at';
}
if ($hasLastSeenColumn) {
    $selectColumns[] = 'last_seen_at';
}
if ($hasProfilePhotoColumn) {
    $selectColumns[] = 'profile_photo';
}

$usersQuery = 'SELECT ' . implode(', ', $selectColumns) . ' FROM users ORDER BY id DESC';
$users = $pdo->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);

$activityMap = [];
try {
    $activityMap = activity_fetch_for_users($pdo, array_map(static fn(array $u): int => (int)$u['id'], $users), 5);
} catch (Throwable $e) {
    $activityMap = [];
}

$stats = [
    'total_users' => 0,
    'new_today' => 0,
    'new_week' => 0,
    'active_now' => 0,
    'suspended_users' => 0,
];

$todayStart = strtotime('today');
$weekStart = strtotime('-6 days 00:00:00');
$nowMinus15 = strtotime('-15 minutes');

foreach ($users as &$user) {
    $user['role'] = (($user['role'] ?? 'user') === 'admin') ? 'admin' : 'user';
    $user['gender'] = $normalizeGender($user['gender'] ?? '');
    $user['gender_label'] = $user['gender'] === 'male'
        ? 'Male'
        : ($user['gender'] === 'female' ? 'Female' : '-');
    $user['status'] = $deriveStatus($user);
    $user['avatar_url'] = $avatarPath($user);
    $user['avatar_initials'] = $initials((string)($user['username'] ?? ''));
    $user['created_at_display'] = $humanDate($user['created_at'] ?? null);
    $user['last_login_display'] = $humanDate($user['last_login_at'] ?? null);
    $user['recent_activity'] = $activityMap[(int)$user['id']] ?? [];

    $stats['total_users']++;

    $createdTs = strtotime((string)($user['created_at'] ?? ''));
    if ($createdTs && $createdTs >= $todayStart) {
        $stats['new_today']++;
    }
    if ($createdTs && $createdTs >= $weekStart) {
        $stats['new_week']++;
    }

    if ($user['status'] === 'suspended') {
        $stats['suspended_users']++;
    }

    $lastSeenTs = strtotime((string)($user['last_seen_at'] ?? ''));
    if ($lastSeenTs && $lastSeenTs >= $nowMinus15 && $user['status'] !== 'suspended') {
        $stats['active_now']++;
    }
}
unset($user);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'users' => $users,
    'stats' => $stats,
    'supports' => [
        'status' => $hasStatusColumn,
        'last_login' => $hasLastLoginColumn,
        'last_seen' => $hasLastSeenColumn,
        'gender' => $hasGenderColumn,
    ],
];
