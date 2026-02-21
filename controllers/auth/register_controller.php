<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db_connection.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$username = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Invalid CSRF token.';
    }

    $username = trim((string)($_POST['username'] ?? ''));
    if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+)*$/', $username)) {
        $errors[] = 'Username must contain letters only and may include spaces between words.';
    }

    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/', $email)) {
        $errors[] = 'Email must be a valid @gmail.com address.';
    }

    $phone = trim((string)($_POST['phone'] ?? ''));
    if (!preg_match('/^(?:\+2557\d{8}|07\d{8})$/', $phone)) {
        $errors[] = 'Phone number must be in Tanzania format: +2557XXXXXXXX or 07XXXXXXXX.';
    }

    $password = (string)($_POST['password'] ?? '');
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {
            $errors[] = 'Email or Username already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare('INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)');

            if ($insertStmt->execute([$username, $email, $phone, $hashedPassword])) {
                $_SESSION['success'] = 'Registration successful! You can now log in.';
                header('Location: login.php');
                exit;
            }

            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

return [
    'errors' => $errors,
    'username' => $username,
    'email' => $email,
    'phone' => $phone,
    'csrf_token' => $_SESSION['csrf_token']
];
