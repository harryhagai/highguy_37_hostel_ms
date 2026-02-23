<?php
$authState = require __DIR__ . '/../controllers/auth/forgot_password_controller.php';
$generalErrors = $authState['general_errors'] ?? [];
$fieldErrors = $authState['field_errors'] ?? [];
$email = $authState['email'] ?? '';
$csrfToken = $authState['csrf_token'] ?? '';
$successMessage = $authState['success_message'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="auth-body">
    <section class="auth-shell py-4 py-lg-5">
        <div class="container">
            <div class="auth-panel row g-0 mx-auto">
                <div class="col-lg-6 auth-side d-flex flex-column justify-content-center">
                    <i class="bi bi-envelope-at auth-side-watermark" aria-hidden="true"></i>
                    <div class="auth-side-content">
                        <span class="auth-badge"><i class="bi bi-shield-lock me-2"></i>Password Recovery</span>
                        <h1 class="auth-title mt-3">Forgot your password?</h1>
                        <p class="auth-subtitle mb-3">
                            Enter your account email and we will send a secure reset link that expires in 30 minutes.
                        </p>
                        <div class="auth-guide">
                            <h6 class="auth-guide-title">How this works</h6>
                            <ul class="auth-guide-list mb-0">
                                <li>Enter your registered email address.</li>
                                <li>Open the reset email and click the link.</li>
                                <li>Create a new password and login again.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 auth-form-col">
                    <div class="auth-form-wrap">
                        <div class="auth-heading mb-4">
                            <h2 class="mb-1"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset Request</h2>
                            <p class="mb-0">Submit your email to receive a password reset link.</p>
                        </div>

                        <?php if ($successMessage): ?>
                            <div class="alert alert-success" role="alert">
                                <?= htmlspecialchars((string)$successMessage) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($generalErrors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($generalErrors as $err): ?>
                                        <li><?= htmlspecialchars((string)$err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" autocomplete="off" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrfToken) ?>">

                            <div class="mb-4">
                                <label class="form-label" for="email">Email</label>
                                <div class="input-group input-group-auth has-validation">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control<?= !empty($fieldErrors['email']) ? ' is-invalid' : '' ?>"
                                        id="email"
                                        name="email"
                                        placeholder="name@gmail.com"
                                        value="<?= htmlspecialchars((string)$email) ?>"
                                        required
                                    >
                                    <span class="btn-password-toggle email-trailing-icon" aria-hidden="true">
                                        <i class="bi bi-envelope-at"></i>
                                    </span>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['email']) ? ' d-block' : '' ?>">
                                        <?= htmlspecialchars((string)($fieldErrors['email'] ?? 'Please enter a valid email address.')) ?>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-brand w-100">
                                <i class="bi bi-send me-2"></i>Send Reset Link
                            </button>
                        </form>

                        <p class="auth-switch text-center mt-4 mb-0">
                            Remembered your password?
                            <a href="login.php" class="auth-link-accent">Back to login</a>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/ui-spinner.js"></script>
</body>
</html>
