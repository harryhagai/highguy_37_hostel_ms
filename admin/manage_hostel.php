<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/manage_hostel_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$openModal = $state['openModal'];
$editFormData = $state['editFormData'];
$addFormData = $state['addFormData'];
$hostels = $state['hostels'];
$stats = $state['stats'];
$locationOptions = $state['locationOptions'];
?>
<div class="container-fluid px-0 users-page hostels-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Hostels</p>
            <h5 class="mb-0"><?= (int)$stats['total_hostels'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">New Today</p>
            <h5 class="mb-0"><?= (int)$stats['new_today'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">New This Week</p>
            <h5 class="mb-0"><?= (int)$stats['new_week'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">With Images</p>
            <h5 class="mb-0"><?= (int)$stats['with_images'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Locations</p>
            <h5 class="mb-0"><?= (int)$stats['unique_locations'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Hostels</h4>
                <p class="text-muted mb-0">Create, filter, and manage hostels faster.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addHostelModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Hostel
                </button>
            </div>
        </div>

        <div class="users-filters mb-3">
            <div class="row g-2 align-items-center mb-2">
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon users-field-search">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            class="form-control form-control-sm"
                            id="hostelsSearchInput"
                            placeholder="Search hostels">
                        <button type="button" id="clearHostelsSearch" class="users-field-clear" aria-label="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-geo-alt"></i>
                        <select class="form-select form-select-sm" id="hostelsLocationFilter">
                            <option value="">All Locations</option>
                            <?php foreach ($locationOptions as $location): ?>
                                <option value="<?= htmlspecialchars(strtolower((string)$location), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$location) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-people"></i>
                        <select class="form-select form-select-sm" id="hostelsGenderFilter">
                            <option value="">All Gender</option>
                            <option value="male">Male Only</option>
                            <option value="female">Female Only</option>
                            <option value="all">All Genders</option>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-image"></i>
                        <select class="form-select form-select-sm" id="hostelsImageFilter">
                            <option value="">Image</option>
                            <option value="yes">With</option>
                            <option value="no">Without</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-2 align-items-center">
                <div class="col-xl-7">
                    <form method="post" id="bulkHostelsForm" class="d-flex flex-wrap gap-2 align-items-center" data-confirm="Apply selected bulk action?">
                        <input type="hidden" name="action" value="bulk_hostels">
                        <div id="bulkHostelSelectedInputs"></div>
                        <select name="bulk_action_type" id="bulkHostelActionType" class="form-select form-select-sm users-bulk-select">
                            <option value="">Bulk Action</option>
                            <option value="set_inactive">Disable Selected</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkHostelApplyBtn">
                            <i class="bi bi-lightning-charge me-1"></i> Apply
                        </button>
                        <span class="small text-muted" id="bulkHostelSelectedCount">0 selected</span>
                    </form>
                </div>
                <div class="col-xl-5 d-flex flex-wrap gap-2 justify-content-xl-end align-items-center">
                    <span class="users-result-count text-muted small" id="hostelsResultCount">
                        <?= (int)count($hostels) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0" id="hostelsTable">
                <thead>
                    <tr>
                        <th class="users-check-col">
                            <input type="checkbox" class="form-check-input" id="selectAllHostels" aria-label="Select all hostels">
                        </th>
                        <th>Hostel</th>
                        <th>Status</th>
                        <th>Rooms</th>
                        <th>Bed Capacity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hostels as $hostel): ?>
                    <?php
                    $hostelId = (int)$hostel['id'];
                    $json = htmlspecialchars(json_encode($hostel), ENT_QUOTES, 'UTF-8');
                    $hasImage = !empty($hostel['hostel_image']);
                    $searchText = strtolower(trim(
                        (string)($hostel['id'] ?? '') . ' ' .
                        (string)($hostel['name'] ?? '') . ' ' .
                        (string)($hostel['location'] ?? '') . ' ' .
                        (string)($hostel['gender'] ?? '')
                    ));
                    $imageUrl = $hasImage ? '../' . ltrim((string)$hostel['hostel_image'], '/') : '';
                    $genderValue = strtolower(trim((string)($hostel['gender'] ?? 'all')));
                    if (!in_array($genderValue, ['male', 'female', 'all'], true)) {
                        $genderValue = 'all';
                    }
                    $genderLabel = $genderValue === 'male'
                        ? 'Male Only'
                        : ($genderValue === 'female' ? 'Female Only' : 'All Genders');
                    ?>
                    <tr
                        class="hostel-row"
                        data-id="<?= $hostelId ?>"
                        data-hostel="<?= $json ?>"
                        data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                        data-location="<?= htmlspecialchars(strtolower(trim((string)($hostel['location'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>"
                        data-gender="<?= htmlspecialchars($genderValue, ENT_QUOTES, 'UTF-8') ?>"
                        data-has-image="<?= $hasImage ? 'yes' : 'no' ?>"
                        data-created="<?= htmlspecialchars((string)($hostel['created_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td>
                            <input type="checkbox" class="form-check-input hostel-select" value="<?= $hostelId ?>" aria-label="Select hostel <?= $hostelId ?>">
                        </td>
                        <td>
                            <div class="user-identity">
                                <div class="user-avatar-shell">
                                    <?php if ($hasImage): ?>
                                        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Hostel" class="user-avatar-img" loading="lazy">
                                    <?php else: ?>
                                        <span class="user-avatar-fallback"><i class="bi bi-building"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)($hostel['name'] ?? '')) ?></div>
                                    <div class="mt-1">
                                        <span class="badge user-role-badge <?= $genderValue === 'all' ? 'user-role-user' : 'user-role-admin' ?>">
                                            <?= htmlspecialchars($genderLabel) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php $hostelStatus = (string)($hostel['status'] ?? 'inactive'); ?>
                            <?php if ($hostelStatus === 'active'): ?>
                                <span class="badge user-status-badge user-status-active">Active</span>
                            <?php else: ?>
                                <span class="badge user-status-badge user-status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge user-status-badge user-status-active">
                                <?= (int)($hostel['room_count'] ?? 0) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge user-role-badge user-role-user">
                                <?= (int)($hostel['bed_capacity'] ?? 0) ?>
                            </span>
                        </td>
                        <td class="d-flex gap-1 flex-wrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary view-hostel-btn"
                                data-hostel="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#viewHostelModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="View hostel details"
                            >
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary edit-hostel-btn"
                                data-hostel="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#editHostelModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="Edit hostel"
                            >
                                <i class="bi bi-pencil me-1"></i>Update
                            </button>
                            <form method="post" data-confirm="Disable this hostel (set inactive)?" class="d-inline">
                                <input type="hidden" name="action" value="disable_hostel">
                                <input type="hidden" name="id" value="<?= $hostelId ?>">
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle-tooltip="tooltip"
                                    title="Disable hostel"
                                    <?= $hostelStatus === 'inactive' ? 'disabled' : '' ?>
                                >
                                    <i class="bi bi-slash-circle me-1"></i>Inactive
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="hostelsNoResultsRow" class="<?= empty($hostels) ? '' : 'd-none' ?>">
                        <td colspan="6" class="text-center text-muted py-4">
                            <?= empty($hostels) ? 'No hostels found.' : 'No hostels match your filters.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="users-lazy-bar mt-3">
            <span class="small text-muted" id="hostelsLoadedInfo">Showing 0 of 0</span>
            <span class="small text-muted">Scroll down to load more</span>
        </div>
        <div id="hostelsLazySentinel" class="users-lazy-sentinel" aria-hidden="true"></div>
    </div>
</div>

<div class="modal fade users-modal" id="addHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="add_hostel">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building-add"></i> Add Hostel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-card-text me-1"></i> Hostel Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Hostel Name</label>
                                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars((string)($addFormData['name'] ?? '')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Location</label>
                                            <input type="text" name="location" class="form-control" required value="<?= htmlspecialchars((string)($addFormData['location'] ?? '')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Gender</label>
                                            <select name="gender" class="form-select">
                                                <?php $addGender = strtolower(trim((string)($addFormData['gender'] ?? 'all'))); ?>
                                                <option value="male" <?= $addGender === 'male' ? 'selected' : '' ?>>Male Only</option>
                                                <option value="female" <?= $addGender === 'female' ? 'selected' : '' ?>>Female Only</option>
                                                <option value="all" <?= $addGender === 'all' ? 'selected' : '' ?>>All Genders</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea
                                                name="description"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Hostel description (optional)"><?= htmlspecialchars((string)($addFormData['description'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-image me-1"></i> Hostel Image</h6>
                                    <label class="form-label">Upload Image</label>
                                    <input type="file" name="hostel_image" class="form-control" accept="image/*">
                                    <div class="form-text mt-2">All image formats are accepted.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check-circle me-1"></i> Save Hostel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="editHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" autocomplete="off" id="editHostelForm">
                <input type="hidden" name="action" value="update_hostel">
                <input type="hidden" name="id" id="editHostelId">
                <input type="hidden" name="existing_image" id="editHostelExistingImage">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Hostel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-pencil-square me-1"></i> Edit Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Hostel Name</label>
                                            <input type="text" name="name" id="editHostelName" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Location</label>
                                            <input type="text" name="location" id="editHostelLocation" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Gender</label>
                                            <select name="gender" id="editHostelGender" class="form-select">
                                                <option value="male">Male Only</option>
                                                <option value="female">Female Only</option>
                                                <option value="all">All Genders</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea
                                                name="description"
                                                id="editHostelDescription"
                                                class="form-control"
                                                rows="3"
                                                placeholder="Hostel description (optional)"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-image-alt me-1"></i> Replace Image</h6>
                                    <label class="form-label">New Image (Optional)</label>
                                    <input type="file" name="hostel_image" class="form-control" accept="image/*">
                                    <div class="form-text mt-2">Leave empty to keep the current image.</div>
                                    <img id="editHostelPreview" src="" alt="Current image" class="mt-3 rounded hostel-preview-md">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-check-circle me-1"></i> Update Hostel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="viewHostelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building"></i> Hostel Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <p class="mb-2"><strong>Name:</strong> <span id="viewHostelName">-</span></p>
                        <p class="mb-2"><strong>Location:</strong> <span id="viewHostelLocation">-</span></p>
                        <p class="mb-2"><strong>Gender:</strong> <span id="viewHostelGender">-</span></p>
                        <p class="mb-2"><strong>Rooms:</strong> <span id="viewHostelRooms">-</span></p>
                        <p class="mb-2"><strong>Created:</strong> <span id="viewHostelCreated">-</span></p>
                    </div>
                    <div class="col-md-5">
                        <img id="viewHostelImage" src="" alt="Hostel" class="rounded hostel-view-lg">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div
    id="manageHostelConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-add-form="<?= htmlspecialchars(json_encode($addFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-total-hostels="<?= (int)count($hostels) ?>">
</div>
<script src="../assets/js/admin-manage-hostel.js"></script>
