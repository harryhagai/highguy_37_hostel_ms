<?php
if (!function_exists('auth_throttle_table_exists')) {
    function auth_throttle_table_exists(PDO $pdo): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(['auth_attempt_locks']);
        $cached = (int)$stmt->fetchColumn() > 0;
        return $cached;
    }
}

if (!function_exists('auth_throttle_assert_table')) {
    function auth_throttle_assert_table(PDO $pdo): void
    {
        if (!auth_throttle_table_exists($pdo)) {
            throw new RuntimeException('Table auth_attempt_locks is missing. Run SQL migration first.');
        }
    }
}

if (!function_exists('auth_throttle_identifier')) {
    function auth_throttle_identifier(string $rawIdentity, string $ipAddress = ''): string
    {
        $identity = strtolower(trim($rawIdentity));
        if ($identity !== '') {
            return substr($identity, 0, 190);
        }

        $ip = trim($ipAddress);
        if ($ip === '') {
            $ip = 'unknown';
        }

        return substr('__ip__:' . $ip, 0, 190);
    }
}

if (!function_exists('auth_throttle_get')) {
    function auth_throttle_get(PDO $pdo, string $action, string $identifier): ?array
    {
        auth_throttle_assert_table($pdo);

        $stmt = $pdo->prepare(
            'SELECT id, attempt_count, locked_until
             FROM auth_attempt_locks
             WHERE action_name = ? AND identifier = ?
             LIMIT 1'
        );
        $stmt->execute([$action, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('auth_throttle_is_locked')) {
    function auth_throttle_is_locked(PDO $pdo, string $action, string $identifier): array
    {
        $row = auth_throttle_get($pdo, $action, $identifier);
        if (!$row) {
            return ['locked' => false, 'locked_until' => null];
        }

        $lockedUntil = (string)($row['locked_until'] ?? '');
        if ($lockedUntil === '') {
            return ['locked' => false, 'locked_until' => null];
        }

        if (strtotime($lockedUntil) <= time()) {
            $stmt = $pdo->prepare(
                'UPDATE auth_attempt_locks
                 SET attempt_count = 0, locked_until = NULL, first_attempt_at = NULL, last_attempt_at = NULL
                 WHERE id = ?'
            );
            $stmt->execute([(int)$row['id']]);
            return ['locked' => false, 'locked_until' => null];
        }

        return ['locked' => true, 'locked_until' => $lockedUntil];
    }
}

if (!function_exists('auth_throttle_register_failure')) {
    function auth_throttle_register_failure(
        PDO $pdo,
        string $action,
        string $identifier,
        int $maxAttempts = 3,
        int $lockSeconds = 10800
    ): array {
        auth_throttle_assert_table($pdo);

        $row = auth_throttle_get($pdo, $action, $identifier);
        $now = date('Y-m-d H:i:s');

        if (!$row) {
            $attemptCount = 1;
            $lockedUntil = $attemptCount >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockSeconds) : null;

            $insert = $pdo->prepare(
                'INSERT INTO auth_attempt_locks
                    (action_name, identifier, attempt_count, first_attempt_at, last_attempt_at, locked_until)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$action, $identifier, $attemptCount, $now, $now, $lockedUntil]);

            return [
                'attempt_count' => $attemptCount,
                'locked_until' => $lockedUntil,
            ];
        }

        $currentLockedUntil = (string)($row['locked_until'] ?? '');
        $attemptCount = (int)($row['attempt_count'] ?? 0);
        $firstAttemptAt = $now;

        if ($currentLockedUntil !== '' && strtotime($currentLockedUntil) > time()) {
            return [
                'attempt_count' => $attemptCount,
                'locked_until' => $currentLockedUntil,
            ];
        }

        if ($currentLockedUntil !== '' && strtotime($currentLockedUntil) <= time()) {
            $attemptCount = 0;
        }

        $attemptCount++;
        $lockedUntil = $attemptCount >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockSeconds) : null;

        if ($attemptCount > 1) {
            $fetchFirst = $pdo->prepare('SELECT first_attempt_at FROM auth_attempt_locks WHERE id = ? LIMIT 1');
            $fetchFirst->execute([(int)$row['id']]);
            $firstAttemptAtDb = (string)$fetchFirst->fetchColumn();
            if ($firstAttemptAtDb !== '') {
                $firstAttemptAt = $firstAttemptAtDb;
            }
        }

        $update = $pdo->prepare(
            'UPDATE auth_attempt_locks
             SET attempt_count = ?, first_attempt_at = ?, last_attempt_at = ?, locked_until = ?
             WHERE id = ?'
        );
        $update->execute([$attemptCount, $firstAttemptAt, $now, $lockedUntil, (int)$row['id']]);

        return [
            'attempt_count' => $attemptCount,
            'locked_until' => $lockedUntil,
        ];
    }
}

if (!function_exists('auth_throttle_clear')) {
    function auth_throttle_clear(PDO $pdo, string $action, string $identifier): void
    {
        auth_throttle_assert_table($pdo);
        $stmt = $pdo->prepare('DELETE FROM auth_attempt_locks WHERE action_name = ? AND identifier = ?');
        $stmt->execute([$action, $identifier]);
    }
}

if (!function_exists('auth_throttle_lock_message')) {
    function auth_throttle_lock_message(string $context, ?string $lockedUntil): string
    {
        if (!$lockedUntil) {
            return 'Too many failed attempts. Please try again later.';
        }

        $ts = strtotime($lockedUntil);
        if ($ts === false) {
            return 'Too many failed attempts. Please try again later.';
        }

        return 'Too many failed ' . $context . ' attempts. Try again after ' . date('d M Y H:i', $ts) . '.';
    }
}
