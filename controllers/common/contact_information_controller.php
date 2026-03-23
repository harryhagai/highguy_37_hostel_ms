<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/contact_helpers.php';

$tableReady = contact_ensure_contact_information_table($pdo);
$contactInformation = contact_fetch_active_information($pdo);

return [
    'table_ready' => $tableReady,
    'contact_information' => $contactInformation,
];
