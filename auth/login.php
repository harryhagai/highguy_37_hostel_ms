<?php
$authState = require __DIR__ . '/../controllers/auth/login_controller.php';
$errors = $authState['errors'];
$generalErrors = $authState['general_errors'] ?? [];
$fieldErrors = $authState['field_errors'] ?? [];
$email = $authState['email'];
$rememberMe = !empty($authState['remember_me']);
$csrfToken = $authState['csrf_token'];
$successMessage = $authState['success_message'];
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
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/login.css">
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

                        <?php if ($successMessage): ?>
                            <div class="alert alert-success" role="alert">
                                <?= htmlspecialchars($successMessage) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($generalErrors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($generalErrors as $err): ?>
                                        <li><?= htmlspecialchars($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <div class="input-group input-group-auth has-validation">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control<?= !empty($fieldErrors['email']) ? ' is-invalid' : '' ?>"
                                        id="email"
                                        name="email"
                                        placeholder="name@gmail.com"
                                        value="<?= htmlspecialchars($email) ?>"
                                        required
                                        data-field="email"
                                    >
                                    <span class="btn-password-toggle email-trailing-icon" aria-hidden="true">
                                        <i class="bi bi-envelope-at"></i>
                                    </span>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['email']) ? ' d-block' : '' ?>" id="emailFeedback">
                                        <?= htmlspecialchars((string)($fieldErrors['email'] ?? 'Please enter a valid email address.')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group input-group-auth has-validation">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input
                                        type="password"
                                        class="form-control<?= !empty($fieldErrors['password']) ? ' is-invalid' : '' ?>"
                                        id="password"
                                        name="password"
                                        placeholder="Enter your password"
                                        required
                                        autocomplete="current-password"
                                        data-field="password"
                                    >
                                    <button type="button" class="btn btn-password-toggle" id="togglePassword" aria-label="Show password">
                                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                    </button>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['password']) ? ' d-block' : '' ?>" id="passwordFeedback">
                                        <?= htmlspecialchars((string)($fieldErrors['password'] ?? 'Please enter your password.')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="remember_me"
                                        name="remember_me"
                                        <?= $rememberMe ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="remember_me">Remember me</label>
                                </div>
                                <a href="forgot_password.php" class="auth-link-accent small">Forgot password?</a>
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
    <script src="../assets/js/login.js"></script>
</body>
</html>
