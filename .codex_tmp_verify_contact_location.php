<?php
require __DIR__ . '/includes/contact_helpers.php';
require __DIR__ . '/config/db_connection.php';
$r = contact_fetch_active_information($pdo);
echo ($r['location'] ?? 'NO_LOCATION') . PHP_EOL;
