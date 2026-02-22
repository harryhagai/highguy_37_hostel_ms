<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = null;
$stringLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
};
$stringSlice = static function (string $value, int $length): string {
    return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
};
$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'topic' => '',
    'message' => '',
];

if (!empty($_SESSION['contact_message_success'])) {
    $successMessage = (string)$_SESSION['contact_message_success'];
    unset($_SESSION['contact_message_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'send_contact_message') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Invalid CSRF token.';
    }

    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['email'] = trim((string)($_POST['email'] ?? ''));
    $form['phone'] = trim((string)($_POST['phone'] ?? ''));
    $form['topic'] = trim((string)($_POST['topic'] ?? ''));
    $form['message'] = trim((string)($_POST['message'] ?? ''));

    if ($form['name'] === '' || $stringLength($form['name']) < 2) {
        $errors[] = 'Please enter your full name.';
    } elseif ($stringLength($form['name']) > 120) {
        $errors[] = 'Full name is too long.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($stringLength($form['email']) > 190) {
        $errors[] = 'Email is too long.';
    }

    if ($form['phone'] !== '' && $stringLength($form['phone']) > 30) {
        $errors[] = 'Phone number is too long.';
    }

    if ($form['topic'] !== '' && $stringLength($form['topic']) > 160) {
        $errors[] = 'Topic is too long.';
    }

    if ($form['message'] === '' || $stringLength($form['message']) < 10) {
        $errors[] = 'Message must be at least 10 characters.';
    }

    if (empty($errors)) {
        $ipAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if ($ipAddress === '') {
            $ipAddress = null;
        }
        if ($userAgent === '') {
            $userAgent = null;
        } elseif ($stringLength($userAgent) > 255) {
            $userAgent = $stringSlice($userAgent, 255);
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages
                    (full_name, email, phone, topic, message, ip_address, user_agent, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $form['name'],
                $form['email'],
                $form['phone'] !== '' ? $form['phone'] : null,
                $form['topic'] !== '' ? $form['topic'] : null,
                $form['message'],
                $ipAddress,
                $userAgent,
                'new',
            ]);

            $_SESSION['contact_message_success'] = 'Message sent successfully. We will get back to you soon.';
            header('Location: index.php#contact');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Unable to send message right now. Please ensure table `contact_messages` exists.';
        }
    }
}

return [
    'errors' => $errors,
    'success_message' => $successMessage,
    'form' => $form,
    'csrf_token' => $_SESSION['csrf_token'],
];
