<?php


require_once __DIR__ . '/../config/db_connection.php';

// Handle Create
if (isset($_POST['add_hostel'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $hostel_image = null;
    $errors = [];

    if (!$name) $errors[] = "Hostel name is required.";
    if (!$location) $errors[] = "Location is required.";

    // Handle image upload if provided
    if (!empty($_FILES['hostel_image']['name'])) {
        $target_dir = "../uploads/hostels/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . basename($_FILES["hostel_image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (!in_array($imageFileType, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed for image.";
        } else {
            if (move_uploaded_file($_FILES["hostel_image"]["tmp_name"], $target_file)) {
                $hostel_image = "uploads/hostels/" . basename($_FILES["hostel_image"]["name"]);
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    // Check if hostel name exists
    $stmt = $pdo->prepare("SELECT id FROM hostels WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) $errors[] = "Hostel name already exists.";

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO hostels (name, description, location, hostel_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $location, $hostel_image]);
        $success = "Hostel added successfully!";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optionally delete image file
    $stmt = $pdo->prepare("SELECT hostel_image FROM hostels WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && $row['hostel_image'] && file_exists("../" . $row['hostel_image'])) {
        unlink("../" . $row['hostel_image']);
    }
    $pdo->prepare("DELETE FROM hostels WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard_layout.php?page=manage_hostel");
    exit;
}

// Handle Edit (fetch hostel)
$edit_hostel = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
    $stmt->execute([$id]);
    $edit_hostel = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Update
if (isset($_POST['update_hostel'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $hostel_image = $edit_hostel['hostel_image'] ?? null;
    $errors = [];

    if (!$name) $errors[] = "Hostel name is required.";
    if (!$location) $errors[] = "Location is required.";

    // Handle image upload if provided
    if (!empty($_FILES['hostel_image']['name'])) {
        $target_dir = "../uploads/hostels/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . basename($_FILES["hostel_image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        if (!in_array($imageFileType, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed for image.";
        } else {
            if (move_uploaded_file($_FILES["hostel_image"]["tmp_name"], $target_file)) {
                // Delete old image if exists
                if ($hostel_image && file_exists("../" . $hostel_image)) {
                    unlink("../" . $hostel_image);
                }
                $hostel_image = "uploads/hostels/" . basename($_FILES["hostel_image"]["name"]);
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    // Check for name conflicts
    $stmt = $pdo->prepare("SELECT id FROM hostels WHERE name = ? AND id != ?");
    $stmt->execute([$name, $id]);
    if ($stmt->fetch()) $errors[] = "Hostel name already exists.";

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE hostels SET name=?, description=?, location=?, hostel_image=? WHERE id=?");
        $stmt->execute([$name, $description, $location, $hostel_image, $id]);
        $success = "Hostel updated successfully!";
        $edit_hostel = null;
    }
}

// Fetch all hostels
$hostels = $pdo->query("SELECT id, name, description, location, hostel_image, created_at FROM hostels ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid px-0">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><?= $edit_hostel ? 'Edit Hostel' : 'Add Hostel' ?></h4>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
            <?php elseif (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <?php if ($edit_hostel): ?>
                    <input type="hidden" name="id" value="<?= $edit_hostel['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Hostel Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit_hostel['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control"><?= htmlspecialchars($edit_hostel['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" required value="<?= htmlspecialchars($edit_hostel['location'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Image</label>
                        <input type="file" name="hostel_image" class="form-control">
                        <?php if (!empty($edit_hostel['hostel_image'])): ?>
                            <img src="../<?= htmlspecialchars($edit_hostel['hostel_image']) ?>" alt="Hostel Image" style="width:40px;height:40px;margin-top:5px;">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3">
                    <?php if ($edit_hostel): ?>
                        <button class="btn btn-success" name="update_hostel" type="submit"><i class="bi bi-check-circle"></i> Update</button>
                        <a href="admin_dashboard_layout.php?page=manage_hostel" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button class="btn btn-primary" name="add_hostel" type="submit"><i class="bi bi-plus-circle"></i> Add</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h4 class="mb-0">All Hostels</h4>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Image</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($hostels as $hostel): ?>
                    <tr>
                        <td><?= $hostel['id'] ?></td>
                        <td><?= htmlspecialchars($hostel['name']) ?></td>
                        <td><?= htmlspecialchars($hostel['description']) ?></td>
                        <td><?= htmlspecialchars($hostel['location']) ?></td>
                        <td>
                            <?php if ($hostel['hostel_image']): ?>
                                <img src="../<?= htmlspecialchars($hostel['hostel_image']) ?>" alt="Hostel Image" style="width:40px;height:40px;">
                            <?php else: ?>
                                <span class="text-muted">No image</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $hostel['created_at'] ?></td>
                        <td>
                            <a href="admin_dashboard_layout.php?page=manage_hostel&edit=<?= $hostel['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="admin_dashboard_layout.php?page=manage_hostel&delete=<?= $hostel['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this hostel?')"><i class="bi bi-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">