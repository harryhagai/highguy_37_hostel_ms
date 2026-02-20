<?php
$heroImageFiles = glob(__DIR__ . '/assets/images/hero/*.{jpg,jpeg,png,webp,avif,gif}', GLOB_BRACE);
sort($heroImageFiles);

$heroImages = array_map(
    static function ($filePath) {
        return 'assets/images/hero/' . rawurlencode(basename($filePath));
    },
    $heroImageFiles ?: []
);

if (empty($heroImages)) {
    $heroImages = ['assets/images/bg1111.jpg'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelPro Student Hostel Finder</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg fixed-top site-header">
        <div class="container">
            <a class="navbar-brand header-brand" href="#home">
                <span class="header-brand-icon">
                    <i class="bi bi-buildings-fill"></i>
                </span>
                <span class="header-brand-text">
                    <strong>HostelPro</strong>
                    <small>Student Hostel Finder</small>
                </span>
            </a>
            <button
                class="navbar-toggler header-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto header-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">
                            <i class="bi bi-house-door nav-link-icon" aria-hidden="true"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">
                            <i class="bi bi-info-circle nav-link-icon" aria-hidden="true"></i>
                            <span>About</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">
                            <i class="bi bi-envelope-paper nav-link-icon" aria-hidden="true"></i>
                            <span>Contact</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2 mt-3 mt-lg-0">
                    <a class="btn btn-header-outline" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
                    <a class="btn btn-header-solid" href="register.php"><i class="bi bi-person-plus me-1"></i> Create Account</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="hero-orb hero-orb-one" aria-hidden="true"></div>
        <div class="hero-orb hero-orb-two" aria-hidden="true"></div>
        <div class="container hero-content">
            <div class="row g-4 g-lg-5 align-items-center hero-grid">
                <div class="col-lg-6">
                    <span class="hero-eyebrow">Efficient & Reliable</span>
                    <h1 class="hero-heading mt-3 mb-3">
                        Find Your Ideal Student
                        <span class="hero-heading-accent">Hostel Faster.</span>
                    </h1>
                    <p class="hero-description mb-4">
                        Compare hostels, view key details, and start booking with confidence from one clean student-friendly platform.
                    </p>
                    <div class="hero-actions d-flex flex-wrap gap-3">
                        <a href="register.php" class="btn btn-hero-primary">
                            <i class="bi bi-rocket-takeoff me-2"></i>Get Started
                        </a>
                        <a href="#about" class="btn btn-hero-secondary">
                            <i class="bi bi-info-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block">
                    <div class="hero-media-wrap">
                        <div class="hero-media-card">
                            <img
                                src="<?= htmlspecialchars($heroImages[0]) ?>"
                                alt="Student hostel view"
                                class="hero-media-image"
                                data-hero-images='<?= htmlspecialchars(json_encode($heroImages, JSON_UNESCAPED_SLASHES)) ?>'
                                data-hero-interval="3000"
                            >
                        </div>
                        <div class="hero-stat-card">
                            <span class="hero-stat-icon">
                                <i class="bi bi-check2-circle"></i>
                            </span>
                            <div>
                                <p class="hero-stat-title mb-0">Verified Listings</p>
                                <small class="hero-stat-subtitle">Availability updated regularly</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section class="py-5" id="about">
        <div class="container">
            <h2 class="section-title">About Our System</h2>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="feature-box">
                        <i class="bi bi-person-badge feature-icon"></i>
                        <h4>User Functionality</h4>
                        <ul class="text-start mt-3">
                            <li>Register and log in to the system</li>
                            <li>Manage personal profile and change password</li>
                            <li>Book hostel rooms and view booking details</li>
                            <li>Lodge complaints and check complaint status</li>
                            <li>Give feedback on hostel services</li>
                            <li>View last login details and notifications</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-box">
                        <i class="bi bi-person-gear feature-icon"></i>
                        <h4>Admin Functionality</h4>
                        <ul class="text-start mt-3">
                            <li>Admin login and profile management</li>
                            <li>Add, edit, and delete courses and rooms</li>
                            <li>Register students and manage student profiles</li>
                            <li>Assign rooms and manage fees</li>
                            <li>View and manage complaints and feedback</li>
                            <li>View access logs and generate reports</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5 bg-light" id="contact">
        <div class="container">
            <h2 class="section-title">Contact Us</h2>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card contact-card h-100">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-geo-alt-fill feature-icon"></i>
                                    <h4>Our Location</h4>
                                    <p>123 Hostel Street, Campus Town<br>Nairobi, Kenya</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card contact-card h-100">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-telephone-fill feature-icon"></i>
                                    <h4>Call Us</h4>
                                    <p>+254 700 123456<br>+254 720 654321</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card contact-card h-100">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-envelope-fill feature-icon"></i>
                                    <h4>Email Us</h4>
                                    <p>info@hostelpro.com<br>support@hostelpro.com</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card contact-card h-100">
                                <div class="card-body text-center p-4">
                                    <i class="bi bi-clock-fill feature-icon"></i>
                                    <h4>Working Hours</h4>
                                    <p>Monday - Friday: 8:00 - 17:00<br>Saturday: 9:00 - 14:00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center">
        <div class="container">
            <div class="mb-3">
                <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
                <a href="#" class="social-icon"><i class="bi bi-twitter"></i></a>
                <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
                <a href="#" class="social-icon"><i class="bi bi-linkedin"></i></a>
            </div>
            <p class="mb-0">&copy; <?php echo date('Y'); ?> HostelPro Student Hostel Finder. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/ui-spinner.js"></script>
    
    <script src="assets/js/index.js"></script>
</body>
</html>




