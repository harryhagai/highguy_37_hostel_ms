<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

// Fetch hostels and their room stats
$stmt = $pdo->query("
    SELECT h.*, 
        (SELECT COUNT(*) FROM rooms WHERE hostel_id = h.id) AS total_rooms,
        (SELECT COUNT(*) FROM rooms WHERE hostel_id = h.id AND available > 0) AS free_rooms
    FROM hostels h
    ORDER BY h.created_at DESC
");
$hostels = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Book a Room</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <link rel="stylesheet" href="../assets/css/user-view-hostels.css">
</head>

<body>
    <div class="container py-4">
        <h2 class="mb-4 view-hostels-title">
            <i class="bi bi-building"></i> Book a Room
        </h2>
        <div class="row g-4">
            <?php foreach ($hostels as $hostel): ?>
                <?php
                $genderValue = strtolower(trim((string)($hostel['gender'] ?? 'all')));
                if (!in_array($genderValue, ['male', 'female', 'all'], true)) {
                    $genderValue = 'all';
                }
                $genderLabel = $genderValue === 'male'
                    ? 'Male Only'
                    : ($genderValue === 'female' ? 'Female Only' : 'All Genders');
                ?>
                <div class="col-lg-6 col-md-6 col-12 d-flex">
                    <div class="hostel-card w-100 position-relative">
                        <div class="hostel-image-wrapper">
                            <img src="../<?= htmlspecialchars($hostel['hostel_image']) ?>" alt="Hostel Image" class="hostel-image">
                            <?php if ($hostel['free_rooms'] == 0): ?>
                                <span class="badge bg-danger no-rooms-badge">No Free Rooms</span>
                            <?php endif; ?>
                        </div>
                        <div class="hostel-card-body">
                            <div class="hostel-title">
                                <i class="bi bi-house-door-fill"></i>
                                <?= htmlspecialchars($hostel['name']) ?>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle ms-2">
                                    <?= htmlspecialchars($genderLabel) ?>
                                </span>
                            </div>
                            <div class="hostel-location">
                                <i class="bi bi-geo-alt-fill"></i>
                                <!-- Location as trigger for map modal -->
                                <a href="#" class="location-link" data-location="<?= htmlspecialchars($hostel['location']) ?>">
                                    <?= htmlspecialchars($hostel['location']) ?>
                                </a>
                            </div>
                            <div class="hostel-desc">
                                <?= htmlspecialchars(mb_strimwidth($hostel['description'], 0, 100, '...')) ?>
                            </div>
                            <div class="hostel-stats">
                                <span>
                                    <i class="bi bi-door-closed stat-icon"></i>
                                    <span class="stat-label">Total Rooms: </span>
                                    <span class="stat-value"><?= $hostel['total_rooms'] ?></span>
                                </span>
                                <span>
                                    <i class="bi bi-check-circle stat-icon"></i>
                                    <span class="stat-label">Free: </span>
                                    <span class="stat-value"><?= $hostel['free_rooms'] ?></span>
                                </span>
                            </div>
                            <div class="hostel-actions">
                                <!-- View Details Button (triggers modal) -->
                                <button
                                    class="btn btn-view"
                                    data-bs-toggle="modal"
                                    data-bs-target="#hostelModal"
                                    data-hostel='<?= htmlspecialchars(json_encode([
                                                        "name" => $hostel["name"],
                                                        "location" => $hostel["location"],
                                                        "description" => $hostel["description"],
                                                        "image" => "../" . $hostel["hostel_image"],
                                                        "total_rooms" => $hostel["total_rooms"],
                                                        "free_rooms" => $hostel["free_rooms"],
                                                        "gender" => $genderValue,
                                                        "gender_label" => $genderLabel
                                                    ])) ?>'>
                                    <i class="bi bi-eye"></i> View Details
                                </button>
                                <!-- Book Room Button -->
                                <?php if ($hostel['free_rooms'] > 0): ?>
                                    <a href="user_dashboard_layout.php?page=book_room&hostel_id=<?= intval($hostel['id']) ?>" class="btn btn-primary">View Rooms & Book</a>
                                    
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="bi bi-x-circle"></i> No Free Rooms
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($hostels)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No hostels available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hostel Details Modal -->
    <div class="modal fade" id="hostelModal" tabindex="-1" aria-labelledby="hostelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <img id="modalHostelImage" src="" alt="Hostel Image" class="img-fluid w-100 h-100 modal-hostel-image">
                        </div>
                        <div class="col-md-6 p-4 d-flex flex-column">
                            <h4 id="modalHostelName" class="mb-2 modal-hostel-name"></h4>
                            <!-- Location as link to open map modal -->
                            <div id="modalHostelLocation" class="mb-2 text-muted modal-hostel-location"></div>
                            <div class="mb-2"><b>Gender:</b> <span id="modalHostelGender"></span></div>
                            <div id="modalHostelDesc" class="mb-3 modal-hostel-desc"></div>
                            <!-- Google Map Embed (small preview) -->
                            <div id="modalHostelMap" class="modal-hostel-map"></div>
                            <div class="mb-3">
                                <span class="me-3"><i class="bi bi-door-closed"></i> <b>Total Rooms:</b> <span id="modalTotalRooms"></span></span>
                                <span><i class="bi bi-check-circle"></i> <b>Free:</b> <span id="modalFreeRooms"></span></span>
                            </div>
                            <button class="btn btn-secondary ms-auto mt-auto" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Modal -->
    <div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mapModalLabel"><i class="bi bi-geo-alt-fill"></i> Hostel Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 map-modal-body">
                    <iframe id="mapFrame" width="100%" height="100%" class="map-frame" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="../assets/js/user-view-hostels.js"></script>
</body>

</html>



