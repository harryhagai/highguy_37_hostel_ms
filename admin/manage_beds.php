<?php
require_once __DIR__ . '/../config/db_connection.php';

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

try {
    $rooms = $pdo->query("
        SELECT r.id, r.room_number, h.name AS hostel_name
        FROM rooms r
        JOIN hostels h ON h.id = r.hostel_id
        ORDER BY h.name ASC, r.room_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rooms = [];
    $errors[] = "Unable to load rooms. Ensure tables `rooms` and `hostels` exist.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_bed') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'maintenance', 'inactive'], true)) {
            $status = 'active';
        }

        if ($roomId <= 0) $errors[] = 'Room is required.';
        if ($bedNumber === '') $errors[] = 'Bed number is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM beds WHERE room_id = ? AND bed_number = ?');
            $stmt->execute([$roomId, $bedNumber]);
            if ($stmt->fetch()) {
                $errors[] = 'Bed number already exists in this room.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO beds (room_id, bed_number, status) VALUES (?, ?, ?)');
            $stmt->execute([$roomId, $bedNumber, $status]);
            $success = 'Bed added successfully.';
        } else {
            $openModal = 'addBedModal';
        }
    }

    if ($action === 'update_bed') {
        $id = (int)($_POST['id'] ?? 0);
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedNumber = trim($_POST['bed_number'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'maintenance', 'inactive'], true)) {
            $status = 'active';
        }

        if ($roomId <= 0) $errors[] = 'Room is required.';
        if ($bedNumber === '') $errors[] = 'Bed number is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM beds WHERE room_id = ? AND bed_number = ? AND id != ?');
            $stmt->execute([$roomId, $bedNumber, $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Bed number already exists in this room.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE beds SET room_id = ?, bed_number = ?, status = ? WHERE id = ?');
            $stmt->execute([$roomId, $bedNumber, $status, $id]);
            $success = 'Bed updated successfully.';
        } else {
            $openModal = 'editBedModal';
            $editFormData = [
                'id' => $id,
                'room_id' => $roomId,
                'bed_number' => $bedNumber,
                'status' => $status
            ];
        }
    }

    if ($action === 'delete_bed') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM beds WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Bed deleted successfully.';
        }
    }
}

try {
    $beds = $pdo->query("
        SELECT b.id, b.room_id, b.bed_number, b.status, b.created_at, r.room_number, h.name AS hostel_name
        FROM beds b
        JOIN rooms r ON r.id = b.room_id
        JOIN hostels h ON h.id = r.hostel_id
        ORDER BY b.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $beds = [];
    if (empty($errors)) {
        $errors[] = "Unable to load beds. Ensure table `beds` exists (run updated SQL).";
    }
}
?>

<div class="container-fluid px-0">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Manage Beds</h4>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addBedModal">
                <i class="bi bi-plus-circle"></i> Add Bed
            </button>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hostel</th>
                        <th>Room</th>
                        <th>Bed Number</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($beds as $bed): ?>
                    <?php $json = htmlspecialchars(json_encode($bed), ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td><?= (int)$bed['id'] ?></td>
                        <td><?= htmlspecialchars($bed['hostel_name']) ?></td>
                        <td><?= htmlspecialchars($bed['room_number']) ?></td>
                        <td><?= htmlspecialchars($bed['bed_number']) ?></td>
                        <td>
                            <?php
                            $badge = 'secondary';
                            if ($bed['status'] === 'active') $badge = 'success';
                            if ($bed['status'] === 'maintenance') $badge = 'warning text-dark';
                            if ($bed['status'] === 'inactive') $badge = 'dark';
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($bed['status']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($bed['created_at']) ?></td>
                        <td class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-info text-white view-bed-btn" data-bed="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewBedModal">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="button" class="btn btn-sm btn-warning edit-bed-btn" data-bed="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#editBedModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="post" onsubmit="return confirm('Delete this bed?');" class="d-inline">
                                <input type="hidden" name="action" value="delete_bed">
                                <input type="hidden" name="id" value="<?= (int)$bed['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_bed">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-grid-3x3-gap"></i> Add Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <select name="room_id" class="form-select" required>
                                <option value="">Select Room</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= (int)$room['id'] ?>">
                                        <?= htmlspecialchars($room['hostel_name'] . ' - Room ' . $room['room_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bed Number</label>
                            <input type="text" name="bed_number" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="update_bed">
                <input type="hidden" name="id" id="editBedId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <select name="room_id" id="editBedRoom" class="form-select" required>
                                <option value="">Select Room</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= (int)$room['id'] ?>">
                                        <?= htmlspecialchars($room['hostel_name'] . ' - Room ' . $room['room_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bed Number</label>
                            <input type="text" name="bed_number" id="editBedNumber" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editBedStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Update Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-grid-3x3-gap"></i> Bed Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>ID:</strong> <span id="viewBedId">-</span></p>
                <p class="mb-2"><strong>Hostel:</strong> <span id="viewBedHostel">-</span></p>
                <p class="mb-2"><strong>Room:</strong> <span id="viewBedRoom">-</span></p>
                <p class="mb-2"><strong>Bed Number:</strong> <span id="viewBedNumber">-</span></p>
                <p class="mb-2"><strong>Status:</strong> <span id="viewBedStatus">-</span></p>
                <p class="mb-0"><strong>Created:</strong> <span id="viewBedCreated">-</span></p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function fillBedEdit(bed) {
        document.getElementById('editBedId').value = bed.id ?? '';
        document.getElementById('editBedRoom').value = bed.room_id ?? '';
        document.getElementById('editBedNumber').value = bed.bed_number ?? '';
        document.getElementById('editBedStatus').value = bed.status ?? 'active';
    }

    function fillBedView(bed) {
        document.getElementById('viewBedId').textContent = bed.id ?? '-';
        document.getElementById('viewBedHostel').textContent = bed.hostel_name ?? '-';
        document.getElementById('viewBedRoom').textContent = bed.room_number ?? '-';
        document.getElementById('viewBedNumber').textContent = bed.bed_number ?? '-';
        document.getElementById('viewBedStatus').textContent = bed.status ?? '-';
        document.getElementById('viewBedCreated').textContent = bed.created_at ?? '-';
    }

    document.querySelectorAll('.edit-bed-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillBedEdit(JSON.parse(this.dataset.bed));
        });
    });

    document.querySelectorAll('.view-bed-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillBedView(JSON.parse(this.dataset.bed));
        });
    });

    var openModal = <?= json_encode($openModal) ?>;
    var editFormData = <?= json_encode($editFormData) ?>;

    if (openModal === 'editBedModal' && editFormData) {
        fillBedEdit(editFormData);
    }

    if (openModal) {
        var target = document.getElementById(openModal);
        if (target && window.bootstrap) {
            new bootstrap.Modal(target).show();
        }
    }
})();
</script>
