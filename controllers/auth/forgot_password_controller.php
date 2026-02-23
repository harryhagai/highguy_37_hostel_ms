<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../helpers/password_reset_helper.php';
require_once __DIR__ . '/../../helpers/auth_throttle_helper.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$generalErrors = [];
$fieldErrors = [
    'email' => '',
];
$email = '';
$successMessage = null;

$setFieldError = static function (array &$errorBag, string $field, string $message): void {
    if (isset($errorBag[$field]) && $errorBag[$field] === '') {
        $errorBag[$field] = $message;
    }
};

$exposeAuthError = static function (Throwable $e): string {
    $msg = (string)$e->getMessage();
    if ($msg === '') {
        return 'Unable to process your request right now. Please try again.';
    }

    $known = [
        'SMTP credentials are missing.',
        'PHPMailer not installed. Run composer install.',
        'PHPMailer class not available after autoload.',
        'Table password_reset_tokens is missing. Run SQL migration first.',
        'Table auth_attempt_locks is missing. Run SQL migration first.',
        'Invalid sender email in config.',
        'Token generation failed.',
    ];

    foreach ($known as $needle) {
        if (stripos($msg, $needle) !== false) {
            return $needle;
        }
    }

    return 'Unable to process your request right now. Please try again.';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $generalErrors[] = 'Invalid CSRF token.';
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $identifier = auth_throttle_identifier($email, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $shouldCountFailure = false;
    $isLocked = false;

    try {
        $lockState = auth_throttle_is_locked($pdo, 'forgot_password', $identifier);
        if (!empty($lockState['locked'])) {
            $generalErrors[] = auth_throttle_lock_message('forgot password', (string)($lockState['locked_until'] ?? ''));
            $isLocked = true;
        }

        if (!$isLocked) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $setFieldError($fieldErrors, 'email', 'Please enter a valid email address.');
                $shouldCountFailure = true;
            }

            $hasFieldErrors = implode('', $fieldErrors) !== '';
            if (!$hasFieldErrors && empty($generalErrors)) {
                $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $setFieldError($fieldErrors, 'email', 'This email is not registered in our system.');
                    $shouldCountFailure = true;
                } else {
                    $tokenRow = auth_password_reset_issue($pdo, (int)$user['id'], (string)$user['email']);
                    if (!$tokenRow) {
                        throw new RuntimeException('Token generation failed.');
                    }

                    $resetLink = auth_password_reset_build_link((string)$tokenRow['selector'], (string)$tokenRow['token']);
                    $mailResult = auth_password_reset_send_email((string)$user['email'], (string)$user['username'], $resetLink);
                    if (empty($mailResult['ok'])) {
                        throw new RuntimeException((string)($mailResult['error'] ?? 'Email send failed.'));
                    }

                    auth_throttle_clear($pdo, 'forgot_password', $identifier);
                    $successMessage = 'A reset link has been sent to your email.';
                }
            }
        }

        if (!$isLocked && $shouldCountFailure) {
            $state = auth_throttle_register_failure($pdo, 'forgot_password', $identifier, 3, 10800);
            $lockedUntil = (string)($state['locked_until'] ?? '');
            if ($lockedUntil !== '' && strtotime($lockedUntil) > time()) {
                $generalErrors[] = auth_throttle_lock_message('forgot password', $lockedUntil);
            }
        }
    } catch (Throwable $e) {
        error_log('Forgot password request failed: ' . $e->getMessage());
        $generalErrors[] = $exposeAuthError($e);
        $successMessage = null;
    }

    if (!empty($generalErrors)) {
        $successMessage = null;
    }
}

$errors = array_values(array_filter(array_merge($generalErrors, array_values($fieldErrors))));

return [
    'errors' => $errors,
    'general_errors' => $generalErrors,
    'field_errors' => $fieldErrors,
    'email' => $email,
    'csrf_token' => $_SESSION['csrf_token'],
    'success_message' => $successMessage,
];
