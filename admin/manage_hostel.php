<?php
require_once __DIR__ . '/../config/db_connection.php';

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

function uploadHostelImage(array $file, string $targetDir, ?string &$error = null): ?string
{
    if (empty($file['name'])) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        $error = 'Only JPG, JPEG, PNG, GIF, and WEBP are allowed.';
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $filename = $safeName . '_' . time() . '.' . $ext;
    $fullPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        $error = 'Failed to upload image.';
        return null;
    }

    return 'uploads/hostels/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hostel') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name === '') {
            $errors[] = 'Hostel name is required.';
        }
        if ($location === '') {
            $errors[] = 'Location is required.';
        }

        $stmt = $pdo->prepare('SELECT id FROM hostels WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $errors[] = 'Hostel name already exists.';
        }

        $hostelImage = null;
        if (!empty($_FILES['hostel_image']['name'])) {
            $uploadError = null;
            $hostelImage = uploadHostelImage($_FILES['hostel_image'], __DIR__ . '/../uploads/hostels', $uploadError);
            if ($uploadError) {
                $errors[] = $uploadError;
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO hostels (name, description, location, hostel_image) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $location, $hostelImage]);
            $success = 'Hostel added successfully.';
        } else {
            $openModal = 'addHostelModal';
        }
    }

    if ($action === 'update_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $existingImage = trim($_POST['existing_image'] ?? '');
        $hostelImage = $existingImage !== '' ? $existingImage : null;

        if ($name === '') {
            $errors[] = 'Hostel name is required.';
        }
        if ($location === '') {
            $errors[] = 'Location is required.';
        }

        $stmt = $pdo->prepare('SELECT id FROM hostels WHERE name = ? AND id != ?');
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Hostel name already exists.';
        }

        if (!empty($_FILES['hostel_image']['name'])) {
            $uploadError = null;
            $newImage = uploadHostelImage($_FILES['hostel_image'], __DIR__ . '/../uploads/hostels', $uploadError);
            if ($uploadError) {
                $errors[] = $uploadError;
            } elseif ($newImage !== null) {
                if ($hostelImage && file_exists(__DIR__ . '/../' . $hostelImage)) {
                    unlink(__DIR__ . '/../' . $hostelImage);
                }
                $hostelImage = $newImage;
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE hostels SET name = ?, description = ?, location = ?, hostel_image = ? WHERE id = ?');
            $stmt->execute([$name, $description, $location, $hostelImage, $id]);
            $success = 'Hostel updated successfully.';
        } else {
            $openModal = 'editHostelModal';
            $editFormData = [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'location' => $location,
                'hostel_image' => $hostelImage
            ];
        }
    }

    if ($action === 'delete_hostel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT hostel_image FROM hostels WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare('DELETE FROM hostels WHERE id = ?');
            $stmt->execute([$id]);

            if (!empty($row['hostel_image']) && file_exists(__DIR__ . '/../' . $row['hostel_image'])) {
                unlink(__DIR__ . '/../' . $row['hostel_image']);
            }

            $success = 'Hostel deleted successfully.';
        }
    }
}

$hostels = $pdo->query('SELECT id, name, description, location, hostel_image, created_at FROM hostels ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
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
            <h4 class="mb-0">Manage Hostels</h4>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addHostelModal">
                <i class="bi bi-plus-circle"></i> Add Hostel
            </button>
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
                <?php foreach ($hostels as $hostel): ?>
                    <?php $json = htmlspecialchars(json_encode($hostel), ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td><?= (int)$hostel['id'] ?></td>
                        <td><?= htmlspecialchars($hostel['name']) ?></td>
                        <td><?= htmlspecialchars($hostel['description']) ?></td>
                        <td><?= htmlspecialchars($hostel['location']) ?></td>
                        <td>
                            <?php if (!empty($hostel['hostel_image'])): ?>
                                <img src="../<?= htmlspecialchars($hostel['hostel_image']) ?>" alt="Hostel" class="rounded hostel-thumb-sm">
                            <?php else: ?>
                                <span class="text-muted">No image</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($hostel['created_at']) ?></td>
                        <td class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-info text-white view-hostel-btn" data-hostel="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewHostelModal">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="button" class="btn btn-sm btn-warning edit-hostel-btn" data-hostel="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#editHostelModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="post" data-confirm="Delete this hostel?" class="d-inline">
                                <input type="hidden" name="action" value="delete_hostel">
                                <input type="hidden" name="id" value="<?= (int)$hostel['id'] ?>">
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

<div class="modal fade" id="addHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="add_hostel">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building-add"></i> Add Hostel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hostel Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Image</label>
                            <input type="file" name="hostel_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Hostel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="update_hostel">
                <input type="hidden" name="id" id="editHostelId">
                <input type="hidden" name="existing_image" id="editHostelExistingImage">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Hostel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hostel Name</label>
                            <input type="text" name="name" id="editHostelName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="editHostelLocation" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editHostelDescription" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Replace Image (optional)</label>
                            <input type="file" name="hostel_image" class="form-control" accept="image/*">
                            <img id="editHostelPreview" src="" alt="Current" class="mt-2 rounded hostel-preview-md">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Update Hostel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building"></i> Hostel Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>ID:</strong> <span id="viewHostelId">-</span></p>
                <p class="mb-2"><strong>Name:</strong> <span id="viewHostelName">-</span></p>
                <p class="mb-2"><strong>Location:</strong> <span id="viewHostelLocation">-</span></p>
                <p class="mb-2"><strong>Description:</strong> <span id="viewHostelDescription">-</span></p>
                <p class="mb-2"><strong>Created:</strong> <span id="viewHostelCreated">-</span></p>
                <img id="viewHostelImage" src="" alt="Hostel" class="rounded hostel-view-lg">
            </div>
        </div>
    </div>
</div>

<div
    id="manageHostelConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>">
</div>
<script src="../assets/js/admin-manage-hostel.js"></script>
