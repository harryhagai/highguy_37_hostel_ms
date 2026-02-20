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

$navPrefix = '';
$activeNav = 'home';
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
    <?php require __DIR__ . '/partials/site_header.php'; ?>

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
    <section class="about-section py-5" id="about">
        <div class="container">
            <div class="about-intro text-center mx-auto">
                <p class="about-kicker mb-2">Why Students Choose HostelPro</p>
                <h2 class="section-title about-title">About Us at HostelPro</h2>
                <p class="about-lead mb-0">
                    HostelPro is built to help students book trusted hostels quickly, stay safe, and get support throughout their stay.
                </p>
            </div>

            <div class="row g-4 g-lg-5">
                <div class="col-lg-6">
                    <article class="capability-card h-100">
                        <div class="capability-icon-wrap">
                            <i class="bi bi-shield-check capability-icon"></i>
                        </div>
                        <h4 class="capability-title">Book With Confidence</h4>
                        <p class="capability-copy">
                            We make booking simple and transparent, so you can reserve your space without stress.
                        </p>
                        <ul class="capability-list mb-0">
                            <li><i class="bi bi-check2-circle"></i><span>Real-time hostel and bed availability</span></li>
                            <li><i class="bi bi-check2-circle"></i><span>Clear pricing before you confirm booking</span></li>
                            <li><i class="bi bi-check2-circle"></i><span>Instant booking confirmation and updates</span></li>
                        </ul>
                    </article>
                </div>

                <div class="col-lg-6">
                    <article class="capability-card capability-card-alt h-100">
                        <div class="capability-icon-wrap capability-icon-wrap-dark">
                            <i class="bi bi-stars capability-icon capability-icon-dark"></i>
                        </div>
                        <h4 class="capability-title">Services We Offer Students</h4>
                        <p class="capability-copy">
                            Beyond booking, we focus on giving students better accommodation support from start to finish.
                        </p>
                        <ul class="capability-list mb-0">
                            <li><i class="bi bi-check2-circle"></i><span>Trusted hostels verified for student living</span></li>
                            <li><i class="bi bi-check2-circle"></i><span>Simple support for complaints and feedback</span></li>
                            <li><i class="bi bi-check2-circle"></i><span>Flexible booking periods that fit your semester</span></li>
                        </ul>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section py-5" id="contact">
        <div class="container">
            <div class="text-center mx-auto contact-intro">
                <p class="about-kicker mb-2">Contact Us</p>
                <h2 class="section-title contact-title">Let's Help You Book Better</h2>
                <p class="about-lead mb-0">
                    Reach our team directly and we will respond quickly to support your booking journey.
                </p>
            </div>

            <div class="contact-shell">
                <div class="row g-0">
                    <div class="col-lg-4">
                        <div class="contact-info-pane h-100">
                            <h3 class="contact-pane-title">Contact Information</h3>
                            <p class="contact-pane-copy">
                                Talk to us anytime. Our team is ready to help you with hostel selection and booking support.
                            </p>

                            <div class="contact-points">
                                <div class="contact-point">
                                    <span class="contact-point-icon"><i class="bi bi-telephone"></i></span>
                                    <div>
                                        <p class="contact-point-label mb-0">Call Us</p>
                                        <p class="contact-point-value mb-0">+254 700 123456</p>
                                    </div>
                                </div>

                                <div class="contact-point">
                                    <span class="contact-point-icon"><i class="bi bi-envelope"></i></span>
                                    <div>
                                        <p class="contact-point-label mb-0">Email</p>
                                        <p class="contact-point-value mb-0">support@hostelpro.com</p>
                                    </div>
                                </div>

                                <div class="contact-point">
                                    <span class="contact-point-icon"><i class="bi bi-geo-alt"></i></span>
                                    <div>
                                        <p class="contact-point-label mb-0">Location</p>
                                        <p class="contact-point-value mb-0">Nairobi, Kenya</p>
                                    </div>
                                </div>

                                <div class="contact-point">
                                    <span class="contact-point-icon"><i class="bi bi-clock"></i></span>
                                    <div>
                                        <p class="contact-point-label mb-0">Working Hours</p>
                                        <p class="contact-point-value mb-0">Mon - Sat, 8:00 AM - 6:00 PM</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="contact-form-pane">
                            <h3 class="contact-form-title">Send Us a Message</h3>
                            <p class="contact-form-copy">
                                Fill this form and we will get back to you within 24 hours.
                            </p>

                            <form class="row g-3" method="post" action="#" autocomplete="off">
                                <div class="col-md-6">
                                    <label for="contactName" class="contact-label">Full Name</label>
                                    <input type="text" class="form-control contact-input" id="contactName" name="name" placeholder="John Doe" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="contactEmail" class="contact-label">Email Address</label>
                                    <input type="email" class="form-control contact-input" id="contactEmail" name="email" placeholder="name@example.com" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="contactPhone" class="contact-label">Phone Number</label>
                                    <input type="text" class="form-control contact-input" id="contactPhone" name="phone" placeholder="+254 700 000000">
                                </div>

                                <div class="col-md-6">
                                    <label for="contactTopic" class="contact-label">Topic</label>
                                    <input type="text" class="form-control contact-input" id="contactTopic" name="topic" placeholder="Booking Support">
                                </div>

                                <div class="col-12">
                                    <label for="contactMessage" class="contact-label">Message</label>
                                    <textarea class="form-control contact-input contact-textarea" id="contactMessage" name="message" rows="5" placeholder="How can we help you?" required></textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-contact-submit">
                                        <i class="bi bi-send me-2"></i>Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/partials/site_footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/ui-spinner.js"></script>
    
    <script src="assets/js/index.js"></script>
</body>
</html>
