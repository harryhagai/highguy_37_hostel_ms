<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/manage_rooms_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$openModal = $state['openModal'];
$editFormData = $state['editFormData'];
$addFormData = $state['addFormData'];
$hostels = $state['hostels'];
$rooms = $state['rooms'];
$stats = $state['stats'];
$roomTypeOptions = $state['roomTypeOptions'];
$supportsRoomImages = !empty($state['supportsRoomImages']);
$roomImages = $state['roomImages'];
?>
<div class="container-fluid px-0 users-page rooms-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Rooms</p>
            <h5 class="mb-0"><?= (int)$stats['total_rooms'] ?></h5>
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
            <p class="users-mini-label mb-1">Avg Price</p>
            <h5 class="mb-0">TZS <?= number_format((float)$stats['avg_price'], 2) ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Room Types</p>
            <h5 class="mb-0"><?= (int)$stats['room_types'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Rooms</h4>
                <p class="text-muted mb-0">Create, filter, and manage rooms faster.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Room
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
                            id="roomsSearchInput"
                            placeholder="Search rooms">
                        <button type="button" id="clearRoomsSearch" class="users-field-clear" aria-label="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-buildings"></i>
                        <select class="form-select form-select-sm" id="roomsHostelFilter">
                            <option value="">All Hostels</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?= (int)$hostel['id'] ?>"><?= htmlspecialchars((string)$hostel['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-door-open"></i>
                        <select class="form-select form-select-sm" id="roomsTypeFilter">
                            <option value="">All Types</option>
                            <?php foreach ($roomTypeOptions as $type): ?>
                                <option value="<?= htmlspecialchars(strtolower((string)$type), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-cash-coin"></i>
                        <select class="form-select form-select-sm" id="roomsPriceFilter">
                            <option value="">All Prices</option>
                            <option value="free">Free (0)</option>
                            <option value="paid">Paid (&gt; 0)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-2 align-items-center">
                <div class="col-xl-7">
                    <form method="post" id="bulkRoomsForm" class="d-flex flex-wrap gap-2 align-items-center" data-confirm="Apply selected bulk action?">
                        <input type="hidden" name="action" value="bulk_rooms">
                        <div id="bulkRoomSelectedInputs"></div>
                        <select name="bulk_action_type" id="bulkRoomActionType" class="form-select form-select-sm users-bulk-select">
                            <option value="">Bulk Action</option>
                            <option value="delete_selected">Delete Selected</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkRoomApplyBtn">
                            <i class="bi bi-lightning-charge me-1"></i> Apply
                        </button>
                        <span class="small text-muted" id="bulkRoomSelectedCount">0 selected</span>
                    </form>
                </div>
                <div class="col-xl-5 d-flex flex-wrap gap-2 justify-content-xl-end align-items-center">
                    <span class="users-result-count text-muted small" id="roomsResultCount">
                        <?= (int)count($rooms) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0" id="roomsTable">
                <thead>
                    <tr>
                        <th class="users-check-col">
                            <input type="checkbox" class="form-check-input" id="selectAllRooms" aria-label="Select all rooms">
                        </th>
                        <th>Room</th>
                        <th>Hostel</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $roomId = (int)$room['id'];
                    $json = htmlspecialchars(json_encode($room), ENT_QUOTES, 'UTF-8');
                    $roomTypeKey = strtolower(trim((string)($room['room_type_key'] ?? '')));
                    $priceTier = (string)($room['price_tier'] ?? (((float)($room['price'] ?? 0) > 0) ? 'paid' : 'free'));
                    $description = trim((string)($room['description'] ?? ''));
                    $descPreview = $description;
                    if (strlen($descPreview) > 70) {
                        $descPreview = substr($descPreview, 0, 70) . '...';
                    }
                    $roomImagePath = trim((string)($room['room_image_path'] ?? ''));
                    $roomImageUrl = $roomImagePath !== '' ? '../' . ltrim($roomImagePath, '/') : '';
                    $searchText = strtolower(trim(
                        (string)($room['id'] ?? '') . ' ' .
                        (string)($room['hostel_name'] ?? '') . ' ' .
                        (string)($room['room_number'] ?? '') . ' ' .
                        (string)($room['room_type'] ?? '') . ' ' .
                        (string)($room['description'] ?? '') . ' ' .
                        (string)($room['price_display'] ?? '')
                    ));
                    ?>
                    <tr
                        class="room-row"
                        data-id="<?= $roomId ?>"
                        data-room="<?= $json ?>"
                        data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                        data-hostel-id="<?= (int)($room['hostel_id'] ?? 0) ?>"
                        data-room-type="<?= htmlspecialchars($roomTypeKey, ENT_QUOTES, 'UTF-8') ?>"
                        data-price-tier="<?= htmlspecialchars($priceTier, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td>
                            <input type="checkbox" class="form-check-input room-select" value="<?= $roomId ?>" aria-label="Select room <?= $roomId ?>">
                        </td>
                        <td>
                            <div class="user-identity">
                                <div class="user-avatar-shell">
                                    <?php if ($roomImageUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($roomImageUrl) ?>" alt="Room image" class="user-avatar-img" loading="lazy">
                                    <?php else: ?>
                                        <span class="user-avatar-fallback"><i class="bi bi-door-open"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-semibold">Room <?= htmlspecialchars((string)($room['room_number'] ?? '-')) ?></div>
                                    <small class="text-muted" title="<?= htmlspecialchars($description !== '' ? $description : 'No description') ?>">
                                        <?= htmlspecialchars($description !== '' ? $descPreview : 'No description') ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)($room['hostel_name'] ?? '-')) ?></td>
                        <td>
                            <span class="badge user-role-badge user-role-user">
                                <?= htmlspecialchars((string)($room['room_type'] ?? '-')) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge user-status-badge <?= $priceTier === 'paid' ? 'user-status-active' : 'user-status-inactive' ?>">
                                TZS <?= htmlspecialchars((string)($room['price_display'] ?? '0.00')) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string)($room['created_at_display'] ?? '-')) ?></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary view-room-btn"
                                data-room="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#viewRoomModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="View room details"
                            >
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary edit-room-btn"
                                data-room="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#editRoomModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="Edit room"
                            >
                                <i class="bi bi-pencil me-1"></i>Update
                            </button>
                            <form method="post" data-confirm="Delete this room?" class="d-inline">
                                <input type="hidden" name="action" value="delete_room">
                                <input type="hidden" name="id" value="<?= $roomId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle-tooltip="tooltip" title="Delete room">
                                    <i class="bi bi-trash me-1"></i>Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="roomsNoResultsRow" class="<?= empty($rooms) ? '' : 'd-none' ?>">
                        <td colspan="7" class="text-center text-muted py-4">
                            <?= empty($rooms) ? 'No rooms found.' : 'No rooms match your filters.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="users-lazy-bar mt-3">
            <span class="small text-muted" id="roomsLoadedInfo">Showing 0 of 0</span>
            <span class="small text-muted">Scroll down to load more</span>
        </div>
        <div id="roomsLazySentinel" class="users-lazy-sentinel" aria-hidden="true"></div>
    </div>
</div>

<div class="modal fade users-modal" id="addRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="add_room">
                <input type="hidden" name="room_image_id" id="addRoomImageId" value="<?= (int)($addFormData['room_image_id'] ?? 0) ?>">
                <input type="hidden" name="room_image_label" id="addRoomImageLabel" value="<?= htmlspecialchars((string)($addFormData['room_image_label'] ?? '')) ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-door-open"></i> Add Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-card-text me-1"></i> Room Details</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Hostel</label>
                                            <select name="hostel_id" class="form-select" required>
                                                <option value="">Select Hostel</option>
                                                <?php foreach ($hostels as $hostel): ?>
                                                    <option value="<?= (int)$hostel['id'] ?>" <?= ((int)($addFormData['hostel_id'] ?? 0) === (int)$hostel['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars((string)$hostel['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Room Number</label>
                                            <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars((string)($addFormData['room_number'] ?? '')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Room Type</label>
                                            <input type="text" name="room_type" class="form-control" required value="<?= htmlspecialchars((string)($addFormData['room_type'] ?? '')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Price (TZS)</label>
                                            <input type="number" step="0.01" min="0" name="price" class="form-control" required value="<?= htmlspecialchars((string)($addFormData['price'] ?? '0.00')) ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description (optional)</label>
                                            <textarea name="description" class="form-control" rows="4" placeholder="Add room description..."><?= htmlspecialchars((string)($addFormData['description'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-image me-1"></i> Room Image</h6>
                                    <label class="form-label">Upload Image</label>
                                    <input type="file" name="room_image" id="addRoomImageUpload" class="form-control" accept="image/*">
                                    <div class="form-text mt-2">All image formats are accepted.</div>
                                    <img id="addRoomImagePreview" src="" alt="Selected image preview" class="mt-3 rounded room-preview-md" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check-circle me-1"></i> Save Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="editRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" autocomplete="off" id="editRoomForm">
                <input type="hidden" name="action" value="update_room">
                <input type="hidden" name="id" id="editRoomId">
                <input type="hidden" name="room_image_id" id="editRoomImageId">
                <input type="hidden" name="room_image_label" id="editRoomImageLabel">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Room</h5>
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
                                            <label class="form-label">Hostel</label>
                                            <select name="hostel_id" id="editRoomHostel" class="form-select" required>
                                                <option value="">Select Hostel</option>
                                                <?php foreach ($hostels as $hostel): ?>
                                                    <option value="<?= (int)$hostel['id'] ?>"><?= htmlspecialchars((string)$hostel['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Room Number</label>
                                            <input type="text" name="room_number" id="editRoomNumber" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Room Type</label>
                                            <input type="text" name="room_type" id="editRoomType" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Price (TZS)</label>
                                            <input type="number" step="0.01" min="0" name="price" id="editRoomPrice" class="form-control" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description (optional)</label>
                                            <textarea name="description" id="editRoomDescription" class="form-control" rows="4" placeholder="Add room description..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-image-alt me-1"></i> Replace Image</h6>
                                    <label class="form-label">New Image (Optional)</label>
                                    <input type="file" name="room_image" id="editRoomImageUpload" class="form-control" accept="image/*">
                                    <div class="form-text mt-2">Leave empty to keep the current image.</div>
                                    <img id="editRoomImagePreview" src="" alt="Current image" class="mt-3 rounded room-preview-md" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-check-circle me-1"></i> Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="viewRoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable rooms-view-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-door-open"></i> Room Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <p class="mb-2"><strong>Hostel:</strong> <span id="viewRoomHostel">-</span></p>
                        <p class="mb-2"><strong>Room Number:</strong> <span id="viewRoomNumber">-</span></p>
                        <p class="mb-2"><strong>Room Type:</strong> <span id="viewRoomType">-</span></p>
                        <p class="mb-2"><strong>Price:</strong> <span id="viewRoomPrice">-</span></p>
                        <p class="mb-2"><strong>Description:</strong> <span id="viewRoomDescriptionText">-</span></p>
                        <p class="mb-2"><strong>Created:</strong> <span id="viewRoomCreated">-</span></p>
                        <p class="mb-2"><strong>Updated:</strong> <span id="viewRoomUpdated">-</span></p>
                    </div>
                    <div class="col-md-5">
                        <img id="viewRoomImage" src="" alt="Room image" class="img-fluid rounded room-view-lg mb-3">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div
    id="manageRoomsConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-total-rooms="<?= (int)count($rooms) ?>">
</div>
<script src="../assets/js/admin-manage-rooms.js"></script>
