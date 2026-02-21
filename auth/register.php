<?php
$authState = require __DIR__ . '/../controllers/auth/register_controller.php';
$errors = $authState['errors'];
$username = $authState['username'];
$email = $authState['email'];
$phone = $authState['phone'];
$csrfToken = $authState['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body class="auth-body">
    <section class="auth-shell py-4 py-lg-5">
        <div class="container">
            <div class="auth-panel row g-0 mx-auto">
                <div class="col-lg-6 auth-side d-flex flex-column justify-content-center">
                    <i class="bi bi-key-fill auth-side-watermark" aria-hidden="true"></i>
                    <div class="auth-side-content">
                        <span class="auth-badge"><i class="bi bi-building me-2"></i>HostelPro</span>
                        <h1 class="auth-title mt-3">Create account</h1>
                        <p class="auth-subtitle mb-3">
                            Register once and start booking hostel beds, tracking requests, and managing your profile smoothly.
                        </p>
                        <div class="auth-guide">
                            <h6 class="auth-guide-title">How to fill this form</h6>
                            <ul class="auth-guide-list mb-0">
                                <li>Username: letters only, spaces between words allowed.</li>
                                <li>Email: must end with <code>@gmail.com</code>.</li>
                                <li>Phone: use <code>+2557XXXXXXXX</code> or <code>07XXXXXXXX</code>.</li>
                                <li>Password: minimum 6 characters.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 auth-form-col">
                    <div class="auth-form-wrap">
                        <div class="auth-heading mb-4">
                            <h2 class="mb-1"><i class="bi bi-person-plus me-2"></i>Register</h2>
                            <p class="mb-0">Fill the details below to create your account.</p>
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="username">Username</label>
                                <div class="input-group input-group-auth">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="username"
                                        name="username"
                                        placeholder="Letters and spaces"
                                        value="<?= htmlspecialchars($username) ?>"
                                        required
                                        pattern="[A-Za-z]+( [A-Za-z]+)*"
                                        title="Letters only, spaces between words allowed"
                                    >
                                </div>
                            </div>

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
                                        pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
                                        title="Must be a valid @gmail.com email"
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="phone">Phone</label>
                                <div class="input-group input-group-auth">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="phone"
                                        name="phone"
                                        placeholder="+2557XXXXXXXX or 07XXXXXXXX"
                                        value="<?= htmlspecialchars($phone) ?>"
                                        required
                                        pattern="^(\+2557\d{8}|07\d{8})$"
                                        title="Format: +2557XXXXXXXX or 07XXXXXXXX"
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
                                        placeholder="At least 6 characters"
                                        required
                                        minlength="6"
                                        autocomplete="new-password"
                                    >
                                </div>
                            </div>

                            <button type="submit" class="btn btn-brand w-100">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </button>
                        </form>

                        <p class="auth-switch text-center mt-4 mb-0">
                            Already have an account?
                            <a href="login.php" class="auth-link-accent">Sign in</a>
                        </p>
                        <div class="text-center mt-2">
                            <a href="../index.php" class="auth-home-link">
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
    <script src="../assets/js/ui-spinner.js"></script>
</body>
</html>
