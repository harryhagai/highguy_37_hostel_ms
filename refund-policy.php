<?php
$navPrefix = 'index.php';
$activeNav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Policy | HostelPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/legal-pages.css">
</head>
<body class="legal-body">
    <?php require __DIR__ . '/partials/site_header.php'; ?>

    <main class="container legal-main">
        <ul class="nav legal-switch">
            <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy Policy</a></li>
            <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms of Service</a></li>
            <li class="nav-item"><a class="nav-link active" href="refund-policy.php">Refund Policy</a></li>
        </ul>

        <article class="legal-doc">
            <h1>Refund Policy</h1>
            <p class="legal-meta">Last Updated: February 20, 2026</p>

            <section class="legal-section">
                <h2>1. Eligibility for Refunds</h2>
                <ul class="legal-list">
                    <li><strong>Full Refund:</strong> Requests made within 48 hours of payment and at least 7 days before check-in.</li>
                    <li><strong>Partial Refund:</strong> Requests made after 48 hours but before check-in may include a 10% administrative fee.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>2. Non-Refundable Cases</h2>
                <ul class="legal-list">
                    <li>No refund is issued once the student has checked in or received room or bed keys.</li>
                    <li>Platform service fees are generally non-refundable unless required by law.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>3. Refund Request Process</h2>
                <ul class="legal-list">
                    <li>Submit requests via the Lodge Complaint or Contact Us portal.</li>
                    <li>Approved refunds are processed within 5 to 7 business days to the original payment method.</li>
                </ul>
            </section>

            <section class="legal-section">
                <h2>4. Additional Notes</h2>
                <p class="text-justify mb-0">
                    Refund outcomes may also depend on hostel-specific booking rules. Students are advised to read
                    booking terms carefully before confirming payment.
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
