<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/settings_controller.php';
$errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
$success = (string)($state['success'] ?? '');
$profile = $state['profile'];
$hasPhoneColumn = $state['hasPhoneColumn'];
$hasProfilePhotoColumn = $state['hasProfilePhotoColumn'];

$semesterState = require __DIR__ . '/../controllers/admin/semester_settings_controller.php';
$semesterErrors = is_array($semesterState['errors'] ?? null) ? $semesterState['errors'] : [];
$semesterSuccess = (string)($semesterState['success'] ?? '');
$semesterTableReady = (bool)($semesterState['table_ready'] ?? false);
$semesters = is_array($semesterState['semesters'] ?? null) ? $semesterState['semesters'] : [];
$semesterCreateDraft = is_array($semesterState['create_draft'] ?? null) ? $semesterState['create_draft'] : [
    'semester_key' => 1,
    'semester_name' => 'Semester 1',
    'start_date' => '',
    'months' => 4,
    'is_active' => 1,
];

$paymentState = require __DIR__ . '/../controllers/admin/payment_settings_controller.php';
$paymentErrors = is_array($paymentState['errors'] ?? null) ? $paymentState['errors'] : [];
$paymentSuccess = (string)($paymentState['success'] ?? '');
$paymentTableReady = (bool)($paymentState['table_ready'] ?? false);
$paymentControlNumbers = is_array($paymentState['control_numbers'] ?? null) ? $paymentState['control_numbers'] : [];

$requestedTab = strtolower(trim((string)($_GET['settings_tab'] ?? '')));
if (($requestedTab === '' || $requestedTab === 'profile') && ((string)($_GET['page'] ?? '') === 'payment_settings')) {
    $requestedTab = 'payment';
}

if (!in_array($requestedTab, ['profile', 'semester', 'payment'], true)) {
    $requestedTab = 'profile';
}

if (!empty($paymentErrors) || $paymentSuccess !== '') {
    $activeTab = 'payment';
} elseif (!empty($semesterErrors) || $semesterSuccess !== '') {
    $activeTab = 'semester';
} else {
    $activeTab = $requestedTab;
}

$profilePhoto = trim((string)($profile['profile_photo'] ?? ''));
if ($profilePhoto === '') {
    $profilePhoto = '../assets/images/prof.jpg';
}

$memberSince = !empty($profile['created_at']) ? date('d M Y', strtotime((string)$profile['created_at'])) : '-';
$lastUpdated = !empty($profile['updated_at']) ? date('d M Y H:i', strtotime((string)$profile['updated_at'])) : '-';
$paymentStats = [
    'total' => count($paymentControlNumbers),
    'active' => 0,
    'inactive' => 0,
];
foreach ($paymentControlNumbers as $controlNumber) {
    if ((int)($controlNumber['is_active'] ?? 1) === 1) {
        $paymentStats['active']++;
    } else {
        $paymentStats['inactive']++;
    }
}

$toPaymentIconUrl = static function (?string $path): string {
    $value = trim((string)$path);
    if ($value === '') {
        return '../assets/images/logo.png';
    }
    if (strpos($value, '../') === 0 || strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
        return $value;
    }
    return '../' . ltrim($value, '/');
};
?>

<div class="container-fluid px-0 users-page settings-page">
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

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'profile' ? 'active' : '' ?>" id="settings-profile-tab" data-bs-toggle="tab" data-bs-target="#settings-profile-pane" type="button" role="tab" aria-controls="settings-profile-pane" aria-selected="<?= $activeTab === 'profile' ? 'true' : 'false' ?>">
                    <i class="bi bi-person-gear me-1"></i>Profile Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'semester' ? 'active' : '' ?>" id="settings-semester-tab" data-bs-toggle="tab" data-bs-target="#settings-semester-pane" type="button" role="tab" aria-controls="settings-semester-pane" aria-selected="<?= $activeTab === 'semester' ? 'true' : 'false' ?>">
                    <i class="bi bi-calendar2-week me-1"></i>Semester Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'payment' ? 'active' : '' ?>" id="settings-payment-tab" data-bs-toggle="tab" data-bs-target="#settings-payment-pane" type="button" role="tab" aria-controls="settings-payment-pane" aria-selected="<?= $activeTab === 'payment' ? 'true' : 'false' ?>">
                    <i class="bi bi-wallet2 me-1"></i>Payment Settings
                </button>
            </li>
        </ul>

        <div class="dashboard-card users-shell mb-4 tab-content">
            <div class="tab-pane fade <?= $activeTab === 'profile' ? 'show active' : '' ?>" id="settings-profile-pane" role="tabpanel" aria-labelledby="settings-profile-tab">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

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
                                <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Admin profile photo" class="settings-profile-photo rounded-circle" id="adminProfilePhotoPreview">
                            </div>

                            <form method="post" enctype="multipart/form-data" autocomplete="off">
                                <input type="hidden" name="action" value="update_photo">
                                <label class="form-label">Upload New Photo</label>
                                <input type="file" class="form-control" id="adminProfilePhotoInput" name="profile_photo" accept="image/*" <?= $hasProfilePhotoColumn ? '' : 'disabled' ?>>
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
                                                <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars((string)$profile['username']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string)$profile['email']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Phone</label>
                                                <input type="text" name="phone" class="form-control" placeholder="+2557XXXXXXXX or 07XXXXXXXX" value="<?= htmlspecialchars((string)($profile['phone'] ?? '')) ?>" <?= $hasPhoneColumn ? '' : 'disabled' ?>>
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

            <div class="tab-pane fade <?= $activeTab === 'semester' ? 'show active' : '' ?>" id="settings-semester-pane" role="tabpanel" aria-labelledby="settings-semester-tab">
                <?php if (!empty($semesterErrors)): ?>
                    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $semesterErrors)) ?></div>
                <?php endif; ?>
                <?php if ($semesterSuccess !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($semesterSuccess) ?></div>
                <?php endif; ?>

                <div class="users-toolbar mb-3">
                    <div>
                        <h4 class="mb-1">Semester Management</h4>
                        <p class="text-muted mb-0">Create, update, or delete semester windows (4 or 6 months).</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSemesterModal">
                            <i class="bi bi-plus-circle me-1"></i>Create Semester
                        </button>
                    </div>
                </div>

                <?php if (!$semesterTableReady): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Semester table is not ready. Run semester migration first.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Months</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($semesters)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No semester records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($semesters as $semester): ?>
                                    <tr>
                                        <td><?= (int)$semester['id'] ?></td>
                                        <td>Semester <?= (int)$semester['semester_key'] ?> - <?= ucfirst((string)($semester['term_type'] ?? 'short')) ?> Term</td>
                                        <td><?= htmlspecialchars((string)$semester['semester_name']) ?></td>
                                        <td><?= (int)$semester['months'] ?> months</td>
                                        <td><?= htmlspecialchars((string)$semester['start_date']) ?></td>
                                        <td><?= htmlspecialchars((string)$semester['end_date']) ?></td>
                                        <td><?= (int)$semester['is_active'] === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                        <td class="settings-actions-cell">
                                            <div class="settings-action-buttons">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSemesterModal" data-id="<?= (int)$semester['id'] ?>" data-semester-key="<?= (int)$semester['semester_key'] ?>" data-term-type="<?= htmlspecialchars((string)($semester['term_type'] ?? 'short')) ?>" data-name="<?= htmlspecialchars((string)$semester['semester_name']) ?>" data-start-date="<?= htmlspecialchars((string)$semester['start_date']) ?>" data-active="<?= (int)$semester['is_active'] ?>" title="Edit semester">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit
                                                </button>
                                                <form method="post" data-confirm="Delete this semester?" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_semester">
                                                    <input type="hidden" name="id" value="<?= (int)$semester['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete semester">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade <?= $activeTab === 'payment' ? 'show active' : '' ?>" id="settings-payment-pane" role="tabpanel" aria-labelledby="settings-payment-tab">
                <?php if (!empty($paymentErrors)): ?>
                    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $paymentErrors)) ?></div>
                <?php endif; ?>
                <?php if ($paymentSuccess !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($paymentSuccess) ?></div>
                <?php endif; ?>

                <div class="users-quick-stats mb-3">
                    <article class="users-mini-stat">
                        <p class="users-mini-label mb-1">Total Networks</p>
                        <h5 class="mb-0"><?= (int)$paymentStats['total'] ?></h5>
                    </article>
                    <article class="users-mini-stat">
                        <p class="users-mini-label mb-1">Active</p>
                        <h5 class="mb-0"><?= (int)$paymentStats['active'] ?></h5>
                    </article>
                    <article class="users-mini-stat">
                        <p class="users-mini-label mb-1">Inactive</p>
                        <h5 class="mb-0"><?= (int)$paymentStats['inactive'] ?></h5>
                    </article>
                </div>

                <div class="users-toolbar mb-3">
                    <div>
                        <h4 class="mb-1">Payment Control Numbers</h4>
                        <p class="text-muted mb-0">Manage mobile payment control numbers visible in student payment verification.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createControlNumberModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Control Number
                    </button>
                </div>

                <?php if (!$paymentTableReady): ?>
                    <div class="alert alert-warning mb-0">
                        Payment control numbers table is missing. Run payment migration first.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Network</th>
                                    <th>Control Number</th>
                                    <th>Company</th>
                                    <th>Info</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paymentControlNumbers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No payment control numbers configured yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paymentControlNumbers as $network): ?>
                                        <?php
                                        $networkJson = htmlspecialchars(json_encode($network), ENT_QUOTES, 'UTF-8');
                                        $isActiveNetwork = (int)($network['is_active'] ?? 1) === 1;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-identity">
                                                    <div class="user-avatar-shell">
                                                        <img src="<?= htmlspecialchars($toPaymentIconUrl((string)($network['network_icon'] ?? ''))) ?>" alt="<?= htmlspecialchars((string)$network['network_name']) ?>" width="36" height="36" class="rounded-circle object-fit-cover">
                                                    </div>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string)$network['network_name']) ?></div>
                                                </div>
                                            </td>
                                            <td><strong><?= htmlspecialchars((string)$network['control_number']) ?></strong></td>
                                            <td><?= htmlspecialchars((string)($network['company_name'] ?? '-')) ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars((string)($network['info'] ?? '-')) ?></td>
                                            <td><?= $isActiveNetwork ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                            <td class="settings-actions-cell">
                                                <div class="settings-action-buttons">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-control-number-btn" data-network="<?= $networkJson ?>" data-bs-toggle="modal" data-bs-target="#editControlNumberModal">
                                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                                    </button>
                                                    <form method="post" data-confirm="Delete this control number?" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_control_number">
                                                        <input type="hidden" name="id" value="<?= (int)$network['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createSemesterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="createSemesterForm">
            <input type="hidden" name="action" value="create_semester">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Create Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Semester Type</label>
                    <select class="form-select" name="semester_key" required>
                        <option value="1" <?= (int)$semesterCreateDraft['semester_key'] === 1 ? 'selected' : '' ?>>Semester 1</option>
                        <option value="2" <?= (int)$semesterCreateDraft['semester_key'] === 2 ? 'selected' : '' ?>>Semester 2</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Semester Name</label>
                    <input type="text" class="form-control" name="semester_name" value="<?= htmlspecialchars((string)$semesterCreateDraft['semester_name']) ?>" required>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label">Term Type</label>
                        <select class="form-select js-semester-term" name="term_type" required>
                            <option value="short" <?= (($semesterCreateDraft['term_type'] ?? 'short') === 'short') ? 'selected' : '' ?>>Short Term</option>
                            <option value="long" <?= (($semesterCreateDraft['term_type'] ?? 'short') === 'long') ? 'selected' : '' ?>>Long Term</option>
                        </select>
                    </div>
                    <div class="col-sm-6 d-flex align-items-end">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="createSemesterActive" <?= (int)$semesterCreateDraft['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="createSemesterActive">Active</label>
                        </div>
                        <input type="text" class="form-control js-semester-months-display" readonly>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-sm-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control js-semester-start" name="start_date" value="<?= htmlspecialchars((string)$semesterCreateDraft['start_date']) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control js-semester-end" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check2-circle me-1"></i>Create Semester</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editSemesterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="editSemesterForm">
            <input type="hidden" name="action" value="update_semester">
            <input type="hidden" name="id" id="editSemesterId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Edit Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Semester Name</label>
                    <input type="text" class="form-control" name="semester_name" id="editSemesterName" required>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label">Semester Type</label>
                        <select class="form-select" name="semester_key" id="editSemesterKey" required>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Term Type</label>
                        <select class="form-select js-semester-term" name="term_type" id="editSemesterTermType" required>
                            <option value="short">Short Term</option>
                            <option value="long">Long Term</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-sm-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editSemesterActive">
                            <label class="form-check-label" for="editSemesterActive">Active</label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Months</label>
                        <input type="text" class="form-control js-semester-months-display" id="editSemesterMonthsDisplay" readonly>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-sm-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control js-semester-start" name="start_date" id="editSemesterStartDate" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control js-semester-end" id="editSemesterEndDate" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-outline-primary">Save Changes</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="createControlNumberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="create_control_number">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Control Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Network Name *</label>
                        <input type="text" name="network_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Control Number *</label>
                        <input type="text" name="control_number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="createControlActive" name="is_active" value="1" checked>
                            <label class="form-check-label" for="createControlActive">Active</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Network Icon</label>
                        <input type="file" name="network_icon" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Payment Info (Tip)</label>
                        <textarea name="info" class="form-control" rows="3" placeholder="Example: Dial *150*00#, choose Pay Bill, enter control number"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check2-circle me-1"></i>Save</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editControlNumberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" enctype="multipart/form-data" class="modal-content" id="editControlNumberForm">
            <input type="hidden" name="action" value="update_control_number">
            <input type="hidden" name="id" id="editControlId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Edit Control Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Network Name *</label>
                        <input type="text" name="network_name" id="editControlNetworkName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Control Number *</label>
                        <input type="text" name="control_number" id="editControlNumber" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" id="editControlCompanyName" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="editControlSortOrder" class="form-control" min="0">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editControlActive" name="is_active" value="1">
                            <label class="form-check-label" for="editControlActive">Active</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Replace Network Icon</label>
                        <input type="file" name="network_icon" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Payment Info (Tip)</label>
                        <textarea name="info" id="editControlInfo" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    function parseDate(value) {
        if (!value) return null;
        var parts = String(value).split('-');
        if (parts.length !== 3) return null;
        var year = Number(parts[0]);
        var month = Number(parts[1]);
        var day = Number(parts[2]);
        if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
            return null;
        }
        return new Date(Date.UTC(year, month - 1, day));
    }

    function formatDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }
        var y = date.getUTCFullYear();
        var m = String(date.getUTCMonth() + 1).padStart(2, '0');
        var d = String(date.getUTCDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function calcEndDate(startValue, monthsValue) {
        var start = parseDate(startValue);
        var term = String(monthsValue || '').toLowerCase();
        var months = term === 'long' ? 6 : (term === 'short' ? 4 : 0);
        if (!start || (months !== 4 && months !== 6)) {
            return '';
        }

        var end = new Date(start.getTime());
        end.setUTCMonth(end.getUTCMonth() + months);
        end.setUTCDate(end.getUTCDate() - 1);
        return formatDate(end);
    }

    function wireForm(form) {
        if (!form) return;
        var start = form.querySelector('.js-semester-start');
        var term = form.querySelector('.js-semester-term');
        var end = form.querySelector('.js-semester-end');
        var monthsDisplay = form.querySelector('.js-semester-months-display');
        if (!start || !term || !end) return;

        var refresh = function () {
            end.value = calcEndDate(start.value, term.value);
            if (monthsDisplay) {
                monthsDisplay.value = term.value === 'long' ? '6 months' : '4 months';
            }
        };

        start.addEventListener('change', refresh);
        term.addEventListener('change', refresh);
        refresh();
    }

    wireForm(document.getElementById('createSemesterForm'));
    wireForm(document.getElementById('editSemesterForm'));

    var editModal = document.getElementById('editSemesterModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var idInput = document.getElementById('editSemesterId');
            var keyInput = document.getElementById('editSemesterKey');
            var termInput = document.getElementById('editSemesterTermType');
            var nameInput = document.getElementById('editSemesterName');
            var startInput = document.getElementById('editSemesterStartDate');
            var endInput = document.getElementById('editSemesterEndDate');
            var activeInput = document.getElementById('editSemesterActive');
            var monthsDisplay = document.getElementById('editSemesterMonthsDisplay');

            if (idInput) idInput.value = button.getAttribute('data-id') || '';
            if (keyInput) keyInput.value = button.getAttribute('data-semester-key') || '1';
            if (termInput) termInput.value = button.getAttribute('data-term-type') || 'short';
            if (nameInput) nameInput.value = button.getAttribute('data-name') || '';
            if (startInput) startInput.value = button.getAttribute('data-start-date') || '';
            if (activeInput) activeInput.checked = String(button.getAttribute('data-active') || '0') === '1';
            if (endInput && startInput && termInput) {
                endInput.value = calcEndDate(startInput.value, termInput.value);
            }
            if (monthsDisplay && termInput) {
                monthsDisplay.value = termInput.value === 'long' ? '6 months' : '4 months';
            }
        });
    }
})();
</script>
<script src="../assets/js/admin-settings.js"></script>
<script src="../assets/js/admin-payment-settings.js"></script>
