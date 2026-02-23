<?php
$authState = require __DIR__ . '/../controllers/auth/reset_password_controller.php';
$generalErrors = $authState['general_errors'] ?? [];
$fieldErrors = $authState['field_errors'] ?? [];
$selector = $authState['selector'] ?? '';
$token = $authState['token'] ?? '';
$csrfToken = $authState['csrf_token'] ?? '';
$isTokenValid = !empty($authState['is_token_valid']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | HostelPro</title>
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
                    <i class="bi bi-shield-lock auth-side-watermark" aria-hidden="true"></i>
                    <div class="auth-side-content">
                        <span class="auth-badge"><i class="bi bi-lock me-2"></i>Secure Password Update</span>
                        <h1 class="auth-title mt-3">Create new password</h1>
                        <p class="auth-subtitle mb-3">
                            Use a strong password with letters and numbers. After reset, please sign in again.
                        </p>
                        <div class="auth-guide">
                            <h6 class="auth-guide-title">Password requirements</h6>
                            <ul class="auth-guide-list mb-0">
                                <li>At least 6 characters long.</li>
                                <li>Must include letters and numbers.</li>
                                <li>Do not reuse weak or shared passwords.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 auth-form-col">
                    <div class="auth-form-wrap">
                        <div class="auth-heading mb-4">
                            <h2 class="mb-1"><i class="bi bi-key me-2"></i>Reset Password</h2>
                            <p class="mb-0">Enter your new password below.</p>
                        </div>

                        <?php if (!empty($generalErrors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($generalErrors as $err): ?>
                                        <li><?= htmlspecialchars((string)$err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isTokenValid): ?>
                            <div class="alert alert-warning" role="alert">
                                This reset link is invalid or expired.
                            </div>
                            <a href="forgot_password.php" class="btn btn-brand w-100">
                                <i class="bi bi-arrow-repeat me-2"></i>Request New Link
                            </a>
                        <?php else: ?>
                            <form method="POST" autocomplete="off" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrfToken) ?>">
                                <input type="hidden" name="selector" value="<?= htmlspecialchars((string)$selector) ?>">
                                <input type="hidden" name="token" value="<?= htmlspecialchars((string)$token) ?>">

                                <div class="mb-3">
                                    <label class="form-label" for="password">New Password</label>
                                    <div class="input-group input-group-auth has-validation">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input
                                            type="password"
                                            class="form-control<?= !empty($fieldErrors['password']) ? ' is-invalid' : '' ?>"
                                            id="password"
                                            name="password"
                                            placeholder="Letters + numbers, min 6"
                                            required
                                            minlength="6"
                                            autocomplete="new-password"
                                            data-field="password"
                                        >
                                        <button type="button" class="btn btn-password-toggle" id="togglePassword" aria-label="Show password">
                                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                        </button>
                                        <div class="invalid-feedback<?= !empty($fieldErrors['password']) ? ' d-block' : '' ?>" id="passwordFeedback">
                                            <?= htmlspecialchars((string)($fieldErrors['password'] ?? 'Password must be at least 6 characters and include letters and numbers.')) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" for="password_confirm">Confirm Password</label>
                                    <div class="input-group input-group-auth has-validation">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input
                                            type="password"
                                            class="form-control<?= !empty($fieldErrors['password_confirm']) ? ' is-invalid' : '' ?>"
                                            id="password_confirm"
                                            name="password_confirm"
                                            placeholder="Repeat your new password"
                                            required
                                            minlength="6"
                                            autocomplete="new-password"
                                            data-field="password_confirm"
                                        >
                                        <button type="button" class="btn btn-password-toggle" id="togglePasswordConfirm" aria-label="Show password confirmation">
                                            <i class="bi bi-eye" id="togglePasswordConfirmIcon"></i>
                                        </button>
                                        <div class="invalid-feedback<?= !empty($fieldErrors['password_confirm']) ? ' d-block' : '' ?>" id="passwordConfirmFeedback">
                                            <?= htmlspecialchars((string)($fieldErrors['password_confirm'] ?? 'Please confirm your password.')) ?>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-brand w-100">
                                    <i class="bi bi-check2-circle me-2"></i>Update Password
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="login.php" class="auth-home-link">
                                <i class="bi bi-arrow-left-short"></i> Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/ui-spinner.js"></script>
    <script src="../assets/js/reset-password.js"></script>
</body>
</html>
