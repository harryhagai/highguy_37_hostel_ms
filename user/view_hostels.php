<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/view_hostels_controller.php';
$hostels = $state['hostels'];
$stats = $state['stats'];
$locationOptions = $state['location_options'];
$genderOptions = $state['gender_options'];
?>
<div class="container-fluid px-0 hostels-grid-page">
    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Hostels</p>
            <h5 class="mb-0"><?= (int)$stats['total_hostels'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Rooms</p>
            <h5 class="mb-0"><?= (int)$stats['total_rooms'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Free Rooms</p>
            <h5 class="mb-0"><?= (int)$stats['free_rooms'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Locations</p>
            <h5 class="mb-0"><?= (int)$stats['locations'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card mb-3">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Hostels</h4>
                <p class="text-muted mb-0">Browse all available hostels using card grid view.</p>
            </div>
        </div>

        <div class="alert alert-info py-2 px-3 small mb-3 hostel-booking-flow-note">
            <i class="bi bi-info-circle me-1"></i>Select hostel first, then room, then bed. Booking flow: choose room, then select an available bed inside that room.
        </div>

        <div class="users-filters mb-2">
            <div class="row g-2 align-items-center">
                <div class="col-xl-4 col-lg-6 col-sm-6">
                    <div class="users-field-icon users-field-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control form-control-sm" id="userHostelsSearchInput" placeholder="Search hostel or location">
                        <button type="button" id="clearUserHostelsSearch" class="users-field-clear" aria-label="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-geo-alt"></i>
                        <select id="userHostelsLocationFilter" class="form-select form-select-sm">
                            <option value="">All Locations</option>
                            <?php foreach ($locationOptions as $location): ?>
                                <option value="<?= htmlspecialchars(strtolower((string)$location), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$location) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-people"></i>
                        <select id="userHostelsGenderFilter" class="form-select form-select-sm">
                            <option value="">All Gender</option>
                            <?php foreach ($genderOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars((string)$value) ?>"><?= htmlspecialchars((string)$label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-door-open"></i>
                        <select id="userHostelsAvailabilityFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="free">Has Free Rooms</option>
                            <option value="full">Full</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                <span class="users-result-count text-muted small" id="userHostelsResultCount"><?= count($hostels) ?> results</span>
                <a href="user_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-house-door me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div id="studentHostelsGrid" class="student-hostel-grid">
        <?php foreach ($hostels as $hostel): ?>
            <?php
            $hostelData = [
                'id' => (int)$hostel['id'],
                'name' => (string)$hostel['name'],
                'location' => (string)$hostel['location'],
                'description' => (string)$hostel['description'],
                'image' => (string)$hostel['hostel_image_url'],
                'gender_label' => (string)$hostel['gender_label'],
                'total_rooms' => (int)$hostel['total_rooms'],
                'free_rooms' => (int)$hostel['free_rooms'],
                'bed_capacity' => (int)$hostel['bed_capacity'],
                'priced_rooms' => (int)($hostel['priced_rooms'] ?? 0),
                'room_price_summary' => (string)($hostel['room_price_summary'] ?? 'Price not set'),
                'room_price_min_label' => (string)($hostel['room_price_min_label'] ?? '-'),
                'room_price_max_label' => (string)($hostel['room_price_max_label'] ?? '-'),
            ];
            ?>
            <article
                class="student-hostel-card"
                data-search="<?= htmlspecialchars(strtolower((string)$hostel['name'] . ' ' . (string)$hostel['location'])) ?>"
                data-location="<?= htmlspecialchars(strtolower((string)$hostel['location'])) ?>"
                data-gender="<?= htmlspecialchars((string)$hostel['gender']) ?>"
                data-availability="<?= (int)$hostel['free_rooms'] > 0 ? 'free' : 'full' ?>"
            >
                <div class="student-hostel-media">
                    <img src="<?= htmlspecialchars((string)$hostel['hostel_image_url']) ?>" alt="Hostel Image" class="student-hostel-thumb">
                    <?php if ((int)$hostel['free_rooms'] <= 0): ?>
                        <span class="badge bg-danger no-rooms-badge">No Free Rooms</span>
                    <?php endif; ?>
                </div>
                <div class="student-hostel-body">
                    <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                        <h5 class="mb-0"><?= htmlspecialchars((string)$hostel['name']) ?></h5>
                        <span class="badge text-bg-light"><?= htmlspecialchars((string)$hostel['gender_label']) ?></span>
                    </div>
                    <p class="text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)$hostel['location']) ?></p>
                    <p class="mb-3"><?= htmlspecialchars((string)$hostel['description_preview']) ?></p>

                    <div class="student-hostel-stats mb-3">
                        <span><i class="bi bi-door-open"></i> Rooms: <b><?= (int)$hostel['total_rooms'] ?></b></span>
                        <span><i class="bi bi-check-circle"></i> Free: <b><?= (int)$hostel['free_rooms'] ?></b></span>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-auto">
                        <button
                            class="btn btn-outline-secondary btn-sm view-hostel-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#studentHostelModal"
                            data-hostel='<?= htmlspecialchars(json_encode($hostelData), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                        <?php if ((int)$hostel['free_rooms'] > 0): ?>
                            <a href="user_dashboard_layout.php?page=book_bed&hostel_id=<?= (int)$hostel['id'] ?>" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-calendar-plus me-1"></i>Book Bed
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="bi bi-x-circle me-1"></i>Full
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <div id="userHostelsNoResults" class="alert alert-light border <?= empty($hostels) ? '' : 'd-none' ?>">
            <?= empty($hostels) ? 'No hostels available right now.' : 'No hostels match your filters.' ?>
        </div>
    </div>
</div>

<div class="modal fade" id="studentHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-buildings me-2"></i>Hostel Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <img id="studentModalHostelImage" src="" alt="Hostel" class="img-fluid rounded w-100 modal-hostel-image">
                        <div class="alert alert-info py-2 px-3 small mt-2 mb-0 hostel-booking-flow-note">
                            <i class="bi bi-info-circle me-1"></i>Select hostel first, then room, then bed. Booking flow: choose room, then select an available bed inside that room.
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h4 id="studentModalHostelName" class="mb-1"></h4>
                        <p id="studentModalHostelLocation" class="text-muted mb-2"></p>
                        <span id="studentModalHostelGender" class="badge text-bg-light mb-2"></span>
                        <p id="studentModalHostelDesc" class="mb-3"></p>
                        <div class="d-flex gap-3 flex-wrap">
                            <span class="badge text-bg-secondary">Rooms: <span id="studentModalTotalRooms">0</span></span>
                            <span class="badge text-bg-success">Free: <span id="studentModalFreeRooms">0</span></span>
                            <span class="badge text-bg-primary">Beds: <span id="studentModalBedCapacity">0</span></span>
                        </div>

                        <div class="hostel-detail-meta mt-3">
                            <div class="hostel-detail-box">
                                <p class="mb-1 small text-muted">Room/Bed Price Range</p>
                                <h6 class="mb-0" id="studentModalRoomPriceSummary">Price not set</h6>
                            </div>
                            <div class="hostel-detail-box">
                                <p class="mb-1 small text-muted">Rooms With Price</p>
                                <h6 class="mb-0"><span id="studentModalPricedRooms">0</span> rooms</h6>
                            </div>
                            <div class="hostel-detail-box">
                                <p class="mb-1 small text-muted">Lowest Price</p>
                                <h6 class="mb-0" id="studentModalMinPrice">-</h6>
                            </div>
                            <div class="hostel-detail-box">
                                <p class="mb-1 small text-muted">Highest Price</p>
                                <h6 class="mb-0" id="studentModalMaxPrice">-</h6>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <a
                    href="#"
                    id="studentModalSelectRoomBtn"
                    data-spa-page="book_room"
                    data-no-spinner="true"
                    data-bs-dismiss="modal"
                    class="btn btn-outline-primary">
                    <i class="bi bi-check2-square me-1"></i>Select Room to Book Bed
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/user-view-hostels.js"></script>
