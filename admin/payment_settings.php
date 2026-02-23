<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

if (!headers_sent()) {
    header('Location: admin_dashboard_layout.php?page=settings&settings_tab=payment', true, 302);
    exit;
}

echo '<script>window.location.href="admin_dashboard_layout.php?page=settings&settings_tab=payment";</script>';