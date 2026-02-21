<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../controllers/auth/remember_me.php';
require_once __DIR__ . '/../controllers/common/activity_logger.php';

$logoutUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($logoutUserId > 0) {
    try {
        activity_log($pdo, $logoutUserId, 'logout', $logoutUserId);
    } catch (Throwable $e) {
        // Ignore logging issues during logout.
    }
}

try {
    auth_remember_revoke_current_token($pdo);
} catch (Throwable $e) {
    auth_remember_clear_cookie();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();
header('Location: login.php');
exit;
?>
