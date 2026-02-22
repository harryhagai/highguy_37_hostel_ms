<?php
$navPrefix = 'index.php';
$activeNav = 'hostels';
$catalogLimit = 0;
$catalogState = require __DIR__ . '/controllers/common/public_hostels_controller.php';
$hostels = $catalogState['hostels'];
$stats = $catalogState['stats'];
$locationOptions = $catalogState['location_options'] ?? [];
$priceOptions = $catalogState['price_options'] ?? [];
$hostelsForModal = array_values(array_map(
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
    $hostels
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostels Catalog | HostelPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/public-hostels.css">
</head>
<body class="public-hostels-body">
    <?php require __DIR__ . '/partials/site_header.php'; ?>

    <main class="public-hostels-main">
        <section class="catalog-hero">
            <div class="container">
                <p class="catalog-kicker mb-2">Hostel Catalog</p>
                <h1 class="catalog-title">Find a Hostel That Fits You</h1>
                <p class="catalog-lead mb-0">
                    Explore active hostels with room and availability details before creating your booking.
                </p>
            </div>
        </section>

        <section class="catalog-section pb-5">
            <div class="container">
                <div class="catalog-stats mb-4">
                    <article class="catalog-stat">
                        <p class="catalog-stat-label mb-1">Total Hostels</p>
                        <h5 class="mb-0"><?= (int)$stats['total_hostels'] ?></h5>
                    </article>
                    <article class="catalog-stat">
                        <p class="catalog-stat-label mb-1">Total Rooms</p>
                        <h5 class="mb-0"><?= (int)$stats['total_rooms'] ?></h5>
                    </article>
                    <article class="catalog-stat">
                        <p class="catalog-stat-label mb-1">Free Rooms</p>
                        <h5 class="mb-0"><?= (int)$stats['free_rooms'] ?></h5>
                    </article>
                    <article class="catalog-stat">
                        <p class="catalog-stat-label mb-1">Locations</p>
                        <h5 class="mb-0"><?= (int)$stats['locations'] ?></h5>
                    </article>
                </div>

                <?php if (empty($hostels)): ?>
                    <div class="alert alert-light border">No hostels available right now.</div>
                <?php else: ?>
                    <section class="catalog-filters-shell mb-3" aria-label="Catalog filters">
                        <div class="row g-2 g-lg-3 align-items-end">
                            <div class="col-lg-4">
                                <label for="catalogSearchInput" class="catalog-filter-label">Search</label>
                                <div class="catalog-field-wrap">
                                    <i class="bi bi-search"></i>
                                    <input
                                        type="search"
                                        class="form-control catalog-filter-control"
                                        id="catalogSearchInput"
                                        placeholder="Hostel name or location"
                                    >
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="catalogLocationFilter" class="catalog-filter-label">Location</label>
                                <div class="catalog-field-wrap">
                                    <i class="bi bi-geo-alt"></i>
                                    <select class="form-select catalog-filter-control" id="catalogLocationFilter">
                                        <option value="">All locations</option>
                                        <?php foreach ($locationOptions as $location): ?>
                                            <option value="<?= htmlspecialchars(strtolower((string)$location), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string)$location, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="catalogPriceFilter" class="catalog-filter-label">Price Range</label>
                                <div class="catalog-field-wrap">
                                    <i class="bi bi-cash-coin"></i>
                                    <select class="form-select catalog-filter-control" id="catalogPriceFilter">
                                        <option value="">Any price</option>
                                        <?php foreach ($priceOptions as $priceOption): ?>
                                            <option value="<?= htmlspecialchars((string)($priceOption['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string)($priceOption['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-8 col-lg-2">
                                <label for="catalogTypeFilter" class="catalog-filter-label">Type</label>
                                <div class="catalog-field-wrap">
                                    <i class="bi bi-people"></i>
                                    <select class="form-select catalog-filter-control" id="catalogTypeFilter">
                                        <option value="">All types</option>
                                        <option value="male">Male Only</option>
                                        <option value="female">Female Only</option>
                                        <option value="all">Mixed/All</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="catalog-filter-actions">
                            <span class="catalog-filter-results" id="catalogResultCount"><?= count($hostels) ?> results</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="catalogClearFilters">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filters
                            </button>
                        </div>
                    </section>

                    <div class="catalog-grid" id="catalogGrid">
                        <?php foreach ($hostels as $hostel): ?>
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
                                        alt="Hostel image"
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

                    <div class="alert alert-light border mt-3 d-none" id="catalogNoResults">
                        No hostels match your filters. Try adjusting location, price range, or type.
                    </div>
                <?php endif; ?>
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
    </main>

    <?php require __DIR__ . '/partials/site_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/ui-spinner.js"></script>
    <script src="assets/js/index.js"></script>
    <script id="catalogHostelsData" type="application/json"><?= json_encode($hostelsForModal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?></script>
    <script src="assets/js/public-hostels.js"></script>
</body>
</html>
