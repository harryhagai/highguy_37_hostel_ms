<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/remember_me.php';
require_once __DIR__ . '/../common/activity_logger.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$redirectByRole = static function (string $role): void {
    if ($role === 'admin') {
        header('Location: ../admin/admin_dashboard_layout.php');
        exit;
    }

    header('Location: ../user/user_dashboard_layout.php');
    exit;
};

$userColumnExists = static function (PDO $pdo, string $column): bool {
    static $cache = [];
    $key = 'users.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['users', $column]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
};

$markLogin = static function (PDO $pdo, int $userId, bool $fullLogin = true) use ($userColumnExists): void {
    if ($userId <= 0) {
        return;
    }

    $setParts = [];
    if ($userColumnExists($pdo, 'last_seen_at')) {
        $setParts[] = 'last_seen_at = NOW()';
    }
    if ($fullLogin && $userColumnExists($pdo, 'last_login_at')) {
        $setParts[] = 'last_login_at = NOW()';
    }

    if (!empty($setParts)) {
        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    }
};

$setSessionFromUser = static function (array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['role'] = (string)$user['role'];
};

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    $redirectByRole((string)$_SESSION['role']);
}

try {
    $rememberedUser = auth_remember_try_login($pdo);
    if ($rememberedUser) {
        if ($userColumnExists($pdo, 'status')) {
            $statusStmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
            $statusStmt->execute([(int)$rememberedUser['id']]);
            $statusValue = strtolower(trim((string)$statusStmt->fetchColumn()));
            if ($statusValue === 'suspended') {
                auth_remember_revoke_current_token($pdo);
                $rememberedUser = null;
            }
        }
    }

    if ($rememberedUser) {
        $setSessionFromUser($rememberedUser);
        $markLogin($pdo, (int)$rememberedUser['id'], false);
        activity_log($pdo, (int)$rememberedUser['id'], 'login_remember', (int)$rememberedUser['id']);
        $redirectByRole((string)$rememberedUser['role']);
    }
} catch (Throwable $e) {
    auth_remember_clear_cookie();
}

$errors = [];
$email = '';
$successMessage = null;
$rememberMe = false;

if (!empty($_SESSION['success'])) {
    $successMessage = (string) $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Invalid CSRF token.';
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        $selectSql = 'SELECT id, username, email, password, role';
        if ($userColumnExists($pdo, 'status')) {
            $selectSql .= ', status';
        }
        $selectSql .= ' FROM users WHERE email = ?';

        $stmt = $pdo->prepare($selectSql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $status = strtolower(trim((string)($user['status'] ?? 'active')));
            if ($status === 'suspended') {
                $errors[] = 'Your account is suspended. Contact admin.';
            } else {
                $setSessionFromUser($user);
                $markLogin($pdo, (int)$user['id'], true);
                activity_log($pdo, (int)$user['id'], 'login_password', (int)$user['id']);

                try {
                    if ($rememberMe) {
                        auth_remember_revoke_current_token($pdo);
                        auth_remember_issue_token($pdo, (int)$user['id']);
                    } else {
                        auth_remember_revoke_current_token($pdo);
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Unable to complete remember me setup. Please try again.';
                    $_SESSION = [];
                }

                if (empty($errors)) {
                    $redirectByRole((string)$user['role']);
                }
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

return [
    'errors' => $errors,
    'email' => $email,
    'remember_me' => $rememberMe,
    'csrf_token' => $_SESSION['csrf_token'],
    'success_message' => $successMessage
];
