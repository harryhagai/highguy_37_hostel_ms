<?php
require __DIR__ . '/config/db_connection.php';
try {
    $stmt = $pdo->query("SELECT id, location, is_active FROM contact_information ORDER BY COALESCE(is_active,0) DESC, updated_at DESC, id DESC LIMIT 3");
    if (!$stmt) {
        echo "NO_TABLE\n";
        exit;
    }

    foreach ($stmt as $r) {
        echo $r['id'] . '|' . $r['location'] . '|' . $r['is_active'] . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
?>
