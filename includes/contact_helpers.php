<?php
if (!function_exists('contact_table_exists')) {
    function contact_table_exists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('contact_column_exists')) {
    function contact_column_exists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('contact_ensure_column')) {
    function contact_ensure_column(PDO $db, string $table, string $column, string $definition): bool
    {
        if (contact_column_exists($db, $table, $column)) {
            return true;
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }

        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            // Ignore and verify below.
        }

        return contact_column_exists($db, $table, $column);
    }
}

if (!function_exists('contact_ensure_contact_information_table')) {
    function contact_ensure_contact_information_table(PDO $db): bool
    {
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS contact_information (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    phone VARCHAR(30) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    location VARCHAR(190) NOT NULL,
                    working_hours VARCHAR(190) NOT NULL,
                    support_note VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_contact_information_active (is_active),
                    KEY idx_contact_information_updated (updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            return false;
        }

        $requiredColumns = [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'phone' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'email' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'location' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'working_hours' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'support_note' => 'VARCHAR(255) DEFAULT NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'created_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (!contact_ensure_column($db, 'contact_information', $column, $definition)) {
                return false;
            }
        }

        return contact_table_exists($db, 'contact_information');
    }
}

if (!function_exists('contact_ensure_contact_messages_table')) {
    function contact_ensure_contact_messages_table(PDO $db): bool
    {
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS contact_messages (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    full_name VARCHAR(120) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    phone VARCHAR(30) DEFAULT NULL,
                    topic VARCHAR(160) DEFAULT NULL,
                    message TEXT NOT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent VARCHAR(255) DEFAULT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'new',
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_contact_messages_status (status),
                    KEY idx_contact_messages_created (created_at),
                    KEY idx_contact_messages_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            return false;
        }

        $requiredColumns = [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'full_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
            'email' => "VARCHAR(190) NOT NULL DEFAULT ''",
            'phone' => 'VARCHAR(30) DEFAULT NULL',
            'topic' => 'VARCHAR(160) DEFAULT NULL',
            'message' => 'TEXT',
            'ip_address' => 'VARCHAR(45) DEFAULT NULL',
            'user_agent' => 'VARCHAR(255) DEFAULT NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'new'",
            'created_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (!contact_ensure_column($db, 'contact_messages', $column, $definition)) {
                return false;
            }
        }

        return contact_table_exists($db, 'contact_messages');
    }
}

if (!function_exists('contact_fetch_active_information')) {
    function contact_fetch_active_information(PDO $db): array
    {
        $defaults = [
            'id' => 0,
            'phone' => '+254 700 123456',
            'email' => 'support@hostelpro.com',
            'location' => 'Dar-es-saalam, Tanzania',
            'working_hours' => 'Mon - Sat, 8:00 AM - 6:00 PM',
            'support_note' => 'Talk to us anytime. Our team is ready to help you with hostel selection and booking support.',
            'is_active' => 1,
        ];

        if (!contact_ensure_contact_information_table($db)) {
            return $defaults;
        }

        try {
            $stmt = $db->query(
                'SELECT id, phone, email, location, working_hours, support_note, is_active
                 FROM contact_information
                 ORDER BY COALESCE(is_active, 0) DESC, updated_at DESC, id DESC
                 LIMIT 1'
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $defaults;
        }

        if (!$row) {
            return $defaults;
        }

        $result = $defaults;
        $result['id'] = (int)($row['id'] ?? 0);
        $result['is_active'] = (int)($row['is_active'] ?? 0) === 1 ? 1 : 0;

        $fields = ['phone', 'email', 'location', 'working_hours', 'support_note'];
        foreach ($fields as $field) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value !== '') {
                $result[$field] = $value;
            }
        }

        return $result;
    }
}

if (!function_exists('contact_normalize_message_status')) {
    function contact_normalize_message_status(?string $value): string
    {
        $status = strtolower(trim((string)$value));
        $allowed = ['new', 'read', 'replied', 'resolved'];
        if (in_array($status, $allowed, true)) {
            return $status;
        }

        if (in_array($status, ['in_progress', 'open'], true)) {
            return 'read';
        }
        if (in_array($status, ['done', 'closed'], true)) {
            return 'resolved';
        }

        return 'new';
    }
}
