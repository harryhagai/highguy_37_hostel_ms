<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Username must be letters, numbers, or underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists.';
        }

        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password, phone, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$username, $email, $hashed, $phone, $role]);
            $success = 'User added successfully.';
        } else {
            $openModal = 'addUserModal';
        }
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Username must be letters, numbers, or underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
        $stmt->execute([$username, $email, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists.';
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (empty($errors)) {
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, role = ?, password = ? WHERE id = ?');
                $stmt->execute([$username, $email, $phone, $role, $hashed, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, phone = ?, role = ? WHERE id = ?');
                $stmt->execute([$username, $email, $phone, $role, $id]);
            }
            $success = 'User updated successfully.';
        } else {
            $openModal = 'editUserModal';
            $editFormData = [
                'id' => $id,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'role' => $role
            ];
        }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'User deleted successfully.';
        }
    }
}

$users = $pdo->query('SELECT id, username, email, phone, role, created_at FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'users' => $users
];
