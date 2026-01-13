<?php
// Start session for CSRF and messages
session_start();

// Include PDO connection (update path if needed)
require_once __DIR__ . '/config/db_connection.php';

// CSRF token generation and validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
$errors = [];
$username = $email = $phone = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    }

    // Username: letters only
    $username = trim($_POST['username']);
    if (!preg_match('/^[A-Za-z]+$/', $username)) {
        $errors[] = "Username must contain letters only (no spaces or numbers).";
    }

    // Email: must be @gmail.com
    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/', $email)) {
        $errors[] = "Email must be a valid @gmail.com address.";
    }

    // Phone: Tanzania format +2557XXXXXXXX or 07XXXXXXXX
    $phone = trim($_POST['phone']);
    if (!preg_match('/^(?:\+2557\d{8}|07\d{8})$/', $phone)) {
        $errors[] = "Phone number must be in Tanzania format: +2557XXXXXXXX or 07XXXXXXXX.";
    }

    // Password: min 6 chars
    $password = $_POST['password'];
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    // If no errors, process registration
    if (empty($errors)) {
        // Check for duplicate email/username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $errors[] = "Email or Username already exists.";
        } else {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $phone, $hashed_password])) {
                $_SESSION['success'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit;
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --aqua: #00FFF0;
            --aqua-dark: #11998e;
            --accent: #f6c23e;
            --white: #fff;
            --dark: #233142;
            --card-shadow: 0 6px 24px rgba(0,255,240,0.09);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(120deg, var(--aqua) 0%, var(--white) 100%);
            min-height: 100vh;
        }
        .navbar {
            background: var(--aqua-dark);
            box-shadow: 0 2px 8px rgba(0,255,240,0.08);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 2rem;
            color: var(--white) !important;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5em;
        }
        .nav-link {
            color: var(--white) !important;
            font-weight: 500;
            margin: 0 12px;
            transition: color 0.3s;
        }
        .nav-link:hover, .nav-link:focus {
            color: var(--aqua) !important;
        }
        .register-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container-register {
            display: flex;
            min-height: 80vh;
            width: 100%;
            max-width: 900px;
            margin: 2rem auto;
            background: transparent;
            box-shadow: none;
            border-radius: 20px;
            overflow: hidden;
            gap: 0;
        }
        .register-left, .register-right {
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: transparent;
            padding: 2rem 1.5rem;
        }
        .register-left {
            background: var(--white);
            border-radius: 20px 0 0 20px;
            box-shadow: var(--card-shadow);
            min-width: 0;
            text-align: center;
        }
        .register-img {
            width: 170px;
            height: 170px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1.2rem;
            box-shadow: 0 0 24px var(--aqua-dark);
            border: 6px solid var(--aqua);
        }
        .register-text h2 {
            font-weight: 700;
            color: var(--aqua-dark);
            margin-bottom: 0.5rem;
        }
        .register-text p {
            color: var(--dark);
            font-size: 1.1rem;
        }
        .register-right {
            background: transparent;
            justify-content: center;
            align-items: center;
            border-radius: 0 20px 20px 0;
        }
        .card {
            background: var(--white);
            color: var(--dark);
            max-width: 400px;
            width: 100%;
            border-radius: 20px;
            padding: 32px 24px;
            box-shadow: var(--card-shadow);
        }
        .card h3 {
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--aqua-dark);
        }
        .input-group-text {
            background: var(--aqua-dark);
            color: white;
            border: none;
            border-radius: 12px 0 0 12px;
        }
        .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
            box-shadow: none;
            font-size: 1rem;
            color: var(--dark);
        }
        .form-control:focus {
            border-color: var(--aqua);
            box-shadow: 0 0 8px var(--aqua);
        }
        .btn-register {
            background: linear-gradient(90deg, var(--aqua-dark), var(--aqua));
            color: #fff;
            border: none;
            padding: 12px 0;
            font-size: 1.1rem;
            border-radius: 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px var(--aqua-dark);
            cursor: pointer;
            width: 100%;
            transition: background 0.4s, color 0.3s, box-shadow 0.3s, transform 0.25s;
        }
        .btn-register:hover, .btn-register:focus {
            background: linear-gradient(90deg, var(--aqua), var(--aqua-dark));
            color: #fff;
            transform: scale(1.03);
            box-shadow: 0 8px 32px 0 var(--aqua), 0 4px 18px var(--accent);
        }
        .alert {
            border-radius: 12px;
            font-weight: 600;
        }
        .footer {
            background: var(--aqua-dark);
            color: #fff;
            text-align: center;
            padding: 18px 0;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: auto;
            box-shadow: 0 -4px 12px var(--aqua);
        }
        .social-links a {
            color: #fff;
            margin: 0 8px;
            font-size: 1.2rem;
            transition: color 0.3s;
        }
        .social-links a:hover {
            color: var(--accent);
        }
        @media (max-width: 991.98px) {
            .container-register {
                flex-direction: column;
                max-width: 95vw;
                min-height: unset;
            }
            .register-left, .register-right {
                border-radius: 20px;
                margin-bottom: 1.5rem;
                padding: 2rem 1rem;
            }
            .register-left { border-radius: 20px 20px 0 0; }
            .register-right { border-radius: 0 0 20px 20px; }
        }
    </style>
</head>
<body>
    <!-- Navbar/Header -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-house-door-fill"></i> HostelPro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse d-none d-lg-flex">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php#home"><i class="bi bi-house-door"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#about"><i class="bi bi-info-circle"></i> About</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact"><i class="bi bi-envelope"></i> Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Register Section -->
    <section class="register-section">
        <div class="container-register">
            <!-- Left: Image and Welcome Text -->
            <div class="register-left">
                <img src="assets/images/bg1 (11).jpg" alt="Welcome to HostelPro" class="register-img">
                <div class="register-text">
                    <h2>Welcome to HostelPro!</h2>
                    <p>Register now to enjoy seamless hostel booking, secure management, and the best student experience. Join our community today!</p>
                </div>
            </div>
            <!-- Right: Register Form -->
            <div class="register-right">
                <div class="card">
                    <h3><i class="bi bi-person-plus-fill"></i> Create Account</h3>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off" novalidate>
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <!-- Username: Letters only -->
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="username" placeholder="Username"
                                   value="<?= htmlspecialchars($username) ?>" required pattern="[A-Za-z]+"
                                   title="Letters only, no spaces or numbers">
                        </div>
                        <!-- Email: Must be @gmail.com -->
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="Email"
                                   value="<?= htmlspecialchars($email) ?>" required
                                   pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
                                   title="Must be a valid @gmail.com email">
                        </div>
                        <!-- Phone: Tanzania format -->
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="text" class="form-control" name="phone" placeholder="Phone Number"
                                   value="<?= htmlspecialchars($phone) ?>" required
                                   pattern="^(\+2557\d{8}|07\d{8})$"
                                   title="Format: +2557XXXXXXXX or 07XXXXXXXX">
                        </div>
                        <!-- Password: Minimum 6 chars -->
                        <div class="mb-3 input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="Password"
                                   required minlength="6" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-register">
                            <i class="bi bi-person-plus"></i> Register
                        </button>
                    </form>
                    <div class="text-center mt-3">
                        Already have an account?
                        <a href="login.php" class="text-decoration-none" style="color: var(--aqua-dark);">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Footer -->
    <footer class="footer">
        <div>
            <span>© <?= date('Y') ?> HostelPro. All rights reserved.</span>
            <span class="social-links ms-3">
                <a href="#"><i class="bi bi-facebook"></i></a>
                <a href="#"><i class="bi bi-twitter"></i></a>
                <a href="#"><i class="bi bi-instagram"></i></a>
                <a href="#"><i class="bi bi-linkedin"></i></a>
            </span>
        </div>
    </footer>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
