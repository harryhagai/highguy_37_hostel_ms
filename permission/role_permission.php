<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('rp_normalize_role')) {
    function rp_normalize_role(?string $role): string
    {
        $normalized = strtolower(trim((string)$role));
        return $normalized === '' ? 'user' : $normalized;
    }
}

if (!function_exists('rp_redirect')) {
    function rp_redirect(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}

if (!function_exists('rp_require_login')) {
    function rp_require_login(string $loginPath = '../auth/login.php'): void
    {
        if (empty($_SESSION['user_id'])) {
            rp_redirect($loginPath);
        }

        $_SESSION['role'] = rp_normalize_role($_SESSION['role'] ?? 'user');
        rp_touch_last_seen((int)$_SESSION['user_id']);
    }
}

if (!function_exists('rp_touch_last_seen')) {
    function rp_touch_last_seen(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return;
        }

        static $lastTouch = 0;
        if ((time() - $lastTouch) < 15) {
            return;
        }

        static $hasLastSeenColumn = null;
        if ($hasLastSeenColumn === null) {
            $stmt = $GLOBALS['pdo']->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['users', 'last_seen_at']);
            $hasLastSeenColumn = (int)$stmt->fetchColumn() > 0;
        }

        if (!$hasLastSeenColumn) {
            return;
        }

        $stmt = $GLOBALS['pdo']->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
        $lastTouch = time();
    }
}

if (!function_exists('rp_require_roles')) {
    function rp_require_roles(array $allowedRoles, string $loginPath = '../auth/login.php'): void
    {
        rp_require_login($loginPath);

        $allowed = array_values(array_unique(array_map('rp_normalize_role', $allowedRoles)));
        $currentRole = rp_normalize_role($_SESSION['role'] ?? 'user');

        if (in_array($currentRole, $allowed, true)) {
            return;
        }

        if ($currentRole === 'admin') {
            rp_redirect('../admin/admin_dashboard_layout.php');
        }

        if ($currentRole === 'user') {
            rp_redirect('../user/user_dashboard_layout.php');
        }

        $_SESSION = [];
        session_destroy();
        rp_redirect($loginPath);
    }
}
