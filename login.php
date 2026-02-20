<?php
session_start();
require_once __DIR__ . '/config/db_connection.php';

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    // Sanitize input
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate fields
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (empty($password)) {
        $errors[] = "Please enter your password.";
    }

    // If no errors, check credentials
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin/admin_dashboard_layout.php");
                exit;
            } else {
                header("Location: user/user_dashboard_layout.php");
                exit;
            }
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="auth-body">
    <section class="auth-shell py-4 py-lg-5">
        <div class="container">
            <div class="auth-panel row g-0 mx-auto">
                <div class="col-lg-6 auth-side d-flex flex-column justify-content-center">
                    <i class="bi bi-key-fill auth-side-watermark" aria-hidden="true"></i>
                    <div class="auth-side-content">
                        <span class="auth-badge"><i class="bi bi-shield-check me-2"></i>Secure Access</span>
                        <h1 class="auth-title mt-3">Welcome back</h1>
                        <p class="auth-subtitle mb-3">
                            Sign in to continue managing hostels, rooms, beds, and student bookings in one place.
                        </p>
                        <div class="auth-guide">
                            <h6 class="auth-guide-title">How to fill this form</h6>
                            <ul class="auth-guide-list mb-0">
                                <li>Use the same email you registered with.</li>
                                <li>Password must match your account password.</li>
                                <li>Check spelling before clicking Login.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 auth-form-col">
                    <div class="auth-form-wrap">
                        <div class="auth-heading mb-4">
                            <h2 class="mb-1"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h2>
                            <p class="mb-0">Enter your credentials to access your account.</p>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= htmlspecialchars($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <div class="input-group input-group-auth">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        placeholder="name@gmail.com"
                                        value="<?= htmlspecialchars($email) ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group input-group-auth">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        placeholder="Enter your password"
                                        required
                                        autocomplete="current-password"
                                    >
                                </div>
                            </div>

                            <button type="submit" class="btn btn-brand w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </form>

                        <p class="auth-switch text-center mt-4 mb-0">
                            Don't have an account?
                            <a href="register.php" class="auth-link-accent">Create one</a>
                        </p>
                        <div class="text-center mt-2">
                            <a href="index.php" class="auth-home-link">
                                <i class="bi bi-arrow-left-short"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/ui-spinner.js"></script>
</body>
</html>


