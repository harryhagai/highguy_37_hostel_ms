<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/my_room_controller.php';
$message = $state['message'];
$room = $state['room'];
$canBook = (bool)($state['can_book'] ?? true);
$bookingLock = is_array($state['booking_lock'] ?? null) ? $state['booking_lock'] : ['blocked' => false, 'message' => ''];
$residents = $state['residents'];
$stats = $state['stats'];
?>
<div class="container-fluid px-0 user-my-room-page">
    <div class="dashboard-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><i class="bi bi-house-heart me-2"></i>My Room</h4>
                <p class="text-muted mb-0">See your room details and students sharing the same room.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($canBook): ?>
                    <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle me-1"></i>Book Bed
                    </a>
                <?php else: ?>
                    <a href="user_dashboard_layout.php?page=my_bed" data-spa-page="my_bed" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-house-check me-1"></i>My Bed
                    </a>
                <?php endif; ?>
                <a href="user_dashboard_layout.php?page=my_bookings" data-spa-page="my_bookings" data-no-spinner="true" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-journal-check me-1"></i>My Bookings
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($bookingLock['blocked'])): ?>
        <div class="alert alert-warning mb-3">
            <i class="bi bi-lock me-1"></i><?= htmlspecialchars((string)($bookingLock['message'] ?? 'Booking is currently locked for your account.')) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$message['type']) ?> mb-3">
            <?= htmlspecialchars((string)$message['text']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$room): ?>
        <div class="dashboard-card my-room-empty-state text-center">
            <div class="mb-2"><i class="bi bi-door-closed my-room-empty-icon"></i></div>
            <h5 class="mb-2">No room assignment yet</h5>
            <p class="text-muted mb-3">Once your bed booking is active, your room and roommate details will appear here.</p>
            <?php if ($canBook): ?>
                <a href="user_dashboard_layout.php?page=book_bed" data-spa-page="book_bed" data-no-spinner="true" class="btn btn-outline-primary">
                    <i class="bi bi-search me-1"></i>Find Bed
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="users-quick-stats mb-3">
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Hostel</p>
                <h6 class="mb-0"><?= htmlspecialchars((string)$room['hostel_name']) ?></h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Room</p>
                <h6 class="mb-0">Room <?= htmlspecialchars((string)$room['room_number']) ?></h6>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Occupants</p>
                <h5 class="mb-0"><?= (int)$stats['occupants'] ?></h5>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Roommates</p>
                <h5 class="mb-0"><?= (int)$stats['roommates'] ?></h5>
            </article>
        </div>

        <div class="dashboard-card mb-3">
            <div class="row g-3">
                <div class="col-lg-8">
                    <h5 class="mb-1"><?= htmlspecialchars((string)$room['hostel_name']) ?> - Room <?= htmlspecialchars((string)$room['room_number']) ?></h5>
                    <p class="text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)$room['hostel_location']) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-light"><?= htmlspecialchars((string)$room['hostel_gender_label']) ?></span>
                        <span class="badge text-bg-secondary">Status: <?= htmlspecialchars((string)$room['status_label']) ?></span>
                        <?php if (!empty($room['room_type'])): ?>
                            <span class="badge text-bg-info">Type: <?= htmlspecialchars((string)$room['room_type']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($room['bed_number'])): ?>
                            <span class="badge text-bg-success">Your Bed: <?= htmlspecialchars((string)$room['bed_number']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="my-room-meta-box">
                        <p class="mb-1"><strong>Price:</strong> TSh <?= number_format((float)$room['price'], 2) ?></p>
                        <p class="mb-1"><strong>Booked:</strong> <?= htmlspecialchars((string)$room['booking_date_display']) ?></p>
                        <p class="mb-0"><strong>Stay:</strong> <?= htmlspecialchars((string)$room['stay_period']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <h5 class="mb-3"><i class="bi bi-people me-2"></i>Students In This Room</h5>
            <?php if (empty($residents)): ?>
                <div class="alert alert-light border mb-0">No student records were found for this room.</div>
            <?php else: ?>
                <div class="table-responsive my-room-table-wrap">
                    <table class="table table-sm align-middle my-room-table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Bed Number</th>
                                <th>Stay Period</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($residents as $resident): ?>
                                <tr class="<?= !empty($resident['is_me']) ? 'my-room-self-row my-room-self-sticky' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span><?= htmlspecialchars((string)$resident['username']) ?></span>
                                            <?php if (!empty($resident['is_me'])): ?>
                                                <span class="badge text-bg-primary">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= $resident['email'] !== '' ? htmlspecialchars((string)$resident['email']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $resident['phone'] !== '' ? htmlspecialchars((string)$resident['phone']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $resident['bed_number'] !== '' ? htmlspecialchars((string)$resident['bed_number']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= htmlspecialchars((string)$resident['stay_period']) ?></td>
                                    <td><span class="badge text-bg-light"><?= htmlspecialchars((string)$resident['status_label']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
