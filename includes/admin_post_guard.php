<?php
if (!function_exists('admin_prg_consume')) {
    function admin_prg_consume(string $scope): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $bucket = $_SESSION['admin_prg_flash'] ?? null;
        if (!is_array($bucket) || !array_key_exists($scope, $bucket)) {
            return null;
        }

        $payload = $bucket[$scope];
        unset($bucket[$scope]);
        $_SESSION['admin_prg_flash'] = $bucket;

        return is_array($payload) ? $payload : null;
    }
}

if (!function_exists('admin_prg_redirect')) {
    function admin_prg_redirect(string $scope, array $payload = []): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $bucket = $_SESSION['admin_prg_flash'] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        $bucket[$scope] = $payload;
        $_SESSION['admin_prg_flash'] = $bucket;

        if (headers_sent()) {
            return;
        }

        $requestUri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($requestUri === '') {
            $requestUri = './admin_dashboard_layout.php';
        }

        header('Location: ' . $requestUri, true, 303);
        exit;
    }
}
