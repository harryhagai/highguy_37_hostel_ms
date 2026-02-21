<?php
if (!function_exists('auth_remember_cookie_name')) {
    function auth_remember_cookie_name(): string
    {
        return 'hostel_remember';
    }
}

if (!function_exists('auth_remember_cookie_is_secure')) {
    function auth_remember_cookie_is_secure(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}

if (!function_exists('auth_remember_set_cookie')) {
    function auth_remember_set_cookie(string $value, int $expiresAt): void
    {
        setcookie(auth_remember_cookie_name(), $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => auth_remember_cookie_is_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('auth_remember_clear_cookie')) {
    function auth_remember_clear_cookie(): void
    {
        setcookie(auth_remember_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => auth_remember_cookie_is_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[auth_remember_cookie_name()]);
    }
}

if (!function_exists('auth_remember_ensure_table')) {
    function auth_remember_ensure_table(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS remember_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                selector VARCHAR(24) NOT NULL UNIQUE,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL,
                user_agent VARCHAR(255) NULL,
                INDEX idx_remember_user_id (user_id),
                INDEX idx_remember_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $ready = true;
    }
}

if (!function_exists('auth_remember_parse_cookie')) {
    function auth_remember_parse_cookie(): ?array
    {
        $cookieValue = (string)($_COOKIE[auth_remember_cookie_name()] ?? '');
        if ($cookieValue === '' || strpos($cookieValue, ':') === false) {
            return null;
        }

        [$selector, $validator] = explode(':', $cookieValue, 2);
        if ($selector === '' || $validator === '') {
            return null;
        }

        if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
            return null;
        }

        return [$selector, $validator];
    }
}

if (!function_exists('auth_remember_revoke_current_token')) {
    function auth_remember_revoke_current_token(PDO $pdo): void
    {
        $parts = auth_remember_parse_cookie();
        if ($parts) {
            auth_remember_ensure_table($pdo);
            $selector = $parts[0];
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $stmt->execute([$selector]);
        }

        auth_remember_clear_cookie();
    }
}

if (!function_exists('auth_remember_issue_token')) {
    function auth_remember_issue_token(PDO $pdo, int $userId): void
    {
        auth_remember_ensure_table($pdo);

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresAtTimestamp = time() + (30 * 24 * 60 * 60);
        $expiresAtDb = date('Y-m-d H:i:s', $expiresAtTimestamp);
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);

        $stmt = $pdo->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $selector, $tokenHash, $expiresAtDb, $userAgent]);

        auth_remember_set_cookie($selector . ':' . $validator, $expiresAtTimestamp);
    }
}

if (!function_exists('auth_remember_try_login')) {
    function auth_remember_try_login(PDO $pdo): ?array
    {
        $parts = auth_remember_parse_cookie();
        if (!$parts) {
            return null;
        }

        auth_remember_ensure_table($pdo);

        [$selector, $validator] = $parts;
        $stmt = $pdo->prepare(
            'SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.username, u.role
             FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.selector = ?
             LIMIT 1'
        );
        $stmt->execute([$selector]);
        $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenRow) {
            auth_remember_clear_cookie();
            return null;
        }

        if (strtotime($tokenRow['expires_at']) <= time()) {
            $deleteStmt = $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?');
            $deleteStmt->execute([$tokenRow['id']]);
            auth_remember_clear_cookie();
            return null;
        }

        $incomingHash = hash('sha256', $validator);
        if (!hash_equals($tokenRow['token_hash'], $incomingHash)) {
            $deleteStmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $deleteStmt->execute([$selector]);
            auth_remember_clear_cookie();
            return null;
        }

        $newValidator = bin2hex(random_bytes(32));
        $newTokenHash = hash('sha256', $newValidator);
        $newExpiresAtTimestamp = time() + (30 * 24 * 60 * 60);
        $newExpiresAtDb = date('Y-m-d H:i:s', $newExpiresAtTimestamp);

        $updateStmt = $pdo->prepare(
            'UPDATE remember_tokens
             SET token_hash = ?, expires_at = ?, last_used_at = NOW()
             WHERE id = ?'
        );
        $updateStmt->execute([$newTokenHash, $newExpiresAtDb, $tokenRow['id']]);

        auth_remember_set_cookie($selector . ':' . $newValidator, $newExpiresAtTimestamp);

        return [
            'id' => (int)$tokenRow['user_id'],
            'username' => (string)$tokenRow['username'],
            'role' => (string)$tokenRow['role'],
        ];
    }
}
