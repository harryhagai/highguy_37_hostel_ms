<?php
$navPrefix = 'index.php';
$activeNav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | HostelPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/legal-pages.css">
</head>
<body class="legal-body">
    <?php require __DIR__ . '/partials/site_header.php'; ?>

    <main class="container legal-main">
        <ul class="nav legal-switch">
            <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy Policy</a></li>
            <li class="nav-item"><a class="nav-link active" href="terms-of-service.php">Terms of Service</a></li>
            <li class="nav-item"><a class="nav-link" href="refund-policy.php">Refund Policy</a></li>
        </ul>

        <article class="legal-doc">
            <h1>Terms of Service</h1>
            <p class="legal-meta">Last Updated: February 20, 2026</p>

            <section class="legal-section">
                <h2>Agreement to Terms</h2>
                <p class="text-justify">
                    By accessing and using HostelPro, you agree to be bound by these Terms of Service and all applicable
                    laws and policies.
                </p>
            </section>

            <section class="legal-section">
                <h2>1. User Accounts</h2>
                <ul class="legal-list">
                    <li>You are responsible for maintaining the confidentiality of your login credentials.</li>
                    <li>You must provide accurate and current information during registration and profile updates.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>2. Booking and Payments</h2>
                <ul class="legal-list">
                    <li>A booking is only confirmed once payment is verified through our system.</li>
                    <li>Users must follow rules and requirements set by individual hostel managers.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>3. Prohibited Activities</h2>
                <ul class="legal-list">
                    <li>Users may not use the system for illegal activities or harassment.</li>
                    <li>Any attempt to manipulate bookings, payments, or account access is strictly prohibited.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>4. Service Updates</h2>
                <p class="text-justify mb-0">
                    HostelPro may update these terms when needed. Continued use of the platform after updates means you
                    accept the revised terms.
                </p>
            </section>

        </article>
    </main>

    <?php require __DIR__ . '/partials/site_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/ui-spinner.js"></script>
    <script src="assets/js/index.js"></script>
</body>
</html>
