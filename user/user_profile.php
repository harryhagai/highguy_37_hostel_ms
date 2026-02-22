<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['user'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/user/profile_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$profile = $state['profile'];
$supportsPhone = $state['supports_phone'];
$supportsPhoto = $state['supports_photo'];
?>
<div class="container-fluid px-0 user-profile-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?= implode('<br>', array_map(static fn($err) => htmlspecialchars((string)$err), $errors)) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string)$success) ?></div>
    <?php endif; ?>

    <?php if (!$profile): ?>
        <div class="alert alert-warning">Unable to load your profile.</div>
    <?php else: ?>
        <div class="dashboard-card user-profile-shell">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="user-profile-left h-100">
                        <img src="<?= htmlspecialchars((string)$profile['profile_photo_url']) ?>" alt="Profile Photo" class="user-profile-avatar">
                        <h5 class="mb-1"><?= htmlspecialchars((string)$profile['username']) ?></h5>
                        <p class="text-muted mb-3"><?= htmlspecialchars((string)$profile['email']) ?></p>

                        <?php if ($supportsPhoto): ?>
                            <form method="post" enctype="multipart/form-data" id="photoForm" class="w-100">
                                <input type="hidden" name="action" value="update_photo">
                                <label class="form-label">Update Photo</label>
                                <input type="file" class="form-control" id="profilePhotoInput" name="profile_photo" accept="image/*">
                            </form>
                            <small class="text-muted mt-2 d-block">Image upload starts automatically once selected.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-8">
                    <h4 class="mb-3"><i class="bi bi-person-circle me-2"></i>My Profile</h4>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="update_account">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars((string)$profile['username']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)$profile['email']) ?>" required>
                        </div>
                        <?php if ($supportsPhone): ?>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string)$profile['phone']) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars((string)$profile['role']) ?>" readonly>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-check-circle me-1"></i>Save Changes
                            </button>
                            <a href="user_dashboard_layout.php?page=dashboard" data-spa-page="dashboard" data-no-spinner="true" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="../assets/js/user-profile.js"></script>
