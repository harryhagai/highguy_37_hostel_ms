<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';

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
    <style>
        :root {
            --aqua: #1ccad8;
            --aqua-dark: #11998e;
            --accent: #f6c23e;
            --white: #fff;
            --dark: #233142;
        }

        body {
            background: #f8f9fc;
        }

        .hostel-card {
            background: var(--white);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(28, 202, 216, 0.10);
            border: none;
            margin-bottom: 2rem;
            transition: box-shadow 0.2s, transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .hostel-card:hover {
            box-shadow: 0 8px 32px rgba(28, 202, 216, 0.18);
            transform: translateY(-4px) scale(1.01);
        }

        .hostel-image-wrapper {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
        }

        .hostel-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            transition: transform 0.3s;
        }

        .hostel-card:hover .hostel-image {
            transform: scale(1.04);
        }

        .no-rooms-badge {
            position: absolute;
            top: 14px;
            right: 16px;
            font-size: 0.92rem;
            padding: 0.5em 1em;
            border-radius: 12px;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(28, 202, 216, 0.13);
        }

        .hostel-card-body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1.2rem 1.2rem 1.2rem;
        }

        .hostel-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--aqua-dark);
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hostel-location {
            font-size: 1rem;
            color: var(--aqua);
            font-weight: 500;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hostel-location a {
            color: var(--aqua-dark);
            text-decoration: underline;
            cursor: pointer;
        }

        .hostel-desc {
            color: #555;
            font-size: 0.98rem;
            margin-bottom: 1.2rem;
            min-height: 48px;
        }

        .hostel-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.2rem;
        }

        .stat-icon {
            font-size: 1.4rem;
            color: var(--aqua-dark);
            margin-right: 6px;
        }

        .stat-label {
            font-weight: 500;
            color: #555;
        }

        .stat-value {
            font-weight: 700;
            color: var(--dark);
        }

        .hostel-actions {
            margin-top: auto;
            display: flex;
            gap: 0.7rem;
        }

        .btn-view {
            background: var(--aqua-dark);
            color: #fff;
            border: none;
            font-weight: 600;
            transition: background 0.18s;
            box-shadow: 0 2px 8px rgba(28, 202, 216, 0.09);
        }

        .btn-view:hover {
            background: var(--aqua);
            color: #fff;
        }

        .btn-book {
            background: var(--accent);
            color: var(--dark);
            border: none;
            font-weight: 600;
            transition: background 0.18s;
            box-shadow: 0 2px 8px rgba(246, 194, 62, 0.09);
        }

        .btn-book:hover {
            background: #ffe082;
            color: var(--dark);
        }

        #hostelModal .modal-content,
        #mapModal .modal-content {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(28, 202, 216, 0.15);
        }

        #hostelModal img {
            background: #f8f9fc;
        }

        @media (max-width: 991.98px) {
            .hostel-image-wrapper {
                height: 170px;
            }

            #hostelModal img {
                min-height: 180px;
            }

            #mapModal .modal-body {
                height: 250px !important;
            }
        }

        @media (max-width: 767.98px) {
            .hostel-image-wrapper {
                height: 140px;
            }

            #hostelModal img {
                min-height: 120px;
            }

            #mapModal .modal-body {
                height: 180px !important;
            }
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <h2 class="mb-4" style="color: var(--aqua-dark); font-weight: 700;">
            <i class="bi bi-building"></i> Book a Room
        </h2>
        <div class="row g-4">
            <?php foreach ($hostels as $hostel): ?>
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
                                                        "free_rooms" => $hostel["free_rooms"]
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
                            <img id="modalHostelImage" src="" alt="Hostel Image" class="img-fluid w-100 h-100" style="object-fit:cover; border-top-left-radius: .5rem; border-bottom-left-radius: .5rem; min-height:340px;">
                        </div>
                        <div class="col-md-6 p-4 d-flex flex-column">
                            <h4 id="modalHostelName" class="mb-2" style="color:var(--aqua-dark);font-weight:700;"></h4>
                            <!-- Location as link to open map modal -->
                            <div id="modalHostelLocation" class="mb-2 text-muted" style="font-size:1.1rem;"></div>
                            <div id="modalHostelDesc" class="mb-3" style="font-size:1rem; color:#555;"></div>
                            <!-- Google Map Embed (small preview) -->
                            <div id="modalHostelMap" style="height:120px; border-radius:8px; overflow:hidden; margin-bottom:1rem;"></div>
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
                <div class="modal-body p-0" style="height:400px;">
                    <iframe id="mapFrame" width="100%" height="100%" style="border:0;" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle location link clicks (both in card and modal)
            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('location-link')) {
                    e.preventDefault();
                    var location = e.target.getAttribute('data-location');
                    var mapFrame = document.getElementById('mapFrame');
                    mapFrame.src = "https://www.google.com/maps?q=" + encodeURIComponent(location) + "&output=embed";
                    var mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
                    mapModal.show();
                }
            });

            // Populate hostel details modal
            var hostelModal = document.getElementById('hostelModal');
            hostelModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var hostel = JSON.parse(button.getAttribute('data-hostel'));
                document.getElementById('modalHostelImage').src = hostel.image;
                document.getElementById('modalHostelName').textContent = hostel.name;
                // Location as link to open map modal
                document.getElementById('modalHostelLocation').innerHTML =
                    '<i class="bi bi-geo-alt-fill"></i> ' +
                    '<a href="#" class="location-link" data-location="' +
                    hostel.location.replace(/"/g, '&quot;') + '">' +
                    hostel.location +
                    '</a>';
                document.getElementById('modalHostelDesc').textContent = hostel.description;
                document.getElementById('modalTotalRooms').textContent = hostel.total_rooms;
                document.getElementById('modalFreeRooms').textContent = hostel.free_rooms;
                // Google Maps embed (small preview)
                document.getElementById('modalHostelMap').innerHTML =
                    '<iframe width="100%" height="100%" style="border:0;" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" ' +
                    'src="https://www.google.com/maps?q=' +
                    encodeURIComponent(hostel.location) +
                    '&output=embed"></iframe>';
            });
        });
    </script>
</body>

</html>