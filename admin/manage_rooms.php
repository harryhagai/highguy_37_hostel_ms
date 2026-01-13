<?php


require_once __DIR__ . '/../config/db_connection.php';

// Fetch hostels for dropdown
$hostels = $pdo->query("SELECT id, name FROM hostels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Create
if (isset($_POST['add_room'])) {
    $hostel_id = (int)$_POST['hostel_id'];
    $room_number = trim($_POST['room_number']);
    $capacity = (int)$_POST['capacity'];
    $available = (int)$_POST['available'];
    $room_type = trim($_POST['room_type']);
    $price = $_POST['price'];
    $errors = [];

    if (!$hostel_id) $errors[] = "Hostel is required.";
    if (!$room_number) $errors[] = "Room number is required.";
    if ($capacity <= 0) $errors[] = "Capacity must be positive.";
    if ($available < 0) $errors[] = "Available must be zero or more.";
    if (!$room_type) $errors[] = "Room type is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a positive number.";

    // Check if room number exists in the same hostel
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ?");
    $stmt->execute([$hostel_id, $room_number]);
    if ($stmt->fetch()) $errors[] = "Room number already exists in this hostel.";

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO rooms (hostel_id, room_number, capacity, available, room_type, price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hostel_id, $room_number, $capacity, $available, $room_type, $price]);
        $success = "Room added successfully!";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard_layout.php?page=manage_rooms");
    exit;
}

// Handle Edit (fetch room)
$edit_room = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    $edit_room = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Update
if (isset($_POST['update_room'])) {
    $id = (int)$_POST['id'];
    $hostel_id = (int)$_POST['hostel_id'];
    $room_number = trim($_POST['room_number']);
    $capacity = (int)$_POST['capacity'];
    $available = (int)$_POST['available'];
    $room_type = trim($_POST['room_type']);
    $price = $_POST['price'];
    $errors = [];

    if (!$hostel_id) $errors[] = "Hostel is required.";
    if (!$room_number) $errors[] = "Room number is required.";
    if ($capacity <= 0) $errors[] = "Capacity must be positive.";
    if ($available < 0) $errors[] = "Available must be zero or more.";
    if (!$room_type) $errors[] = "Room type is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a positive number.";

    // Check for room number conflicts in the same hostel
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE hostel_id = ? AND room_number = ? AND id != ?");
    $stmt->execute([$hostel_id, $room_number, $id]);
    if ($stmt->fetch()) $errors[] = "Room number already exists in this hostel.";

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE rooms SET hostel_id=?, room_number=?, capacity=?, available=?, room_type=?, price=? WHERE id=?");
        $stmt->execute([$hostel_id, $room_number, $capacity, $available, $room_type, $price, $id]);
        $success = "Room updated successfully!";
        $edit_room = null;
    }
}

// Fetch all rooms with hostel name
$rooms = $pdo->query("SELECT r.*, h.name AS hostel_name FROM rooms r JOIN hostels h ON r.hostel_id = h.id ORDER BY r.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid px-0">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><?= $edit_room ? 'Edit Room' : 'Add Room' ?></h4>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
            <?php elseif (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?php if ($edit_room): ?>
                    <input type="hidden" name="id" value="<?= $edit_room['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Hostel</label>
                        <select name="hostel_id" class="form-select" required>
                            <option value="">Select Hostel</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?= $hostel['id'] ?>" <?= (isset($edit_room['hostel_id']) && $edit_room['hostel_id'] == $hostel['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($hostel['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars($edit_room['room_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" class="form-control" min="1" required value="<?= htmlspecialchars($edit_room['capacity'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Available</label>
                        <input type="number" name="available" class="form-control" min="0" required value="<?= htmlspecialchars($edit_room['available'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Room Type</label>
                        <input type="text" name="room_type" class="form-control" required value="<?= htmlspecialchars($edit_room['room_type'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Price</label>
                        <input type="number" step="0.01" name="price" class="form-control" min="0" required value="<?= htmlspecialchars($edit_room['price'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <?php if ($edit_room): ?>
                        <button class="btn btn-success" name="update_room" type="submit"><i class="bi bi-check-circle"></i> Update</button>
                        <a href="admin_dashboard_layout.php?page=manage_rooms" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button class="btn btn-primary" name="add_room" type="submit"><i class="bi bi-plus-circle"></i> Add</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h4 class="mb-0">All Rooms</h4>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hostel</th>
                        <th>Room Number</th>
                        <th>Capacity</th>
                        <th>Available</th>
                        <th>Room Type</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($rooms as $room): ?>
                    <tr>
                        <td><?= $room['id'] ?></td>
                        <td><?= htmlspecialchars($room['hostel_name']) ?></td>
                        <td><?= htmlspecialchars($room['room_number']) ?></td>
                        <td><?= $room['capacity'] ?></td>
                        <td><?= $room['available'] ?></td>
                        <td><?= htmlspecialchars($room['room_type']) ?></td>
                        <td><?= number_format($room['price'], 2) ?></td>
                        <td>
                            <a href="admin_dashboard_layout.php?page=manage_rooms&edit=<?= $room['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="admin_dashboard_layout.php?page=manage_rooms&delete=<?= $room['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this room?')"><i class="bi bi-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">