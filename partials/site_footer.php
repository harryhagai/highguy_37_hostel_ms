<?php
$navPrefix = isset($navPrefix) ? $navPrefix : '';
$homeHref = $navPrefix === '' ? '#home' : $navPrefix . '#home';
$aboutHref = $navPrefix === '' ? '#about' : $navPrefix . '#about';
$contactHref = $navPrefix === '' ? '#contact' : $navPrefix . '#contact';
?>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4 g-lg-5 footer-main">
            <div class="col-lg-4 col-md-6">
                <a href="<?= htmlspecialchars($homeHref) ?>" class="footer-brand">
                    <span class="footer-brand-icon">
                        <i class="bi bi-buildings-fill"></i>
                    </span>
                    <span class="footer-brand-text">Hostel<span>Pro</span></span>
                </a>
                <p class="footer-summary mb-0">
                    Tunawasaidia wanafunzi kupata hostel bora, salama, na inayofaa bajeti yao kwa urahisi zaidi.
                </p>
                <div class="footer-socials mt-4">
                    <a href="#" class="footer-social-icon" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="footer-social-icon" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    <a href="#" class="footer-social-icon" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="footer-social-icon" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?= htmlspecialchars($homeHref) ?>">Home</a></li>
                    <li><a href="<?= htmlspecialchars($aboutHref) ?>">About Us</a></li>
                    <li><a href="<?= htmlspecialchars($contactHref) ?>">Contact Us</a></li>
                    <li><a href="register.php">Get Started</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Support</h5>
                <ul class="footer-links">
                    <li><a href="login.php">Student Login</a></li>
                    <li><a href="register.php">Create Account</a></li>
                    <li><a href="mailto:support@hostelpro.com">support@hostelpro.com</a></li>
                    <li><a href="tel:+254700123456">+254 700 123456</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Legal</h5>
                <ul class="footer-links">
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    <li><a href="terms-of-service.php">Terms of Service</a></li>
                    <li><a href="refund-policy.php">Refund Policy</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="mb-0">&copy; <?= date('Y') ?> HostelPro Student Hostel Finder. All rights reserved.</p>
            <p class="mb-0 footer-bottom-note">Built for students, trusted by campuses.</p>
        </div>
    </div>
</footer>
