<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/notice_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$openModal = $state['openModal'];
$addFormData = is_array($state['addFormData'] ?? null) ? $state['addFormData'] : [];
$editFormData = $state['editFormData'];
$notices = $state['notices'];
$supportsTargeting = !empty($state['supportsTargeting']);
$hostelOptions = $state['hostelOptions'] ?? [];
$roomOptions = $state['roomOptions'] ?? [];
$bedOptions = $state['bedOptions'] ?? [];

$stats = [
    'total_notices' => count($notices),
    'new_today' => 0,
    'new_week' => 0,
    'new_month' => 0,
    'long_notices' => 0,
];

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));
$monthAgo = date('Y-m-d', strtotime('-29 days'));

foreach ($notices as $noticeStat) {
    $createdAt = (string)($noticeStat['created_at'] ?? '');
    $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';

    if ($createdDate === $today) {
        $stats['new_today']++;
    }
    if ($createdDate !== '' && $createdDate >= $weekAgo && $createdDate <= $today) {
        $stats['new_week']++;
    }
    if ($createdDate !== '' && $createdDate >= $monthAgo && $createdDate <= $today) {
        $stats['new_month']++;
    }

    if (strlen(trim((string)($noticeStat['content'] ?? ''))) >= 200) {
        $stats['long_notices']++;
    }
}

$addNoticeTitle = trim((string)($addFormData['title'] ?? ''));
$addNoticeContent = trim((string)($addFormData['content'] ?? ''));
$addNoticeTargetScope = strtolower(trim((string)($addFormData['target_scope'] ?? 'public')));
if (!in_array($addNoticeTargetScope, ['public', 'hostel', 'room', 'bed'], true)) {
    $addNoticeTargetScope = 'public';
}
$addNoticeHostelId = (int)($addFormData['hostel_id'] ?? 0);
$addNoticeRoomId = (int)($addFormData['room_id'] ?? 0);
$addNoticeBedId = (int)($addFormData['bed_id'] ?? 0);
?>
<div class="container-fluid px-0 users-page notices-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Notices</p>
            <h5 class="mb-0"><?= (int)$stats['total_notices'] ?></h5>
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
            <p class="users-mini-label mb-1">New This Month</p>
            <h5 class="mb-0"><?= (int)$stats['new_month'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Long Notices</p>
            <h5 class="mb-0"><?= (int)$stats['long_notices'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Notices</h4>
                <p class="text-muted mb-0">Create, filter, and manage announcements faster.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Notice
                </button>
            </div>
        </div>

        <div class="users-filters mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-xl-4 col-lg-6 col-sm-6">
                    <div class="users-field-icon users-field-search">
                        <i class="bi bi-search"></i>
                        <input
                            type="search"
                            class="form-control form-control-sm"
                            id="noticesSearchInput"
                            placeholder="Search notices">
                        <button type="button" id="clearNoticesSearch" class="users-field-clear" aria-label="Clear search">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-sm-6">
                    <div class="users-field-icon">
                        <i class="bi bi-calendar3"></i>
                        <select class="form-select form-select-sm" id="noticesDateFilter">
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">Last 7 Days</option>
                            <option value="month">Last 30 Days</option>
                            <option value="older">Older</option>
                        </select>
                    </div>
                </div>
                <div class="col-xl-5 d-flex flex-wrap gap-2 justify-content-xl-end align-items-center">
                    <span class="users-result-count text-muted small" id="noticesResultCount">
                        <?= (int)count($notices) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0" id="noticesTable">
                <thead>
                    <tr>
                        <th>Notice</th>
                        <th>Target</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($notices as $notice): ?>
                    <?php
                    $noticeId = (int)$notice['id'];
                    $title = trim((string)($notice['title'] ?? ''));
                    $content = trim((string)($notice['content'] ?? ''));
                    $createdAt = (string)($notice['created_at'] ?? '');
                    $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';
                    $createdDisplay = $createdAt !== '' ? date('d M Y H:i', strtotime($createdAt)) : '-';
                    $targetScope = strtolower(trim((string)($notice['target_scope'] ?? 'public')));
                    if (!in_array($targetScope, ['public', 'hostel', 'room', 'bed'], true)) {
                        $targetScope = 'public';
                    }
                    $targetLabel = trim((string)($notice['target_label'] ?? 'Public (All Users)'));
                    if ($targetLabel === '') {
                        $targetLabel = 'Public (All Users)';
                    }
                    $targetBadgeClass = $targetScope === 'public' ? 'user-role-admin' : 'user-role-user';
                    $preview = $content;
                    if (strlen($preview) > 120) {
                        $preview = substr($preview, 0, 120) . '...';
                    }
                    $noticeView = [
                        'id' => $noticeId,
                        'title' => $title,
                        'content' => $content,
                        'target_scope' => $targetScope,
                        'target_label' => $targetLabel,
                        'hostel_id' => (int)($notice['hostel_id'] ?? 0),
                        'room_id' => (int)($notice['room_id'] ?? 0),
                        'bed_id' => (int)($notice['bed_id'] ?? 0),
                        'created_at' => $createdAt,
                        'created_at_display' => $createdDisplay,
                    ];
                    $json = htmlspecialchars(json_encode($noticeView), ENT_QUOTES, 'UTF-8');
                    $searchText = strtolower(trim($title . ' ' . $content . ' ' . $createdDisplay . ' ' . $targetScope . ' ' . $targetLabel));
                    ?>
                    <tr
                        class="notice-row"
                        data-notice="<?= $json ?>"
                        data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                        data-created-date="<?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td>
                            <div class="user-identity">
                                <div class="user-avatar-shell">
                                    <span class="user-avatar-fallback"><i class="bi bi-megaphone"></i></span>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($title !== '' ? $title : 'Untitled Notice') ?></div>
                                    <small class="text-muted notice-preview" title="<?= htmlspecialchars($content !== '' ? $content : 'No content') ?>">
                                        <?= htmlspecialchars($content !== '' ? $preview : 'No content') ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge user-role-badge <?= $targetBadgeClass ?>">
                                <?= htmlspecialchars($targetLabel) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge user-status-badge user-status-active">
                                <?= htmlspecialchars($createdDisplay) ?>
                            </span>
                        </td>
                        <td class="d-flex gap-1 flex-nowrap actions-cell notice-actions-cell">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary view-notice-btn"
                                data-notice="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#viewNoticeModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="View notice"
                            >
                                <i class="bi bi-eye me-1"></i>View
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary edit-notice-btn"
                                data-notice="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#editNoticeModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="Edit notice"
                            >
                                <i class="bi bi-pencil me-1"></i>Update
                            </button>
                            <form method="post" data-confirm="Delete this notice?" class="d-inline">
                                <input type="hidden" name="action" value="delete_notice">
                                <input type="hidden" name="id" value="<?= $noticeId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle-tooltip="tooltip" title="Delete notice">
                                    <i class="bi bi-trash me-1"></i>Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="noticesNoResultsRow" class="<?= empty($notices) ? '' : 'd-none' ?>">
                        <td colspan="4" class="text-center text-muted py-4">
                            <?= empty($notices) ? 'No notices found.' : 'No notices match your filters.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="addNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_notice">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-megaphone"></i> Add Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-card-text me-1"></i> Notice Details</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($addNoticeTitle) ?>">
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Content</label>
                                        <textarea name="content" class="form-control" rows="6" required><?= htmlspecialchars($addNoticeContent) ?></textarea>
                                    </div>
                                    <?php if ($supportsTargeting): ?>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label">Target Scope</label>
                                                <select name="target_scope" id="addNoticeScope" class="form-select">
                                                    <option value="public" <?= $addNoticeTargetScope === 'public' ? 'selected' : '' ?>>Public (All Users)</option>
                                                    <option value="hostel" <?= $addNoticeTargetScope === 'hostel' ? 'selected' : '' ?>>Specific Hostel</option>
                                                    <option value="room" <?= $addNoticeTargetScope === 'room' ? 'selected' : '' ?>>Specific Room</option>
                                                    <option value="bed" <?= $addNoticeTargetScope === 'bed' ? 'selected' : '' ?>>Specific Bed</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-none" id="addNoticeHostelWrap">
                                                <label class="form-label">Hostel</label>
                                                <select name="hostel_id" id="addNoticeHostel" class="form-select" data-placeholder="Select Hostel">
                                                    <option value="">Select Hostel</option>
                                                    <?php foreach ($hostelOptions as $hostel): ?>
                                                        <option value="<?= (int)($hostel['id'] ?? 0) ?>" <?= $addNoticeHostelId === (int)($hostel['id'] ?? 0) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars((string)($hostel['name'] ?? '-')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-none" id="addNoticeRoomWrap">
                                                <label class="form-label">Room</label>
                                                <select name="room_id" id="addNoticeRoom" class="form-select" data-placeholder="Select Room">
                                                    <option value="">Select Room</option>
                                                    <?php foreach ($roomOptions as $room): ?>
                                                        <option
                                                            value="<?= (int)($room['id'] ?? 0) ?>"
                                                            data-hostel-id="<?= (int)($room['hostel_id'] ?? 0) ?>"
                                                            <?= $addNoticeRoomId === (int)($room['id'] ?? 0) ? 'selected' : '' ?>
                                                        >
                                                            <?= htmlspecialchars((string)($room['hostel_name'] ?? '-') . ' - Room ' . (string)($room['room_number'] ?? '-')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-none" id="addNoticeBedWrap">
                                                <label class="form-label">Bed</label>
                                                <select name="bed_id" id="addNoticeBed" class="form-select" data-placeholder="Select Bed">
                                                    <option value="">Select Bed</option>
                                                    <?php foreach ($bedOptions as $bed): ?>
                                                        <option
                                                            value="<?= (int)($bed['id'] ?? 0) ?>"
                                                            data-hostel-id="<?= (int)($bed['hostel_id'] ?? 0) ?>"
                                                            data-room-id="<?= (int)($bed['room_id'] ?? 0) ?>"
                                                            <?= $addNoticeBedId === (int)($bed['id'] ?? 0) ? 'selected' : '' ?>
                                                        >
                                                            <?= htmlspecialchars((string)($bed['hostel_name'] ?? '-') . ' - Room ' . (string)($bed['room_number'] ?? '-') . ' - Bed ' . (string)($bed['bed_number'] ?? '-')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mt-3 mb-0 small">
                                            Notice targeting columns are not in the database yet. Run the SQL migration, then refresh this page.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100 d-flex flex-column">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-lightbulb me-1"></i> Tips</h6>
                                    <ul class="mb-0 text-muted small flex-grow-1">
                                        <li>Use a clear and short title.</li>
                                        <li>Keep the message specific and actionable.</li>
                                        <li>Mention any important date or deadline.</li>
                                    </ul>
                                    <div class="mt-4 d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check-circle me-1"></i> Save Notice</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="editNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="update_notice">
                <input type="hidden" name="id" id="editNoticeId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid px-0">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-pencil-square me-1"></i> Edit Details</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" id="editNoticeTitle" class="form-control" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">Content</label>
                                        <textarea name="content" id="editNoticeContent" class="form-control" rows="6" required></textarea>
                                    </div>
                                    <?php if ($supportsTargeting): ?>
                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label">Target Scope</label>
                                                <select name="target_scope" id="editNoticeScope" class="form-select">
                                                    <option value="public">Public (All Users)</option>
                                                    <option value="hostel">Specific Hostel</option>
                                                    <option value="room">Specific Room</option>
                                                    <option value="bed">Specific Bed</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-none" id="editNoticeHostelWrap">
                                                <label class="form-label">Hostel</label>
                                                <select name="hostel_id" id="editNoticeHostel" class="form-select" data-placeholder="Select Hostel">
                                                    <option value="">Select Hostel</option>
                                                    <?php foreach ($hostelOptions as $hostel): ?>
                                                        <option value="<?= (int)($hostel['id'] ?? 0) ?>">
                                                            <?= htmlspecialchars((string)($hostel['name'] ?? '-')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-none" id="editNoticeRoomWrap">
                                                <label class="form-label">Room</label>
                                                <select name="room_id" id="editNoticeRoom" class="form-select" data-placeholder="Select Room">
                                                    <option value="">Select Room</option>
                                                    <?php foreach ($roomOptions as $room): ?>
                                                        <option
                                                            value="<?= (int)($room['id'] ?? 0) ?>"
                                                            data-hostel-id="<?= (int)($room['hostel_id'] ?? 0) ?>"
                                                        >
                                                            <?= htmlspecialchars((string)($room['hostel_name'] ?? '-') . ' - Room ' . (string)($room['room_number'] ?? '-')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 d-none" id="editNoticeBedWrap">
                                                <label class="form-label">Bed</label>
                                                <select name="bed_id" id="editNoticeBed" class="form-select" data-placeholder="Select Bed">
                                                    <option value="">Select Bed</option>
                                                    <?php foreach ($bedOptions as $bed): ?>
                                                        <option
                                                            value="<?= (int)($bed['id'] ?? 0) ?>"
                                                            data-hostel-id="<?= (int)($bed['hostel_id'] ?? 0) ?>"
                                                            data-room-id="<?= (int)($bed['room_id'] ?? 0) ?>"
                                                        >
                                                            <?= htmlspecialchars((string)($bed['hostel_name'] ?? '-') . ' - Room ' . (string)($bed['room_number'] ?? '-') . ' - Bed ' . (string)($bed['bed_number'] ?? '-')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100 d-flex flex-column">
                                    <h6 class="mb-3 fw-semibold"><i class="bi bi-info-circle me-1"></i> Guidance</h6>
                                    <p class="text-muted small mb-0 flex-grow-1">
                                        Keep the most important details in the first two lines so users can read them quickly in the table preview.
                                    </p>
                                    <div class="mt-4 d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-outline-success"><i class="bi bi-check-circle me-1"></i> Update Notice</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="viewNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-card-text"></i> Notice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <p class="mb-2"><strong>ID:</strong> <span id="viewNoticeId">-</span></p>
                        <p class="mb-2"><strong>Title:</strong> <span id="viewNoticeTitle">-</span></p>
                        <p class="mb-2"><strong>Target:</strong> <span id="viewNoticeTarget">-</span></p>
                        <p class="mb-0"><strong>Created:</strong> <span id="viewNoticeCreated">-</span></p>
                    </div>
                    <div class="col-md-8">
                        <strong>Content:</strong>
                        <div class="mt-2 p-3 border rounded-3 bg-light notice-content-box">
                            <span id="viewNoticeContent">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div
    id="manageNoticeConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-total-notices="<?= (int)count($notices) ?>">
</div>
<script src="../assets/js/admin-notice.js"></script>
