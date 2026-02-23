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
$catalogLimit = 6;
$catalogState = require __DIR__ . '/controllers/common/public_hostels_controller.php';
$publicHostels = $catalogState['hostels'];
$publicHostelStats = $catalogState['stats'];
$publicHostelLocationOptions = $catalogState['location_options'] ?? [];
$publicHostelPriceOptions = $catalogState['price_options'] ?? [];
$contactState = require __DIR__ . '/controllers/common/contact_messages_controller.php';
$contactErrors = $contactState['errors'] ?? [];
$contactSuccessMessage = $contactState['success_message'] ?? null;
$contactForm = $contactState['form'] ?? [
    'name' => '',
    'email' => '',
    'phone' => '',
    'topic' => '',
    'message' => '',
];
$contactCsrfToken = (string)($contactState['csrf_token'] ?? '');
$publicHostelsForModal = array_values(array_map(
    static function (array $hostel): array {
        return [
            'id' => (int)($hostel['id'] ?? 0),
            'name' => (string)($hostel['name'] ?? ''),
            'location' => (string)($hostel['location'] ?? ''),
            'description' => (string)($hostel['description_full'] ?? $hostel['description_preview'] ?? ''),
            'image_url' => (string)($hostel['hostel_image_url'] ?? ''),
            'gender_label' => (string)($hostel['gender_label'] ?? 'All Genders'),
            'rating_label' => (string)($hostel['rating_label'] ?? 'No ratings yet'),
            'starting_price' => isset($hostel['starting_price']) ? (float)$hostel['starting_price'] : null,
            'total_rooms' => (int)($hostel['total_rooms'] ?? 0),
            'free_rooms' => (int)($hostel['free_rooms'] ?? 0),
            'free_beds' => (int)($hostel['free_beds'] ?? 0),
            'bed_capacity' => (int)($hostel['bed_capacity'] ?? 0),
            'rooms' => array_values(array_map(
                static function (array $room): array {
                    return [
                        'id' => (int)($room['id'] ?? 0),
                        'room_number' => (string)($room['room_number'] ?? ''),
                        'room_type' => (string)($room['room_type'] ?? 'Standard'),
                        'description' => (string)($room['description'] ?? ''),
                        'price' => isset($room['price']) ? (float)$room['price'] : 0,
                        'price_display' => (string)($room['price_display'] ?? '0'),
                        'capacity' => (int)($room['capacity'] ?? 0),
                        'free_beds' => (int)($room['free_beds'] ?? 0),
                        'status' => (string)($room['status'] ?? 'full'),
                        'status_label' => (string)($room['status_label'] ?? 'Full'),
                    ];
                },
                (array)($hostel['rooms'] ?? [])
            )),
        ];
    },
    $publicHostels
));
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
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
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
                    <div class="hero-media-wrap d-lg-none mb-3">
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
                    <a href="#hostels" class="hero-scroll-indicator hero-scroll-indicator-inline d-lg-none mb-2" aria-label="Scroll down to available hostels">
                        <span>Scroll Down</span>
                        <i class="bi bi-chevron-double-down" aria-hidden="true"></i>
                    </a>
                    <div class="hero-actions d-flex flex-wrap gap-3">
                        <a href="auth/register.php" class="btn btn-hero-primary">
                            <i class="bi bi-rocket-takeoff me-2"></i>Get Started
                        </a>
                        <a href="hostels.php" class="btn btn-hero-secondary btn-hero-find">
                            <i class="bi bi-buildings me-2"></i>Find Hostel
                        </a>
                    </div>
                    <div class="hero-system-stats mt-4" aria-label="System availability snapshot">
                        <article class="hero-system-stat">
                            <p class="hero-system-stat-label mb-1">Total Hostels In System</p>
                            <h5 class="hero-system-stat-value mb-0"><?= (int)($publicHostelStats['total_hostels'] ?? 0) ?></h5>
                        </article>
                        <article class="hero-system-stat">
                            <p class="hero-system-stat-label mb-1">Locations</p>
                            <h5 class="hero-system-stat-value mb-0"><?= (int)($publicHostelStats['locations'] ?? 0) ?></h5>
                        </article>
                        <article class="hero-system-stat">
                            <p class="hero-system-stat-label mb-1">Free Rooms</p>
                            <h5 class="hero-system-stat-value mb-0"><?= (int)($publicHostelStats['free_rooms'] ?? 0) ?></h5>
                        </article>
                        <article class="hero-system-stat">
                            <p class="hero-system-stat-label mb-1">Free Beds</p>
                            <h5 class="hero-system-stat-value mb-0"><?= (int)($publicHostelStats['free_beds'] ?? 0) ?></h5>
                        </article>
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
        <a href="#hostels" class="hero-scroll-indicator d-none d-lg-inline-flex" aria-label="Scroll down to available hostels">
            <span>Scroll Down</span>
            <i class="bi bi-chevron-double-down" aria-hidden="true"></i>
        </a>
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

    <!-- Public Hostels Section -->
    <section class="public-hostels-section py-5" id="hostels">
        <div class="container">
            <div class="public-hostels-intro text-center mx-auto">
                <p class="about-kicker mb-2">Available Hostels</p>
                <h2 class="section-title public-hostels-title">Explore Hostel Options</h2>
                <p class="about-lead mb-0">
                    Browse active hostels, compare room availability, and choose what fits your location and preference.
                </p>
            </div>

            <div class="public-hostels-stats mb-4">
                <article class="public-mini-stat">
                    <span class="public-mini-icon"><i class="bi bi-buildings"></i></span>
                    <p class="public-mini-label mb-1">Total Hostels</p>
                    <h5 class="mb-0"><?= (int)$publicHostelStats['total_hostels'] ?></h5>
                </article>
                <article class="public-mini-stat">
                    <span class="public-mini-icon"><i class="bi bi-door-open"></i></span>
                    <p class="public-mini-label mb-1">Total Rooms</p>
                    <h5 class="mb-0"><?= (int)$publicHostelStats['total_rooms'] ?></h5>
                </article>
                <article class="public-mini-stat">
                    <span class="public-mini-icon"><i class="bi bi-check2-circle"></i></span>
                    <p class="public-mini-label mb-1">Free Rooms</p>
                    <h5 class="mb-0"><?= (int)$publicHostelStats['free_rooms'] ?></h5>
                </article>
                <article class="public-mini-stat">
                    <span class="public-mini-icon"><i class="bi bi-geo-alt"></i></span>
                    <p class="public-mini-label mb-1">Locations</p>
                    <h5 class="mb-0"><?= (int)$publicHostelStats['locations'] ?></h5>
                </article>
            </div>

            <?php if (empty($publicHostels)): ?>
                <div class="alert alert-light border">No hostels are available at the moment.</div>
            <?php else: ?>
                <div class="public-hostels-filter-card mb-3">
                    <div class="row g-2 g-lg-3 align-items-end">
                        <div class="col-lg-4">
                            <label for="publicHostelSearchInput" class="public-filter-label">Search</label>
                            <div class="public-field-wrap">
                                <i class="bi bi-search"></i>
                                <input type="search" class="form-control public-filter-control" id="publicHostelSearchInput" placeholder="Hostel name or location">
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label for="publicHostelLocationFilter" class="public-filter-label">Location</label>
                            <div class="public-field-wrap">
                                <i class="bi bi-geo-alt"></i>
                                <select class="form-select public-filter-control" id="publicHostelLocationFilter">
                                    <option value="">All locations</option>
                                    <?php foreach ($publicHostelLocationOptions as $location): ?>
                                        <option value="<?= htmlspecialchars(strtolower((string)$location), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$location) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label for="publicHostelPriceFilter" class="public-filter-label">Price Range</label>
                            <div class="public-field-wrap">
                                <i class="bi bi-cash-coin"></i>
                                <select class="form-select public-filter-control" id="publicHostelPriceFilter">
                                    <option value="">Any price</option>
                                    <?php foreach ($publicHostelPriceOptions as $priceOption): ?>
                                        <option value="<?= htmlspecialchars((string)($priceOption['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string)($priceOption['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-8 col-lg-2">
                            <label for="publicHostelTypeFilter" class="public-filter-label">Type</label>
                            <div class="public-field-wrap">
                                <i class="bi bi-people"></i>
                                <select class="form-select public-filter-control" id="publicHostelTypeFilter">
                                    <option value="">All types</option>
                                    <option value="male">Male Only</option>
                                    <option value="female">Female Only</option>
                                    <option value="all">Mixed/All</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="public-filter-actions">
                        <span class="public-filter-results" id="publicHostelResultCount"><?= count($publicHostels) ?> results</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="publicHostelClearFilters">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filters
                        </button>
                    </div>
                </div>

                <div class="public-hostels-carousel-shell">
                    <div class="public-hostels-grid" id="publicHostelsGrid">
                        <?php foreach ($publicHostels as $hostel): ?>
                            <?php
                            $hostelName = (string)($hostel['name'] ?? '');
                            $hostelLocation = (string)($hostel['location'] ?? '');
                            $hostelDescription = (string)($hostel['description_preview'] ?? '');
                            $startingPrice = isset($hostel['starting_price']) ? (float)$hostel['starting_price'] : null;
                            $priceDisplay = $startingPrice !== null ? number_format($startingPrice, 0) : null;
                            $freeRooms = (int)($hostel['free_rooms'] ?? 0);
                            $freeBeds = (int)($hostel['free_beds'] ?? 0);
                            $isHostelFull = $freeRooms <= 0 || $freeBeds <= 0;
                            ?>
                            <article
                                class="public-hostel-card"
                                data-hostel-id="<?= (int)($hostel['id'] ?? 0) ?>"
                                data-search="<?= htmlspecialchars(strtolower($hostelName . ' ' . $hostelLocation . ' ' . $hostelDescription), ENT_QUOTES, 'UTF-8') ?>"
                                data-location="<?= htmlspecialchars(strtolower($hostelLocation), ENT_QUOTES, 'UTF-8') ?>"
                                data-price="<?= htmlspecialchars($startingPrice !== null ? (string)$startingPrice : '', ENT_QUOTES, 'UTF-8') ?>"
                                data-type="<?= htmlspecialchars((string)($hostel['gender'] ?? 'all'), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <div class="public-hostel-media">
                                    <img
                                        src="<?= htmlspecialchars((string)$hostel['hostel_image_url']) ?>"
                                        alt="Hostel Image"
                                        class="public-hostel-image public-hostel-detail-trigger"
                                        data-hostel-id="<?= (int)($hostel['id'] ?? 0) ?>"
                                        role="button"
                                        tabindex="0"
                                        aria-label="Open details for <?= htmlspecialchars($hostelName, ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <div class="public-hostel-top-badges">
                                        <span class="public-hostel-price-badge">
                                            <i class="bi bi-cash-coin me-1"></i>
                                            <?php if ($priceDisplay !== null): ?>
                                                From TSh <?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?>/room
                                            <?php else: ?>
                                                Price on request
                                            <?php endif; ?>
                                        </span>
                                        <span class="public-hostel-rating-badge">
                                            <i class="bi bi-star-fill me-1"></i><?= htmlspecialchars((string)($hostel['rating_label'] ?? 'No ratings yet')) ?>
                                        </span>
                                    </div>

                                    <?php if ($isHostelFull): ?>
                                        <span class="badge bg-danger public-hostel-full-badge">No Free Rooms</span>
                                    <?php endif; ?>
                                    <span class="badge bg-dark public-hostel-gender-badge">
                                        <?= htmlspecialchars((string)$hostel['gender_label']) ?>
                                    </span>

                                    <div class="public-hostel-image-overlay">
                                        <h6 class="mb-1"><?= htmlspecialchars($hostelName) ?></h6>
                                        <button
                                            type="button"
                                            class="public-hostel-map-trigger"
                                            data-location="<?= htmlspecialchars($hostelLocation, ENT_QUOTES, 'UTF-8') ?>"
                                            data-hostel-name="<?= htmlspecialchars($hostelName, ENT_QUOTES, 'UTF-8') ?>"
                                            aria-label="View <?= htmlspecialchars($hostelLocation, ENT_QUOTES, 'UTF-8') ?> on map"
                                        >
                                            <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($hostelLocation) ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="public-hostel-body">
                                    <p class="public-hostel-description mb-3"><?= htmlspecialchars($hostelDescription) ?></p>
                                    <div class="public-hostel-meta mb-3">
                                        <span><i class="bi bi-check-circle"></i> Available Rooms: <b><?= $freeRooms ?></b></span>
                                        <span><i class="bi bi-grid-3x3-gap"></i> Free Beds: <b><?= $freeBeds ?></b></span>
                                        <span><i class="bi bi-door-open"></i> Total Rooms: <b><?= (int)$hostel['total_rooms'] ?></b></span>
                                    </div>
                                    <div class="public-hostel-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 public-hostel-detail-trigger" data-hostel-id="<?= (int)($hostel['id'] ?? 0) ?>">
                                            <i class="bi bi-info-circle me-1"></i>More Info
                                        </button>
                                        <?php if ($isHostelFull): ?>
                                            <span class="btn btn-sm btn-secondary rounded-pill px-3 disabled" aria-disabled="true">
                                                <i class="bi bi-x-circle me-1"></i>Full
                                            </span>
                                        <?php else: ?>
                                            <a href="auth/login.php" class="btn btn-sm btn-hero-primary rounded-pill px-3">
                                                <i class="bi bi-calendar-plus me-1"></i>Book Bed
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="public-hostels-nav" id="publicHostelsNav">
                        <button type="button" class="public-carousel-arrow" id="publicHostelPrev" aria-label="Previous hostels">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <div class="public-hostels-dots" id="publicHostelDots" aria-label="Hostel pages"></div>
                        <button type="button" class="public-carousel-arrow" id="publicHostelNext" aria-label="Next hostels">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="alert alert-light border mt-3 d-none" id="publicHostelNoResults">
                    No hostels match your filters. Try adjusting location, price range, or type.
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="hostels.php" class="btn btn-hero-secondary rounded-pill px-4 public-catalog-btn">
                    <i class="bi bi-buildings me-2"></i>View Full Hostel Catalog
                </a>
            </div>
        </div>
    </section>

    <div class="modal fade" id="hostelDetailModal" tabindex="-1" aria-labelledby="hostelDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content hostel-detail-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hostelDetailTitle">Hostel Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 g-lg-4">
                        <div class="col-lg-5">
                            <img src="assets/images/logo.png" alt="Hostel image" id="hostelDetailImage" class="hostel-detail-image">
                        </div>
                        <div class="col-lg-7">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="badge bg-dark" id="hostelDetailGender">All Genders</span>
                                <span class="badge text-bg-light" id="hostelDetailRating">No ratings yet</span>
                            </div>
                            <p class="mb-2">
                                <button type="button" class="btn btn-link p-0 hostel-detail-map-btn" id="hostelDetailMapBtn">
                                    <i class="bi bi-geo-alt-fill me-1"></i><span id="hostelDetailLocation">-</span>
                                </button>
                            </p>
                            <p class="text-muted mb-3" id="hostelDetailDescription">No description available.</p>

                            <div class="hostel-detail-stats">
                                <article class="hostel-detail-stat">
                                    <span class="label">Starting Price</span>
                                    <strong id="hostelDetailPrice">-</strong>
                                </article>
                                <article class="hostel-detail-stat">
                                    <span class="label">Available Rooms</span>
                                    <strong id="hostelDetailFreeRooms">0</strong>
                                </article>
                                <article class="hostel-detail-stat">
                                    <span class="label">Free Beds</span>
                                    <strong id="hostelDetailFreeBeds">0</strong>
                                </article>
                                <article class="hostel-detail-stat">
                                    <span class="label">Total Rooms</span>
                                    <strong id="hostelDetailTotalRooms">0</strong>
                                </article>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0">Room Details</h6>
                        <small class="text-muted" id="hostelDetailRoomsCount">0 rooms</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle hostel-detail-rooms-table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Beds</th>
                                    <th>Free</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="hostelDetailRoomsBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No room details available.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="auth/login.php" class="btn btn-hero-primary rounded-pill px-3" id="hostelDetailBookBtn">
                        <i class="bi bi-calendar-plus me-1"></i>Book Bed
                    </a>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="hostelMapModal" tabindex="-1" aria-labelledby="hostelMapTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hostelMapTitle">Hostel Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe
                        id="hostelMapFrame"
                        class="hostel-map-frame"
                        src="about:blank"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                        title="Hostel location map"
                    ></iframe>
                    <div class="mt-2">
                        <a id="hostelMapExternalLink" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary rounded-pill">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open in Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

                            <?php if (!empty($contactSuccessMessage)): ?>
                                <div class="alert alert-success" role="alert">
                                    <?= htmlspecialchars((string)$contactSuccessMessage, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($contactErrors)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <ul class="mb-0 ps-3">
                                        <?php foreach ($contactErrors as $contactError): ?>
                                            <li><?= htmlspecialchars((string)$contactError, ENT_QUOTES, 'UTF-8') ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form class="row g-3" method="post" action="#contact" autocomplete="off">
                                <input type="hidden" name="action" value="send_contact_message">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($contactCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

                                <div class="col-md-6">
                                    <label for="contactName" class="contact-label">Full Name</label>
                                    <input
                                        type="text"
                                        class="form-control contact-input"
                                        id="contactName"
                                        name="name"
                                        placeholder="John Doe"
                                        value="<?= htmlspecialchars((string)($contactForm['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label for="contactEmail" class="contact-label">Email Address</label>
                                    <input
                                        type="email"
                                        class="form-control contact-input"
                                        id="contactEmail"
                                        name="email"
                                        placeholder="name@example.com"
                                        value="<?= htmlspecialchars((string)($contactForm['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label for="contactPhone" class="contact-label">Phone Number</label>
                                    <input
                                        type="text"
                                        class="form-control contact-input"
                                        id="contactPhone"
                                        name="phone"
                                        placeholder="+255 7XX XXX XXX"
                                        value="<?= htmlspecialchars((string)($contactForm['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label for="contactTopic" class="contact-label">Topic</label>
                                    <input
                                        type="text"
                                        class="form-control contact-input"
                                        id="contactTopic"
                                        name="topic"
                                        placeholder="Booking Support"
                                        value="<?= htmlspecialchars((string)($contactForm['topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>

                                <div class="col-12">
                                    <label for="contactMessage" class="contact-label">Message</label>
                                    <textarea class="form-control contact-input contact-textarea" id="contactMessage" name="message" rows="5" placeholder="How can we help you?" required><?= htmlspecialchars((string)($contactForm['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
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
    <script id="publicHostelsData" type="application/json"><?= json_encode($publicHostelsForModal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?></script>
    
    <script src="assets/js/index.js"></script>
</body>
</html>
