<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/settings_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$profile = $state['profile'];
$hasPhoneColumn = $state['hasPhoneColumn'];
$hasProfilePhotoColumn = $state['hasProfilePhotoColumn'];

$profilePhoto = trim((string)($profile['profile_photo'] ?? ''));
if ($profilePhoto === '') {
    $profilePhoto = '../assets/images/prof.jpg';
}

$memberSince = !empty($profile['created_at']) ? date('d M Y', strtotime((string)$profile['created_at'])) : '-';
$lastUpdated = !empty($profile['updated_at']) ? date('d M Y H:i', strtotime((string)$profile['updated_at'])) : '-';
?>

<div class="container-fluid px-0 users-page settings-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$profile): ?>
        <div class="dashboard-card">
            <h4 class="mb-2"><i class="bi bi-person-x me-2"></i>Profile Not Found</h4>
            <p class="text-muted mb-0">Unable to load admin profile details.</p>
        </div>
    <?php else: ?>
        <div class="users-quick-stats mb-3">
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Admin ID</p>
                <h5 class="mb-0"><?= (int)$profile['id'] ?></h5>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Role</p>
                <h5 class="mb-0 text-capitalize"><?= htmlspecialchars((string)$profile['role']) ?></h5>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Member Since</p>
                <h5 class="mb-0"><?= htmlspecialchars($memberSince) ?></h5>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Last Update</p>
                <h5 class="mb-0"><?= htmlspecialchars($lastUpdated) ?></h5>
            </article>
            <article class="users-mini-stat">
                <p class="users-mini-label mb-1">Profile Photo</p>
                <h5 class="mb-0"><?= $hasProfilePhotoColumn ? 'Enabled' : 'Not Available' ?></h5>
            </article>
        </div>

        <div class="dashboard-card users-shell mb-4">
            <div class="users-toolbar mb-3">
                <div>
                    <h4 class="mb-1">Admin Profile Settings</h4>
                    <p class="text-muted mb-0">Update your account details, security credentials, and profile photo.</p>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="border rounded-3 p-3 h-100 settings-photo-card">
                        <h6 class="mb-3 fw-semibold"><i class="bi bi-image me-1"></i> Profile Photo</h6>
                        <div class="text-center mb-3">
                            <img
                                src="<?= htmlspecialchars($profilePhoto) ?>"
                                alt="Admin profile photo"
                                class="settings-profile-photo rounded-circle"
                                id="adminProfilePhotoPreview"
                            >
                        </div>

                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="action" value="update_photo">
                            <label class="form-label">Upload New Photo</label>
                            <input
                                type="file"
                                class="form-control"
                                id="adminProfilePhotoInput"
                                name="profile_photo"
                                accept="image/*"
                                <?= $hasProfilePhotoColumn ? '' : 'disabled' ?>
                            >
                            <div class="form-text mt-2">Max size: 5MB. Image formats only.</div>
                            <button type="submit" class="btn btn-outline-primary w-100 mt-3" <?= $hasProfilePhotoColumn ? '' : 'disabled' ?>>
                                <i class="bi bi-upload me-1"></i> Update Photo
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <h6 class="mb-3 fw-semibold"><i class="bi bi-person-gear me-1"></i> Account Information</h6>
                                <form method="post" autocomplete="off">
                                    <input type="hidden" name="action" value="update_account">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Username</label>
                                            <input
                                                type="text"
                                                name="username"
                                                class="form-control"
                                                required
                                                value="<?= htmlspecialchars((string)$profile['username']) ?>"
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input
                                                type="email"
                                                name="email"
                                                class="form-control"
                                                required
                                                value="<?= htmlspecialchars((string)$profile['email']) ?>"
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input
                                                type="text"
                                                name="phone"
                                                class="form-control"
                                                placeholder="+2557XXXXXXXX or 07XXXXXXXX"
                                                value="<?= htmlspecialchars((string)($profile['phone'] ?? '')) ?>"
                                                <?= $hasPhoneColumn ? '' : 'disabled' ?>
                                            >
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars((string)$profile['role']) ?>" disabled>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-success mt-3">
                                        <i class="bi bi-check-circle me-1"></i> Save Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="mb-3 fw-semibold"><i class="bi bi-shield-lock me-1"></i> Security</h6>
                                <form method="post" autocomplete="off">
                                    <input type="hidden" name="action" value="update_password">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" name="current_password" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">New Password</label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Confirm Password</label>
                                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger mt-3">
                                        <i class="bi bi-key me-1"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="../assets/js/admin-settings.js"></script>
