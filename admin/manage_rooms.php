<?php
require_once __DIR__ . '/../config/db_connection.php';

function mrColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;
$hasCapacityCol = mrColumnExists($pdo, 'rooms', 'capacity');
$hasAvailableCol = mrColumnExists($pdo, 'rooms', 'available');

$hostels = $pdo->query('SELECT id, name FROM hostels ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_room') {
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $available = (int)($_POST['available'] ?? 0);
        $roomType = trim($_POST['room_type'] ?? '');
        $price = $_POST['price'] ?? '';

        if ($hostelId <= 0) $errors[] = 'Hostel is required.';
        if ($roomNumber === '') $errors[] = 'Room number is required.';
        if ($hasCapacityCol && $capacity <= 0) $errors[] = 'Capacity must be positive.';
        if ($hasAvailableCol && $available < 0) $errors[] = 'Available must be zero or more.';
        if ($roomType === '') $errors[] = 'Room type is required.';
        if (!is_numeric($price) || (float)$price < 0) $errors[] = 'Price must be a positive number.';

        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ?');
        $stmt->execute([$hostelId, $roomNumber]);
        if ($stmt->fetch()) {
            $errors[] = 'Room number already exists in this hostel.';
        }

        if (empty($errors)) {
            $columns = ['hostel_id', 'room_number', 'room_type', 'price'];
            $values = [$hostelId, $roomNumber, $roomType, $price];
            if ($hasCapacityCol) {
                $columns[] = 'capacity';
                $values[] = $capacity;
            }
            if ($hasAvailableCol) {
                $columns[] = 'available';
                $values[] = $available;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO rooms (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $success = 'Room added successfully.';
        } else {
            $openModal = 'addRoomModal';
        }
    }

    if ($action === 'update_room') {
        $id = (int)($_POST['id'] ?? 0);
        $hostelId = (int)($_POST['hostel_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $available = (int)($_POST['available'] ?? 0);
        $roomType = trim($_POST['room_type'] ?? '');
        $price = $_POST['price'] ?? '';

        if ($hostelId <= 0) $errors[] = 'Hostel is required.';
        if ($roomNumber === '') $errors[] = 'Room number is required.';
        if ($hasCapacityCol && $capacity <= 0) $errors[] = 'Capacity must be positive.';
        if ($hasAvailableCol && $available < 0) $errors[] = 'Available must be zero or more.';
        if ($roomType === '') $errors[] = 'Room type is required.';
        if (!is_numeric($price) || (float)$price < 0) $errors[] = 'Price must be a positive number.';

        $stmt = $pdo->prepare('SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ? AND id != ?');
        $stmt->execute([$hostelId, $roomNumber, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Room number already exists in this hostel.';
        }

        if (empty($errors)) {
            $set = ['hostel_id = ?', 'room_number = ?', 'room_type = ?', 'price = ?'];
            $values = [$hostelId, $roomNumber, $roomType, $price];
            if ($hasCapacityCol) {
                $set[] = 'capacity = ?';
                $values[] = $capacity;
            }
            if ($hasAvailableCol) {
                $set[] = 'available = ?';
                $values[] = $available;
            }
            $values[] = $id;

            $sql = 'UPDATE rooms SET ' . implode(', ', $set) . ' WHERE id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $success = 'Room updated successfully.';
        } else {
            $openModal = 'editRoomModal';
            $editFormData = [
                'id' => $id,
                'hostel_id' => $hostelId,
                'room_number' => $roomNumber,
                'capacity' => $capacity,
                'available' => $available,
                'room_type' => $roomType,
                'price' => $price
            ];
        }
    }

    if ($action === 'delete_room') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Room deleted successfully.';
        }
    }
}

$rooms = $pdo->query('SELECT r.*, h.name AS hostel_name FROM rooms r JOIN hostels h ON r.hostel_id = h.id ORDER BY r.id DESC')->fetchAll(PDO::FETCH_ASSOC);
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
            <h4 class="mb-0">Manage Rooms</h4>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                <i class="bi bi-plus-circle"></i> Add Room
            </button>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hostel</th>
                <th>Room Number</th>
                        <?php if ($hasCapacityCol): ?><th>Capacity</th><?php endif; ?>
                        <?php if ($hasAvailableCol): ?><th>Available</th><?php endif; ?>
                        <th>Room Type</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $room): ?>
                    <?php $json = htmlspecialchars(json_encode($room), ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td><?= (int)$room['id'] ?></td>
                        <td><?= htmlspecialchars($room['hostel_name']) ?></td>
                        <td><?= htmlspecialchars($room['room_number']) ?></td>
                        <?php if ($hasCapacityCol): ?><td><?= (int)$room['capacity'] ?></td><?php endif; ?>
                        <?php if ($hasAvailableCol): ?><td><?= (int)$room['available'] ?></td><?php endif; ?>
                        <td><?= htmlspecialchars($room['room_type']) ?></td>
                        <td><?= number_format((float)$room['price'], 2) ?></td>
                        <td class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-info text-white view-room-btn" data-room="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewRoomModal">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="button" class="btn btn-sm btn-warning edit-room-btn" data-room="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#editRoomModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="post" data-confirm="Delete this room?" class="d-inline">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="id" value="<?= (int)$room['id'] ?>">
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

<div class="modal fade" id="addRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_room">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-door-open"></i> Add Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Hostel</label>
                            <select name="hostel_id" class="form-select" required>
                                <option value="">Select Hostel</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?= (int)$hostel['id'] ?>"><?= htmlspecialchars($hostel['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Room Type</label>
                            <input type="text" name="room_type" class="form-control" required>
                        </div>
                        <?php if ($hasCapacityCol): ?>
                            <div class="col-md-4">
                                <label class="form-label">Capacity</label>
                                <input type="number" name="capacity" class="form-control" min="1" required>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasAvailableCol): ?>
                            <div class="col-md-4">
                                <label class="form-label">Available</label>
                                <input type="number" name="available" class="form-control" min="0" required>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" class="form-control" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="update_room">
                <input type="hidden" name="id" id="editRoomId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Hostel</label>
                            <select name="hostel_id" id="editRoomHostel" class="form-select" required>
                                <option value="">Select Hostel</option>
                                <?php foreach ($hostels as $hostel): ?>
                                    <option value="<?= (int)$hostel['id'] ?>"><?= htmlspecialchars($hostel['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Room Number</label>
                            <input type="text" name="room_number" id="editRoomNumber" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Room Type</label>
                            <input type="text" name="room_type" id="editRoomType" class="form-control" required>
                        </div>
                        <?php if ($hasCapacityCol): ?>
                            <div class="col-md-4">
                                <label class="form-label">Capacity</label>
                                <input type="number" name="capacity" id="editRoomCapacity" class="form-control" min="1" required>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasAvailableCol): ?>
                            <div class="col-md-4">
                                <label class="form-label">Available</label>
                                <input type="number" name="available" id="editRoomAvailable" class="form-control" min="0" required>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" id="editRoomPrice" class="form-control" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-door-open"></i> Room Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>ID:</strong> <span id="viewRoomId">-</span></p>
                <p class="mb-2"><strong>Hostel:</strong> <span id="viewRoomHostel">-</span></p>
                <p class="mb-2"><strong>Room Number:</strong> <span id="viewRoomNumber">-</span></p>
                <p class="mb-2"><strong>Room Type:</strong> <span id="viewRoomType">-</span></p>
                <?php if ($hasCapacityCol): ?><p class="mb-2"><strong>Capacity:</strong> <span id="viewRoomCapacity">-</span></p><?php endif; ?>
                <?php if ($hasAvailableCol): ?><p class="mb-2"><strong>Available:</strong> <span id="viewRoomAvailable">-</span></p><?php endif; ?>
                <p class="mb-0"><strong>Price:</strong> <span id="viewRoomPrice">-</span></p>
            </div>
        </div>
    </div>
</div>

<div
    id="manageRoomsConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>">
</div>
<script src="../assets/js/admin-manage-rooms.js"></script>
