<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/book_bed_controller.php';
$errors = $state['errors'];
$message = $state['message'];
$beds = $state['beds'];
$stats = $state['stats'];
$filters = $state['filters'];
?>
<div class="container-fluid px-0 user-book-bed-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
            <?= implode('<br>', array_map(static fn($err) => htmlspecialchars((string)$err), $errors)) ?>
        </div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Available Beds</p>
            <h5 class="mb-0"><?= (int)$stats['available_beds'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Hostels</p>
            <h5 class="mb-0"><?= (int)$stats['hostels'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Locations</p>
            <h5 class="mb-0"><?= (int)$stats['locations'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>Book Bed</h4>
                <p class="text-muted mb-0">Use filters to find hostel, room, and bed that matches your preference.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="user_dashboard_layout.php?page=my_room" data-spa-page="my_room" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-house-heart me-1"></i>My Room
                </a>
                <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-buildings me-1"></i>View Hostels
                </a>
            </div>
        </div>

        <form method="get" action="user_dashboard_layout.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="book_bed">
            <div class="col-xl-3 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Search</label>
                <input type="search" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$filters['search']) ?>" placeholder="Hostel, room, bed, location">
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Hostel</label>
                <select name="hostel_id" class="form-select form-select-sm">
                    <option value="">All Hostels</option>
                    <?php foreach ($filters['hostel_options'] as $hostelId => $hostelName): ?>
                        <option value="<?= (int)$hostelId ?>" <?= (int)$filters['hostel_id'] === (int)$hostelId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$hostelName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Location</label>
                <select name="location" class="form-select form-select-sm">
                    <option value="">All Locations</option>
                    <?php foreach ($filters['location_options'] as $location): ?>
                        <?php $normalizedLocation = strtolower((string)$location); ?>
                        <option value="<?= htmlspecialchars($normalizedLocation) ?>" <?= $filters['location'] === $normalizedLocation ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$location) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Room Type</label>
                <select name="room_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach ($filters['room_type_options'] as $roomType): ?>
                        <?php $normalizedType = strtolower((string)$roomType); ?>
                        <option value="<?= htmlspecialchars($normalizedType) ?>" <?= $filters['room_type'] === $normalizedType ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$roomType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-1 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Gender</label>
                <select name="gender" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($filters['gender_options'] as $genderValue => $genderLabel): ?>
                        <option value="<?= htmlspecialchars((string)$genderValue) ?>" <?= $filters['gender'] === (string)$genderValue ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$genderLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-1 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Min (TSh)</label>
                <input type="number" min="0" step="1000" name="price_min" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$filters['price_min']) ?>">
            </div>
            <div class="col-xl-1 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Max (TSh)</label>
                <input type="number" min="0" step="1000" name="price_max" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$filters['price_max']) ?>">
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Sort</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="hostel_asc" <?= $filters['sort'] === 'hostel_asc' ? 'selected' : '' ?>>Hostel A-Z</option>
                    <option value="room_asc" <?= $filters['sort'] === 'room_asc' ? 'selected' : '' ?>>Room Number</option>
                    <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Price Low-High</option>
                    <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Price High-Low</option>
                </select>
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$filters['start_date']) ?>">
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6">
                <label class="form-label form-label-sm mb-1">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$filters['end_date']) ?>">
            </div>
            <div class="col-xl-2 col-lg-4 col-sm-6 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
                <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars((string)$message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($beds)): ?>
        <div class="dashboard-card my-room-empty-state text-center">
            <div class="mb-2"><i class="bi bi-search my-room-empty-icon"></i></div>
            <h5 class="mb-2">No beds found</h5>
            <p class="text-muted mb-3">Try adjusting your filters or change date range to see more available beds.</p>
            <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-outline-primary">
                <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
            </a>
        </div>
    <?php else: ?>
        <div class="student-bed-grid">
            <?php foreach ($beds as $bed): ?>
                <?php
                $modalData = [
                    'bed_id' => (int)$bed['bed_id'],
                    'bed_number' => (string)$bed['bed_number'],
                    'room_number' => (string)$bed['room_number'],
                    'room_type' => (string)$bed['room_type'],
                    'price' => (float)$bed['price'],
                    'hostel_name' => (string)$bed['hostel_name'],
                    'hostel_location' => (string)$bed['hostel_location'],
                    'start_date' => (string)$filters['start_date'],
                    'end_date' => (string)$filters['end_date'],
                ];
                ?>
                <article class="student-bed-card">
                    <div class="student-bed-media">
                        <img src="<?= htmlspecialchars((string)$bed['room_image_url']) ?>" alt="Room image" class="student-room-thumb">
                    </div>
                    <div class="student-bed-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h5 class="mb-0"><?= htmlspecialchars((string)$bed['hostel_name']) ?></h5>
                            <span class="badge text-bg-light"><?= htmlspecialchars((string)$bed['hostel_gender_label']) ?></span>
                        </div>

                        <p class="text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)$bed['hostel_location']) ?></p>
                        <div class="small mb-2">
                            <div><strong>Room:</strong> <?= htmlspecialchars((string)$bed['room_number']) ?></div>
                            <?php if (!empty($bed['room_type'])): ?>
                                <div><strong>Type:</strong> <?= htmlspecialchars((string)$bed['room_type']) ?></div>
                            <?php endif; ?>
                            <div><strong>Bed:</strong> <?= htmlspecialchars((string)$bed['bed_number']) ?></div>
                        </div>

                        <p class="mb-3 room-price">TSh <?= number_format((float)$bed['price'], 2) ?></p>

                        <button
                            type="button"
                            class="btn btn-outline-success w-100 book-bed-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#bookBedModal"
                            data-bed='<?= htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="bi bi-check2-circle me-1"></i>Book Bed
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="bookBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="bookBedForm">
            <input type="hidden" name="action" value="book_bed">
            <input type="hidden" name="bed_id" id="bookBedId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-grid-3x3-gap me-1"></i>Confirm Bed Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Hostel</label>
                    <input type="text" class="form-control" id="bookBedHostel" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" id="bookBedLocation" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Room</label>
                    <input type="text" class="form-control" id="bookBedRoom" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Bed Number</label>
                    <input type="text" class="form-control" id="bookBedNumber" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Price</label>
                    <input type="text" class="form-control" id="bookBedPrice" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" placeholder="Enter phone number">
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="bookBedStartDate" value="<?= htmlspecialchars((string)$filters['start_date']) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="bookBedEndDate" value="<?= htmlspecialchars((string)$filters['end_date']) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-outline-success">Confirm Booking</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/user-book-bed.js"></script>
