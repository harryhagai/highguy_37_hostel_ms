<?php
$authState = require __DIR__ . '/../controllers/auth/register_controller.php';
$errors = $authState['errors'];
$generalErrors = $authState['general_errors'] ?? [];
$fieldErrors = $authState['field_errors'] ?? [];
$username = $authState['username'];
$email = $authState['email'];
$phone = $authState['phone'];
$gender = $authState['gender'] ?? '';
$supportsGender = !empty($authState['supports_gender']);
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
                                <li>Full name: at least 3 names, letters only.</li>
                                <li>Email: must end with <code>@gmail.com</code>.</li>
                                <li>Phone: use <code>06...</code>, <code>07...</code>, or <code>+255 ..</code>.</li>
                                <li>Gender: choose Male or Female.</li>
                                <li>Password: minimum 6 chars with letters and numbers.</li>
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
                                <label class="form-label" for="username">Full Name</label>
                                <div class="input-group input-group-auth has-validation">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input
                                        type="text"
                                        class="form-control<?= !empty($fieldErrors['username']) ? ' is-invalid' : '' ?>"
                                        id="username"
                                        name="username"
                                        placeholder="HAGAI HAROLD NGOBEY"
                                        value="<?= htmlspecialchars($username) ?>"
                                        required
                                        pattern="^[A-Za-z]+(?: [A-Za-z]+){2,}$"
                                        title="At least 3 names, letters only, separated by spaces"
                                        data-field="username"
                                    >
                                    <span class="input-group-text field-valid-indicator d-none" id="usernameCheck" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['username']) ? ' d-block' : '' ?>" id="usernameFeedback">
                                        <?= htmlspecialchars((string)($fieldErrors['username'] ?? 'Enter at least 3 names using letters only, separated by spaces.')) ?>
                                    </div>
                                </div>
                            </div>

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
                                        pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
                                        title="Must be a valid @gmail.com email"
                                        data-field="email"
                                    >
                                    <span class="input-group-text field-valid-indicator d-none" id="emailCheck" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['email']) ? ' d-block' : '' ?>" id="emailFeedback">
                                        <?= htmlspecialchars((string)($fieldErrors['email'] ?? 'Email must be a valid @gmail.com address.')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="phone">Phone</label>
                                <div class="input-group input-group-auth has-validation">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input
                                        type="text"
                                        class="form-control<?= !empty($fieldErrors['phone']) ? ' is-invalid' : '' ?>"
                                        id="phone"
                                        name="phone"
                                        placeholder="0765384905 or +255765384905"
                                        value="<?= htmlspecialchars($phone) ?>"
                                        required
                                        pattern="^(0(?:6|7)\d{8}|\+255\s?(?:6|7)\d{8})$"
                                        title="Use 10 digits starting with 06/07, or +255 followed by 9 digits"
                                        data-field="phone"
                                    >
                                    <span class="input-group-text field-valid-indicator d-none" id="phoneCheck" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['phone']) ? ' d-block' : '' ?>" id="phoneFeedback">
                                        <?= htmlspecialchars((string)($fieldErrors['phone'] ?? 'Use 10 digits starting 06/07, or +255 followed by 9 digits.')) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($supportsGender): ?>
                                <div class="mb-3">
                                    <label class="form-label" for="gender">Gender</label>
                                    <div class="input-group input-group-auth has-validation">
                                        <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                                        <select class="form-select<?= !empty($fieldErrors['gender']) ? ' is-invalid' : '' ?>" id="gender" name="gender" required data-field="gender">
                                            <option value="">Select gender</option>
                                            <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                        <span class="input-group-text field-valid-indicator d-none" id="genderCheck" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                        <div class="invalid-feedback<?= !empty($fieldErrors['gender']) ? ' d-block' : '' ?>" id="genderFeedback">
                                            <?= htmlspecialchars((string)($fieldErrors['gender'] ?? 'Please select gender.')) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label" for="password">Password</label>
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
                                    <span class="input-group-text field-valid-indicator d-none" id="passwordCheck" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    <div class="invalid-feedback<?= !empty($fieldErrors['password']) ? ' d-block' : '' ?>" id="passwordFeedback">
                                        <?= htmlspecialchars((string)($fieldErrors['password'] ?? 'Password must be at least 6 characters and include letters and numbers.')) ?>
                                    </div>
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
    <script src="../assets/js/register.js"></script>
</body>
</html>
