<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelPro Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --aqua: #1ccad8;
            --aqua-dark: #11998e;
            --accent: #f6c23e;
            --primary: #1ccad8;
            --secondary: #11998e;
            --white: #fff;
            --dark: #233142;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
            color: var(--dark);
        }
        .navbar {
            background: var(--white);
            box-shadow: 0 2px 8px rgba(28,202,216,0.08);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 2rem;
            color: var(--aqua-dark) !important;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5em;
        }
        .nav-link {
            color: var(--aqua-dark) !important;
            font-weight: 500;
            margin: 0 12px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-link:hover, .nav-link:focus {
            color: var(--aqua) !important;
        }
        .btn-primary {
            background: var(--aqua);
            border: none;
            font-weight: 600;
            border-radius: 25px;
            padding: 10px 28px;
            transition: background 0.3s, transform 0.3s;
        }
        .btn-primary:hover {
            background: var(--aqua-dark);
            transform: translateY(-2px) scale(1.05);
        }
        .btn-success {
            background: var(--accent);
            color: var(--dark);
            border: none;
            font-weight: 600;
            border-radius: 25px;
            padding: 10px 28px;
            transition: background 0.3s, color 0.3s, transform 0.3s;
        }
        .btn-success:hover {
            background: #ffe082;
            color: var(--aqua-dark);
            transform: translateY(-2px) scale(1.05);
        }
        /* Hero Section */
        .hero-section {
            position: relative;
            color: var(--white);
            padding: 120px 0 90px;
            border-radius: 0 0 32px 32px;
            margin-bottom: 56px;
            text-align: center;
            background: linear-gradient(120deg, rgba(28,202,216,0.85) 60%, rgba(17,153,142,0.85) 100%);
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('assets/images/bg1111.jpg') center center/cover no-repeat;
            opacity: 0.35;
            z-index: 0;
        }
        .hero-content {
            position: relative;
            z-index: 2;
        }
        .main-hostel-icon {
            font-size: 4.5rem;
            color: var(--accent);
            margin-bottom: 18px;
            text-shadow: 0 4px 18px rgba(17,153,142,0.18);
            animation: iconPop 1.2s;
        }
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5em;
            animation: fadeInDown 1s;
        }
        .hero-highlight {
            display: inline-block;
            background: var(--accent);
            color: var(--aqua-dark);
            padding: 0 0.6em;
            border-radius: 8px;
            font-size: 1.5rem;
            font-weight: 600;
            margin-left: 0.5em;
            animation: colorPulse 2.2s infinite alternate;
        }
        .hero-section .lead {
            font-size: 1.2rem;
            font-weight: 400;
            margin-bottom: 2.5em;
            animation: fadeInUp 1.2s 0.3s backwards;
        }
        .hero-icons {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .hero-icon-box {
            background: rgba(255,255,255,0.12);
            border-radius: 18px;
            padding: 18px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 4px 18px rgba(28,202,216,0.12);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .hero-icon-box:hover {
            transform: translateY(-10px) scale(1.07);
            box-shadow: 0 12px 32px rgba(28,202,216,0.16);
            background: rgba(255,255,255,0.22);
        }
        .hero-icon {
            font-size: 2.7rem;
            color: var(--white);
            margin-bottom: 8px;
            text-shadow: 0 2px 8px rgba(17,153,142,0.23);
        }
        @keyframes iconPop {
            0% { transform: scale(0.7);}
            80% { transform: scale(1.2);}
            100% { transform: scale(1);}
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-40px);}
            to { opacity: 1; transform: translateY(0);}
        }
        @keyframes colorPulse {
            0% { background: var(--accent); color: var(--aqua-dark);}
            100% { background: var(--aqua); color: var(--white);}
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px);}
            to { opacity: 1; transform: translateY(0);}
        }
        /* About Section */
        .section-title {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 38px;
            text-align: center;
            color: var(--aqua-dark);
            position: relative;
            letter-spacing: 1px;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 72px;
            height: 4px;
            background: var(--accent);
            border-radius: 2px;
            margin: 18px auto 0;
            opacity: 0.7;
            animation: fadeInUp 1s 0.2s backwards;
        }
        .feature-icon {
            font-size: 2.5rem;
            color: var(--aqua);
            margin-bottom: 18px;
            transition: transform 0.3s, color 0.3s;
            animation: iconPop 1.2s;
        }
        .feature-box {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(28,202,216,0.09);
            padding: 36px 20px 30px;
            margin-bottom: 24px;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            text-align: center;
        }
        .feature-box:hover .feature-icon {
            color: var(--aqua-dark);
            transform: scale(1.15) rotate(-8deg);
        }
        .feature-box:hover {
            transform: translateY(-10px) scale(1.025);
            box-shadow: 0 12px 32px rgba(28,202,216,0.13);
        }
        /* Contact Cards */
        .contact-card {
            background: var(--white);
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(28,202,216,0.10);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 18px;
        }
        .contact-card:hover {
            transform: translateY(-8px) scale(1.04);
            box-shadow: 0 12px 32px rgba(28,202,216,0.16);
        }
        /* Footer */
        footer {
            background: var(--aqua-dark);
            color: var(--white);
            padding: 32px 0 18px;
            margin-top: 60px;
            border-radius: 24px 24px 0 0;
        }
        .social-icon {
            color: var(--white);
            font-size: 1.4rem;
            margin: 0 12px;
            transition: color 0.3s, transform 0.3s;
            display: inline-block;
        }
        .social-icon:hover {
            color: var(--accent);
            transform: scale(1.18) rotate(-7deg);
        }
        @media (max-width: 767px) {
            .hero-title { font-size: 1.4rem;}
            .section-title { font-size: 1.2rem;}
            .hero-section { padding: 70px 0 50px;}
            .hero-icons { flex-direction: column; gap: 18px;}
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-house-door-fill"></i> HostelPro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="#home"><i class="bi bi-house-door"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about"><i class="bi bi-info-circle"></i> About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact"><i class="bi bi-envelope"></i> Contact</a></li>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-primary" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="register.php"><i class="bi bi-person-plus"></i> Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container hero-content">
            <div class="main-hostel-icon mb-2">
                <i class="bi bi-house-fill"></i>
            </div>
            <h1 class="hero-title mb-3">
                Welcome to
                <span class="hero-highlight">HostelPro</span>
                Management System
            </h1>
            <div class="hero-icons mb-3">
                <div class="hero-icon-box">
                    <i class="bi bi-person-badge hero-icon"></i>
                    <div>User Module</div>
                </div>
                <div class="hero-icon-box">
                    <i class="bi bi-person-gear hero-icon"></i>
                    <div>Admin Module</div>
                </div>
                <div class="hero-icon-box">
                    <i class="bi bi-building hero-icon"></i>
                    <div>Hostel Facilities</div>
                </div>
                <div class="hero-icon-box">
                    <i class="bi bi-graph-up-arrow hero-icon"></i>
                    <div>Analytics</div>
                </div>
                <div class="hero-icon-box">
                    <i class="bi bi-shield-check hero-icon"></i>
                    <div>Security</div>
                </div>
            </div>
            <p class="lead mb-4">Efficient, reliable, and user-friendly solution for managing your hostel operations.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="login.php" class="btn btn-light btn-lg"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                <a href="register.php" class="btn btn-success btn-lg"><i class="bi bi-person-plus"></i> Register</a>
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
            <p class="mb-0">&copy; <?php echo date('Y'); ?> HostelPro Management System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/ui-spinner.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                if (this.getAttribute('href') !== '#') {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
        // Navbar shadow on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 40) {
                navbar.classList.add('shadow');
            } else {
                navbar.classList.remove('shadow');
            }
        });
    </script>
</body>
</html>
