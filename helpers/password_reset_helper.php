<?php
require_once __DIR__ . '/mailer.php';

if (!function_exists('auth_password_reset_table_exists')) {
    function auth_password_reset_table_exists(PDO $pdo): bool
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
        $stmt->execute(['password_reset_tokens']);
        $cached = (int)$stmt->fetchColumn() > 0;
        return $cached;
    }
}

if (!function_exists('auth_password_reset_assert_table')) {
    function auth_password_reset_assert_table(PDO $pdo): void
    {
        if (!auth_password_reset_table_exists($pdo)) {
            throw new RuntimeException('Table password_reset_tokens is missing. Run SQL migration first.');
        }
    }
}

if (!function_exists('auth_password_reset_cleanup')) {
    function auth_password_reset_cleanup(PDO $pdo): void
    {
        auth_password_reset_assert_table($pdo);
        $pdo->exec('DELETE FROM password_reset_tokens WHERE used_at IS NOT NULL OR expires_at < NOW()');
    }
}

if (!function_exists('auth_password_reset_issue')) {
    function auth_password_reset_issue(PDO $pdo, int $userId, string $email, int $ttlSeconds = 1800): ?array
    {
        if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        auth_password_reset_cleanup($pdo);

        $deleteStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? OR email = ?');
        $deleteStmt->execute([$userId, $email]);

        $selector = bin2hex(random_bytes(12));
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + max($ttlSeconds, 300));
        $requestedIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $requestedUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        $insertStmt = $pdo->prepare(
            'INSERT INTO password_reset_tokens
                (user_id, email, selector, token_hash, expires_at, requested_ip, requested_user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insertStmt->execute([$userId, $email, $selector, $tokenHash, $expiresAt, $requestedIp, $requestedUserAgent]);

        return [
            'selector' => $selector,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }
}

if (!function_exists('auth_password_reset_find')) {
    function auth_password_reset_find(PDO $pdo, string $selector, string $token): ?array
    {
        $selector = strtolower(trim($selector));
        $token = strtolower(trim($token));

        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        auth_password_reset_assert_table($pdo);

        $stmt = $pdo->prepare(
            'SELECT id, user_id, email, token_hash, expires_at, used_at
             FROM password_reset_tokens
             WHERE selector = ?
             LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (!empty($row['used_at']) || strtotime((string)$row['expires_at']) <= time()) {
            $deleteStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE id = ?');
            $deleteStmt->execute([(int)$row['id']]);
            return null;
        }

        $incomingHash = hash('sha256', $token);
        if (!hash_equals((string)$row['token_hash'], $incomingHash)) {
            $deleteStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE selector = ?');
            $deleteStmt->execute([$selector]);
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'email' => (string)$row['email'],
            'expires_at' => (string)$row['expires_at'],
        ];
    }
}

if (!function_exists('auth_password_reset_consume')) {
    function auth_password_reset_consume(PDO $pdo, int $tokenId): void
    {
        if ($tokenId <= 0) {
            return;
        }

        auth_password_reset_assert_table($pdo);
        $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $stmt->execute([$tokenId]);
    }
}

if (!function_exists('auth_password_reset_invalidate_user_tokens')) {
    function auth_password_reset_invalidate_user_tokens(PDO $pdo, int $userId, string $email): void
    {
        auth_password_reset_assert_table($pdo);
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? OR email = ?');
        $stmt->execute([$userId, $email]);
    }
}

if (!function_exists('auth_password_reset_revoke_remember_tokens')) {
    function auth_password_reset_revoke_remember_tokens(PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $tableExistsStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $tableExistsStmt->execute(['remember_tokens']);
        $hasRememberTable = (int)$tableExistsStmt->fetchColumn() > 0;
        if (!$hasRememberTable) {
            return;
        }

        $deleteStmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $deleteStmt->execute([$userId]);
    }
}

if (!function_exists('auth_password_reset_build_link')) {
    function auth_password_reset_build_link(string $selector, string $token): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $isHttps ? 'https' : 'http';

        $basePath = '';
        $authMarker = '/auth/';
        $markerPos = strpos($scriptName, $authMarker);
        if ($markerPos !== false) {
            $basePath = substr($scriptName, 0, $markerPos);
        } elseif ($scriptName !== '') {
            $basePath = dirname($scriptName);
        }

        if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
            $basePath = '';
        }

        $resetPath = rtrim($basePath, '/') . '/auth/reset_password.php';
        $query = http_build_query([
            'selector' => $selector,
            'token' => $token,
        ]);

        return $scheme . '://' . $host . $resetPath . '?' . $query;
    }
}

if (!function_exists('auth_password_reset_send_email')) {
    function auth_password_reset_send_email(string $toEmail, string $toName, string $resetLink): array
    {
        $subject = 'Reset your HostelPro password';
        $safeName = htmlspecialchars($toName !== '' ? $toName : 'User', ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $htmlBody = '
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Password Reset</title>
            </head>
            <body style="margin:0;padding:0;background:#f8f9fc;font-family:Segoe UI,Arial,sans-serif;color:#233142;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8f9fc;padding:24px 12px;">
                    <tr>
                        <td align="center">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #c9edf0;border-radius:16px;overflow:hidden;">
                                <tr>
                                    <td style="padding:22px 24px;background:linear-gradient(135deg,#1ccad8,#11998e);color:#ffffff;">
                                        <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">HostelPro Security</div>
                                        <h1 style="margin:8px 0 0;font-size:24px;line-height:1.25;">Reset Your Password</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:24px;">
                                        <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">Hello ' . $safeName . ',</p>
                                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                            We received a request to reset your HostelPro account password.
                                            Click the button below to continue.
                                        </p>

                                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 18px;">
                                            <tr>
                                                <td align="center" style="border-radius:10px;background:#11998e;">
                                                    <a href="' . $safeLink . '" style="display:inline-block;padding:12px 20px;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">
                                                        Reset Password
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <div style="margin:0 0 14px;padding:12px;border:1px dashed #c9edf0;border-radius:10px;background:#f4fcfd;">
                                            <p style="margin:0 0 8px;font-size:13px;color:#4f6375;">If button is not working, copy this link:</p>
                                            <p style="margin:0;word-break:break-all;font-size:12px;">
                                                <a href="' . $safeLink . '" style="color:#11998e;text-decoration:underline;">' . $safeLink . '</a>
                                            </p>
                                        </div>

                                        <p style="margin:0 0 8px;font-size:13px;color:#4f6375;">
                                            This link expires in <strong>30 minutes</strong>.
                                        </p>
                                        <p style="margin:0;font-size:13px;color:#4f6375;">
                                            If you did not request this, you can safely ignore this email.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 24px;background:#f8fafc;border-top:1px solid #eaf2f5;">
                                        <p style="margin:0;font-size:12px;color:#6b7280;">HostelPro System</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ';

        $altBody = "HostelPro password reset\n\nHello {$toName},\n\nUse this link to reset your password:\n{$resetLink}\n\nThis link expires in 30 minutes.\nIf you did not request this, ignore this email.";

        return hostel_send_mail($toEmail, $toName, $subject, $htmlBody, $altBody);
    }
}
