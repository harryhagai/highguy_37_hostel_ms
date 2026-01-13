<?php


require_once __DIR__ . '/../config/db_connection.php';

// Handle Create
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $password = $_POST['password'];
    $errors = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) $errors[] = "Username must be letters, numbers, or underscores.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    // Check if username/email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) $errors[] = "Username or email already exists.";

    if (!$errors) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed, $phone, $role]);
        $success = "User added successfully!";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard_layout.php?page=manage_users");
    exit;
}

// Handle Edit (fetch user)
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Update
if (isset($_POST['update_user'])) {
    $id = (int)$_POST['id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $password = $_POST['password'];
    $errors = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) $errors[] = "Username must be letters, numbers, or underscores.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";

    // Check for username/email conflicts
    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $id]);
    if ($stmt->fetch()) $errors[] = "Username or email already exists.";

    if (!$errors) {
        if ($password) {
            if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, password=? WHERE id=?");
            $stmt->execute([$username, $email, $phone, $role, $hashed, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, role=? WHERE id=?");
            $stmt->execute([$username, $email, $phone, $role, $id]);
        }
        $success = "User updated successfully!";
        $edit_user = null;
    }
}

// Fetch all users
$users = $pdo->query("SELECT id, username, email, phone, role, created_at FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid px-0">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white" >
            <h4 class="mb-0"><?= $edit_user ? 'Edit User' : 'Add User' ?></h4>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
            <?php elseif (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required pattern="[A-Za-z0-9_]+" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="user" <?= (isset($edit_user['role']) && $edit_user['role']=='user') ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= (isset($edit_user['role']) && $edit_user['role']=='admin') ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><?= $edit_user ? 'New Password (optional)' : 'Password' ?></label>
                        <input type="password" name="password" class="form-control" <?= $edit_user ? '' : 'required minlength="6"' ?>>
                    </div>
                </div>
                <div class="mt-3">
                    <?php if ($edit_user): ?>
                        <button class="btn btn-success" name="update_user" type="submit"><i class="bi bi-check-circle"></i> Update</button>
                        <a href="admin_dashboard_layout.php?page=manage_users" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button class="btn btn-primary" name="add_user" type="submit"><i class="bi bi-plus-circle"></i> Add</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h4 class="mb-0">All Users</h4>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td>
                            <span class="badge bg-<?= $user['role']=='admin'?'danger':'secondary' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td><?= $user['created_at'] ?></td>
                        <td>
                            <a href="admin_dashboard_layout.php?page=manage_users&edit=<?= $user['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="admin_dashboard_layout.php?page=manage_users&delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')"><i class="bi bi-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">