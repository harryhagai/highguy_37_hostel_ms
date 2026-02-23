<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../helpers/password_reset_helper.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$generalErrors = [];
$fieldErrors = [
    'password' => '',
    'password_confirm' => '',
];
$isTokenValid = false;

$selector = strtolower(trim((string)($_GET['selector'] ?? $_POST['selector'] ?? '')));
$token = strtolower(trim((string)($_GET['token'] ?? $_POST['token'] ?? '')));

$setFieldError = static function (array &$errorBag, string $field, string $message): void {
    if (isset($errorBag[$field]) && $errorBag[$field] === '') {
        $errorBag[$field] = $message;
    }
};

try {
    $tokenRow = auth_password_reset_find($pdo, $selector, $token);
    $isTokenValid = is_array($tokenRow);
} catch (Throwable $e) {
    $tokenRow = null;
    $isTokenValid = false;
    $generalErrors[] = 'Unable to validate reset token right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $generalErrors[] = 'Invalid CSRF token.';
    }

    if (!$isTokenValid || !is_array($tokenRow)) {
        $generalErrors[] = 'Reset link is invalid or expired.';
    }

    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{6,}$/', $password)) {
        $setFieldError($fieldErrors, 'password', 'Password must be at least 6 characters and include letters and numbers.');
    }
    if ($passwordConfirm === '') {
        $setFieldError($fieldErrors, 'password_confirm', 'Please confirm your new password.');
    } elseif (!hash_equals($password, $passwordConfirm)) {
        $setFieldError($fieldErrors, 'password_confirm', 'Passwords do not match.');
    }

    $hasFieldErrors = implode('', $fieldErrors) !== '';
    if (!$hasFieldErrors && empty($generalErrors) && is_array($tokenRow)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userId = (int)$tokenRow['user_id'];
            $email = (string)$tokenRow['email'];

            if ($userId > 0) {
                $updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $updateStmt->execute([$hashedPassword, $userId]);
            } else {
                $updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
                $updateStmt->execute([$hashedPassword, $email]);
            }
            if ($updateStmt->rowCount() <= 0) {
                throw new RuntimeException('No user account matched this reset token.');
            }

            auth_password_reset_consume($pdo, (int)$tokenRow['id']);
            auth_password_reset_invalidate_user_tokens($pdo, $userId, $email);
            auth_password_reset_revoke_remember_tokens($pdo, $userId);

            $_SESSION['success'] = 'Password updated successfully. Please login with your new password.';
            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            error_log('Reset password failed: ' . $e->getMessage());
            $generalErrors[] = 'Unable to reset password right now. Please try again.';
        }
    }
}

$errors = array_values(array_filter(array_merge($generalErrors, array_values($fieldErrors))));

return [
    'errors' => $errors,
    'general_errors' => $generalErrors,
    'field_errors' => $fieldErrors,
    'selector' => $selector,
    'token' => $token,
    'is_token_valid' => $isTokenValid,
    'csrf_token' => $_SESSION['csrf_token'],
];
