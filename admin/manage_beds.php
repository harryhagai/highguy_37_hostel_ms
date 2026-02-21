<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/manage_beds_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$openModal = $state['openModal'];
$editFormData = $state['editFormData'];
$addFormData = $state['addFormData'];
$rooms = $state['rooms'];
$beds = $state['beds'];
$stats = $state['stats'];
$hostelOptions = $state['hostelOptions'];
$roomOptions = $state['roomOptions'];
?>
<div class="container-fluid px-0 users-page beds-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Beds</p>
            <h5 class="mb-0"><?= (int)$stats['total_beds'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Active</p>
            <h5 class="mb-0"><?= (int)$stats['active_beds'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Maintenance</p>
            <h5 class="mb-0"><?= (int)$stats['maintenance_beds'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Inactive</p>
            <h5 class="mb-0"><?= (int)$stats['inactive_beds'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">New Today</p>
            <h5 class="mb-0"><?= (int)$stats['new_today'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Beds</h4>
                <p class="text-muted mb-0">Create, filter, and manage beds faster.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBedModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Bed
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
                            id="bedsSearchInput"
                            placeholder="Search beds">
                        <button type="button" id="clearBedsSearch" class="users-field-clear" aria-label="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-buildings"></i>
                        <select class="form-select form-select-sm" id="bedsHostelFilter">
                            <option value="">All Hostels</option>
                            <?php foreach ($hostelOptions as $hostel): ?>
                                <option value="<?= (int)$hostel['id'] ?>"><?= htmlspecialchars((string)$hostel['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-door-open"></i>
                        <select class="form-select form-select-sm" id="bedsRoomFilter">
                            <option value="">All Rooms</option>
                            <?php foreach ($roomOptions as $room): ?>
                                <option value="<?= (int)$room['id'] ?>">
                                    <?= htmlspecialchars((string)$room['hostel_name'] . ' - Room ' . (string)$room['room_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-toggle-on"></i>
                        <select class="form-select form-select-sm" id="bedsStatusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-2 align-items-center">
                <div class="col-xl-7">
                    <form method="post" id="bulkBedsForm" class="d-flex flex-wrap gap-2 align-items-center" data-confirm="Apply selected bulk action?">
                        <input type="hidden" name="action" value="bulk_beds">
                        <div id="bulkBedSelectedInputs"></div>
                        <select name="bulk_action_type" id="bulkBedActionType" class="form-select form-select-sm users-bulk-select">
                            <option value="">Bulk Action</option>
                            <option value="set_active">Set Active</option>
                            <option value="set_maintenance">Set Maintenance</option>
                            <option value="set_inactive">Set Inactive</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkBedApplyBtn">
                            <i class="bi bi-lightning-charge me-1"></i> Apply
                        </button>
                        <span class="small text-muted" id="bulkBedSelectedCount">0 selected</span>
                    </form>
                </div>
                <div class="col-xl-5 d-flex flex-wrap gap-2 justify-content-xl-end align-items-center">
                    <span class="users-result-count text-muted small" id="bedsResultCount">
                        <?= (int)count($beds) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0" id="bedsTable">
                <thead>
                    <tr>
                        <th class="users-check-col">
                            <input type="checkbox" class="form-check-input" id="selectAllBeds" aria-label="Select all beds">
                        </th>
                        <th>Bed</th>
                        <th>Hostel</th>
                        <th>Room</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($beds as $bed): ?>
                    <?php
                    $bedId = (int)$bed['id'];
                    $json = htmlspecialchars(json_encode($bed), ENT_QUOTES, 'UTF-8');
                    $status = strtolower(trim((string)($bed['status'] ?? 'active')));
                    if (!in_array($status, ['active', 'maintenance', 'inactive'], true)) {
                        $status = 'active';
                    }
                    $statusClass = $status === 'active'
                        ? 'user-status-active'
                        : ($status === 'maintenance' ? 'user-status-maintenance' : 'user-status-inactive');
                    $statusLabel = $status === 'maintenance' ? 'Maintenance' : ucfirst($status);
                    $searchText = strtolower(trim(
                        (string)($bed['id'] ?? '') . ' ' .
                        (string)($bed['hostel_name'] ?? '') . ' ' .
                        (string)($bed['room_number'] ?? '') . ' ' .
                        (string)($bed['bed_number'] ?? '') . ' ' .
                        (string)$status
                    ));
                    ?>
                    <tr
                        class="bed-row"
                        data-id="<?= $bedId ?>"
                        data-bed="<?= $json ?>"
                        data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                        data-hostel-id="<?= (int)($bed['hostel_id'] ?? 0) ?>"
                        data-room-id="<?= (int)($bed['room_id'] ?? 0) ?>"
                        data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td>
                            <input type="checkbox" class="form-check-input bed-select" value="<?= $bedId ?>" aria-label="Select bed <?= $bedId ?>">
                        </td>
                        <td>
                            <div class="user-identity">
                                <div class="user-avatar-shell">
                                    <span class="user-avatar-fallback"><i class="bi bi-grid-3x3-gap"></i></span>
                                </div>
                                <div>
                                    <div class="fw-semibold">Bed <?= htmlspecialchars((string)($bed['bed_number'] ?? '-')) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)($bed['hostel_name'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($bed['room_number'] ?? '-')) ?></td>
                        <td>
                            <span class="badge user-status-badge <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string)($bed['created_at_display'] ?? '-')) ?></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary view-bed-btn"
                                data-bed="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#viewBedModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="View bed details"
                            >
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary edit-bed-btn"
                                data-bed="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#editBedModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="Edit bed"
                            >
                                <i class="bi bi-pencil me-1"></i>Update
                            </button>
                            <form method="post" data-confirm="Set this bed to inactive?" class="d-inline">
                                <input type="hidden" name="action" value="set_bed_inactive">
                                <input type="hidden" name="id" value="<?= $bedId ?>">
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle-tooltip="tooltip"
                                    title="Set bed inactive"
                                    <?= $status === 'inactive' ? 'disabled' : '' ?>
                                >
                                    <i class="bi bi-slash-circle me-1"></i>Inactive
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="bedsNoResultsRow" class="<?= empty($beds) ? '' : 'd-none' ?>">
                        <td colspan="7" class="text-center text-muted py-4">
                            <?= empty($beds) ? 'No beds found.' : 'No beds match your filters.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="users-lazy-bar mt-3">
            <span class="small text-muted" id="bedsLoadedInfo">Showing 0 of 0</span>
            <span class="small text-muted">Scroll down to load more</span>
        </div>
        <div id="bedsLazySentinel" class="users-lazy-sentinel" aria-hidden="true"></div>
    </div>
</div>

<div class="modal fade users-modal" id="addBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_bed">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-grid-3x3-gap"></i> Add Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-card-text me-1"></i> Bed Details</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Room</label>
                                            <select name="room_id" class="form-select" required>
                                                <option value="">Select Room</option>
                                                <?php foreach ($rooms as $room): ?>
                                                    <option value="<?= (int)$room['id'] ?>" <?= ((int)($addFormData['room_id'] ?? 0) === (int)$room['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars((string)$room['hostel_name'] . ' - Room ' . (string)$room['room_number']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Bed Number</label>
                                            <input type="text" name="bed_number" class="form-control" required value="<?= htmlspecialchars((string)($addFormData['bed_number'] ?? '')) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Status</label>
                                            <?php $addStatus = strtolower(trim((string)($addFormData['status'] ?? 'active'))); ?>
                                            <select name="status" class="form-select">
                                                <option value="active" <?= $addStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="maintenance" <?= $addStatus === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                <option value="inactive" <?= $addStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-info-circle me-1"></i> Quick Notes</h6>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li>Bed number must be unique inside the same room.</li>
                                        <li>Use <strong>Maintenance</strong> when bed is temporarily unavailable.</li>
                                        <li>Use bulk action for faster status updates.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check-circle me-1"></i> Save Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="editBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" autocomplete="off" id="editBedForm">
                <input type="hidden" name="action" value="update_bed">
                <input type="hidden" name="id" id="editBedId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-pencil-square me-1"></i> Edit Details</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Room</label>
                                            <select name="room_id" id="editBedRoom" class="form-select" required>
                                                <option value="">Select Room</option>
                                                <?php foreach ($rooms as $room): ?>
                                                    <option value="<?= (int)$room['id'] ?>">
                                                        <?= htmlspecialchars((string)$room['hostel_name'] . ' - Room ' . (string)$room['room_number']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Bed Number</label>
                                            <input type="text" name="bed_number" id="editBedNumber" class="form-control" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Status</label>
                                            <select name="status" id="editBedStatus" class="form-select">
                                                <option value="active">Active</option>
                                                <option value="maintenance">Maintenance</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-gear me-1"></i> Status Guide</h6>
                                    <p class="small mb-2"><strong>Active:</strong> Available for allocation.</p>
                                    <p class="small mb-2"><strong>Maintenance:</strong> Temporarily unavailable.</p>
                                    <p class="small mb-0"><strong>Inactive:</strong> Disabled from use.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-check-circle me-1"></i> Update Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="viewBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-grid-3x3-gap"></i> Bed Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>Hostel:</strong> <span id="viewBedHostel">-</span></p>
                <p class="mb-2"><strong>Room:</strong> <span id="viewBedRoom">-</span></p>
                <p class="mb-2"><strong>Bed Number:</strong> <span id="viewBedNumber">-</span></p>
                <p class="mb-2"><strong>Status:</strong> <span id="viewBedStatus">-</span></p>
                <p class="mb-2"><strong>Created:</strong> <span id="viewBedCreated">-</span></p>
                <p class="mb-0"><strong>Updated:</strong> <span id="viewBedUpdated">-</span></p>
            </div>
        </div>
    </div>
</div>

<div
    id="manageBedsConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-total-beds="<?= (int)count($beds) ?>">
</div>
<script src="../assets/js/admin-manage-beds.js"></script>
