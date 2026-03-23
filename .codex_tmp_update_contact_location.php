<?php
require __DIR__ . '/config/db_connection.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_information (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        phone VARCHAR(30) NOT NULL,
        email VARCHAR(190) NOT NULL,
        location VARCHAR(190) NOT NULL,
        working_hours VARCHAR(190) NOT NULL,
        support_note VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // ignore create compatibility errors if structure already exists
}

try {
    $stmt = $pdo->prepare("UPDATE contact_information SET location = ? WHERE TRIM(location) = ?");
    $stmt->execute(['Dar-es-saalam, Tanzania', 'Nairobi, Kenya']);
    echo 'UPDATED_ROWS=' . $stmt->rowCount() . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR=' . $e->getMessage() . PHP_EOL;
}
?>
