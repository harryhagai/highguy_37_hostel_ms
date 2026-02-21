<?php
$navPrefix = 'index.php';
$activeNav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | HostelPro</title>
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
            <li class="nav-item"><a class="nav-link active" href="privacy-policy.php">Privacy Policy</a></li>
            <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms of Service</a></li>
            <li class="nav-item"><a class="nav-link" href="refund-policy.php">Refund Policy</a></li>
        </ul>

        <article class="legal-doc">
            <h1>Privacy Policy - HostelPro</h1>
            <p class="legal-meta">Last Updated: February 20, 2026</p>

            <section class="legal-section">
                <h2>1. Information We Collect</h2>
                <p class="text-justify">
                    We collect personal information that you voluntarily provide to us when you register on the website.
                    This helps us serve students better and protect booking integrity.
                </p>
                <ul class="legal-list">
                    <li>Name and contact data (email address and phone number).</li>
                    <li>Academic information (university or college name).</li>
                    <li>Log data (IP address, browser type, and basic device details).</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>2. How We Use Your Information</h2>
                <p class="text-justify">
                    We use collected data to provide safe and reliable services for students and hostel partners.
                </p>
                <ul class="legal-list">
                    <li>Facilitate hostel bookings and account management.</li>
                    <li>Send administrative information and booking updates.</li>
                    <li>Improve platform analytics, reliability, and account security.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>3. Data Protection</h2>
                <p class="text-justify">
                    We apply reasonable technical and organizational safeguards to protect your data against unauthorized
                    access, misuse, or disclosure.
                </p>
            </section>

            <section class="legal-section">
                <h2>4. Contact</h2>
                <p class="text-justify mb-0">
                    For privacy-related requests, contact us through the Contact Us section or email:
                    <strong>support@hostelpro.com</strong>.
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
