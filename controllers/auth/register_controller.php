<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db_connection.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userGenderColumnExists = static function (PDO $db): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['users', 'gender']);
    $cached = (int)$stmt->fetchColumn() > 0;
    return $cached;
};

$hasGenderColumn = $userGenderColumnExists($pdo);

$generalErrors = [];
$fieldErrors = [
    'username' => '',
    'email' => '',
    'phone' => '',
    'gender' => '',
    'password' => '',
];
$username = '';
$email = '';
$phone = '';
$gender = '';

$setFieldError = static function (array &$errors, string $field, string $message): void {
    if (isset($errors[$field]) && $errors[$field] === '') {
        $errors[$field] = $message;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $generalErrors[] = 'Invalid CSRF token.';
    }

    $username = trim((string)($_POST['username'] ?? ''));
    if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+){2,}$/', $username)) {
        $setFieldError($fieldErrors, 'username', 'Enter at least 3 names using letters only, separated by spaces.');
    }

    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/', $email)) {
        $setFieldError($fieldErrors, 'email', 'Email must be a valid @gmail.com address.');
    }

    $rawPhone = trim((string)($_POST['phone'] ?? ''));
    $phone = preg_replace('/\s+/', '', $rawPhone);
    if (!is_string($phone)) {
        $phone = $rawPhone;
    }
    $isValidLocalPhone = (bool)preg_match('/^0(?:6|7)\d{8}$/', $phone);
    $isValidInternationalPhone = (bool)preg_match('/^\+255(?:6|7)\d{8}$/', $phone);
    if (!$isValidLocalPhone && !$isValidInternationalPhone) {
        $setFieldError($fieldErrors, 'phone', 'Use 10 digits starting with 06/07, or +255 followed by 9 digits.');
    }

    $gender = strtolower(trim((string)($_POST['gender'] ?? '')));
    if ($hasGenderColumn && !in_array($gender, ['male', 'female'], true)) {
        $setFieldError($fieldErrors, 'gender', 'Please select a valid gender.');
    }

    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{6,}$/', $password)) {
        $setFieldError($fieldErrors, 'password', 'Password must be at least 6 characters and include letters and numbers.');
    }

    $hasFieldErrors = implode('', $fieldErrors) !== '';
    if (!$hasFieldErrors && empty($generalErrors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);

        if ($stmt->fetch()) {
            $emailExistsStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $emailExistsStmt->execute([$email]);
            if ($emailExistsStmt->fetch()) {
                $setFieldError($fieldErrors, 'email', 'This email is already registered.');
            }

            $usernameExistsStmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $usernameExistsStmt->execute([$username]);
            if ($usernameExistsStmt->fetch()) {
                $setFieldError($fieldErrors, 'username', 'This username is already taken.');
            }
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            if ($hasGenderColumn) {
                $insertStmt = $pdo->prepare('INSERT INTO users (username, email, phone, gender, password) VALUES (?, ?, ?, ?, ?)');
                $insertOk = $insertStmt->execute([$username, $email, $phone, $gender, $hashedPassword]);
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)');
                $insertOk = $insertStmt->execute([$username, $email, $phone, $hashedPassword]);
            }

            if ($insertOk) {
                $_SESSION['success'] = 'Registration successful! You can now log in.';
                header('Location: login.php');
                exit;
            }

            $generalErrors[] = 'Registration failed. Please try again.';
        }
    }
}

return [
    'errors' => array_values(array_filter($fieldErrors)),
    'general_errors' => $generalErrors,
    'field_errors' => $fieldErrors,
    'username' => $username,
    'email' => $email,
    'phone' => $phone,
    'gender' => $gender,
    'supports_gender' => $hasGenderColumn,
    'csrf_token' => $_SESSION['csrf_token']
];
