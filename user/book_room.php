<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/book_room_controller.php';
$errors = $state['errors'];
$message = $state['message'];
$hostel = $state['hostel'];
$rooms = $state['rooms'];
$existingBooking = $state['existing_booking'];
$bookingMode = $state['booking_mode'];
$selectedStartDate = $state['selected_start_date'];
$selectedEndDate = $state['selected_end_date'];
?>
<div class="container-fluid px-0 user-book-room-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?= implode('<br>', array_map(static fn($err) => htmlspecialchars((string)$err), $errors)) ?>
        </div>
        <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle me-1"></i>Back to Hostels
        </a>
    <?php else: ?>
        <div class="dashboard-card mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="mb-1"><i class="bi bi-building me-2"></i><?= htmlspecialchars((string)$hostel['name']) ?></h4>
                    <p class="text-muted mb-1"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)$hostel['location']) ?></p>
                    <span class="badge text-bg-light"><?= htmlspecialchars((string)$hostel['gender_label']) ?></span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="user_dashboard_layout.php?page=view_hostels" data-spa-page="view_hostels" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Hostels
                    </a>
                    <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-journal-check me-1"></i>My Bookings
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string)$message['text']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($existingBooking): ?>
            <div class="alert alert-warning">
                <i class="bi bi-lock me-1"></i>You already have an active booking request. View it in My Bookings.
            </div>
        <?php endif; ?>

        <?php if (empty($rooms)): ?>
            <div class="alert alert-info">No rooms found for this hostel.</div>
        <?php else: ?>
            <?php if ($bookingMode === 'bed'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>Booking flow: Choose room, then select an available bed inside that room.
                </div>
            <?php endif; ?>

            <div class="student-room-grid">
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $spotsLeft = max(0, (int)($room['spots_left'] ?? 0));
                    $capacity = max(0, (int)($room['capacity'] ?? 0));
                    $occupied = max(0, (int)($room['current_occupancy'] ?? 0));
                    $isFull = (bool)($room['is_full'] ?? false);
                    $availableBeds = is_array($room['available_beds'] ?? null) ? $room['available_beds'] : [];
                    $roomImageUrl = (string)($room['room_image_url'] ?? '../assets/images/logo.png');
                    ?>
                    <article class="student-room-card">
                        <div class="student-room-media mb-3">
                            <img src="<?= htmlspecialchars($roomImageUrl) ?>" alt="Room image" class="student-room-thumb">
                        </div>
                        <div class="student-room-head mb-2">
                            <h5 class="mb-1">Room <?= htmlspecialchars((string)$room['room_number']) ?></h5>
                            <span class="badge <?= $isFull ? 'text-bg-danger' : 'text-bg-success' ?>">
                                <?= $isFull ? 'Full' : ($spotsLeft . ' spots left') ?>
                            </span>
                        </div>

                        <p class="mb-1 text-muted">Type: <?= htmlspecialchars((string)($room['room_type'] ?: 'Standard')) ?></p>
                        <p class="mb-1">Capacity: <b><?= $capacity ?></b></p>
                        <p class="mb-1">Occupied: <b><?= $occupied ?></b></p>
                        <p class="mb-2 room-price">TZS <?= number_format((float)$room['price'], 2) ?></p>

                        <?php if ($bookingMode === 'bed'): ?>
                            <div class="mb-3 small text-muted">
                                <?php if (!empty($availableBeds)): ?>
                                    Beds: <?= htmlspecialchars(implode(', ', array_map(static fn($bed) => (string)($bed['bed_number'] ?? ''), $availableBeds))) ?>
                                <?php else: ?>
                                    No available beds for selected dates.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$existingBooking && !$isFull): ?>
                            <button
                                type="button"
                                class="btn btn-outline-primary w-100 book-room-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#bookRoomModal"
                                data-room-id="<?= (int)$room['id'] ?>"
                                data-room-number="<?= htmlspecialchars((string)$room['room_number']) ?>"
                                data-room-price="<?= number_format((float)$room['price'], 2) ?>"
                                data-booking-mode="<?= htmlspecialchars((string)$bookingMode) ?>"
                                data-available-beds='<?= htmlspecialchars(json_encode($availableBeds), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="bi bi-calendar-plus me-1"></i>
                                <?= $bookingMode === 'bed' ? 'Select Bed & Book' : 'Book This Room' ?>
                            </button>
                        <?php elseif ($existingBooking): ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="bi bi-lock me-1"></i>Already Booked
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="bi bi-x-circle me-1"></i><?= $bookingMode === 'bed' ? 'No Bed Available' : 'Room Full' ?>
                            </button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="modal fade" id="bookRoomModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" class="modal-content" id="bookingForm">
                    <input type="hidden" name="action" value="book_room">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-calendar-plus me-1"></i>Confirm Booking</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="room_id" id="modalRoomId">
                        <div class="mb-2">
                            <label class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="modalRoomNumber" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Price</label>
                            <input type="text" class="form-control" id="modalRoomPrice" readonly>
                        </div>
                        <div class="mb-2" id="modalBedSelectWrap">
                            <label class="form-label">Available Beds</label>
                            <select class="form-select" name="bed_id" id="modalBedId">
                                <option value="">Select bed</option>
                            </select>
                            <small class="text-muted">Choose the bed you want to book in this room.</small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" id="phone" placeholder="Enter phone number">
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars((string)$selectedStartDate) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars((string)$selectedEndDate) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-outline-primary">Confirm Booking</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="../assets/js/user-book-room.js"></script>
