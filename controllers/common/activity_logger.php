<?php
if (!function_exists('activity_ensure_table')) {
    function activity_ensure_table(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_activity_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                actor_user_id INT NULL,
                action VARCHAR(64) NOT NULL,
                details TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_activity_user (user_id),
                INDEX idx_activity_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $ready = true;
    }
}

if (!function_exists('activity_log')) {
    function activity_log(PDO $pdo, int $userId, string $action, ?int $actorUserId = null, array $details = []): void
    {
        if ($userId <= 0 || $action === '') {
            return;
        }

        activity_ensure_table($pdo);

        $payload = null;
        if (!empty($details)) {
            $payload = json_encode($details, JSON_UNESCAPED_SLASHES);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO user_activity_logs (user_id, actor_user_id, action, details, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $actorUserId, $action, $payload]);
    }
}

if (!function_exists('activity_fetch_for_users')) {
    function activity_fetch_for_users(PDO $pdo, array $userIds, int $limitPerUser = 5): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds), static function (int $id): bool {
            return $id > 0;
        }));

        if (empty($userIds)) {
            return [];
        }

        activity_ensure_table($pdo);

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT user_id, actor_user_id, action, details, created_at
             FROM user_activity_logs
             WHERE user_id IN ($placeholders)
             ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute($userIds);

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int)$row['user_id'];
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [];
            }
            if (count($grouped[$uid]) >= $limitPerUser) {
                continue;
            }

            $detailText = '';
            $decoded = json_decode((string)($row['details'] ?? ''), true);
            if (is_array($decoded) && !empty($decoded)) {
                $pairs = [];
                foreach ($decoded as $key => $value) {
                    $pairs[] = $key . ': ' . (is_scalar($value) ? (string)$value : json_encode($value));
                }
                $detailText = implode(', ', $pairs);
            }

            $grouped[$uid][] = [
                'action' => (string)$row['action'],
                'created_at' => (string)$row['created_at'],
                'details' => $detailText,
                'actor_user_id' => $row['actor_user_id'] !== null ? (int)$row['actor_user_id'] : null,
            ];
        }

        return $grouped;
    }
}
