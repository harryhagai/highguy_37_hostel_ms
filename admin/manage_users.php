<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/manage_users_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$openModal = $state['openModal'];
$editFormData = $state['editFormData'];
$users = $state['users'];
$stats = $state['stats'];
$supports = $state['supports'];
?>
<div class="container-fluid px-0 users-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Users</p>
            <h5 class="mb-0"><?= (int)$stats['total_users'] ?></h5>
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
            <p class="users-mini-label mb-1">Active Now</p>
            <h5 class="mb-0"><?= (int)$stats['active_now'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Suspended</p>
            <h5 class="mb-0"><?= (int)$stats['suspended_users'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Users</h4>
                <p class="text-muted mb-0">Create, filter, and manage users faster.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-circle me-1"></i> Add User
                </button>
            </div>
        </div>

        <div class="users-filters mb-3">
            <div class="row g-2 align-items-center mb-2">
                <div class="col-xl-4 col-lg-6">
                    <div class="input-group input-group-sm users-search-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input
                            type="search"
                            class="form-control"
                            id="usersSearchInput"
                            placeholder="Live search name, email, phone, status">
                        <button class="btn btn-outline-secondary" type="button" id="clearUsersSearch">
                            Clear
                        </button>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <select class="form-select form-select-sm" id="usersRoleFilter">
                        <option value="">All Roles</option>
                        <option value="user">Users Only</option>
                        <option value="admin">Admins Only</option>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <select class="form-select form-select-sm" id="usersStatusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <input type="date" id="usersDateFrom" class="form-control form-control-sm" title="Joined from date">
                </div>
                <div class="col-xl-2 col-lg-3 col-sm-6">
                    <input type="date" id="usersDateTo" class="form-control form-control-sm" title="Joined to date">
                </div>
            </div>

            <div class="row g-2 align-items-center">
                <div class="col-xl-7">
                    <form method="post" id="bulkUsersForm" class="d-flex flex-wrap gap-2 align-items-center" data-confirm="Apply selected bulk action?">
                        <input type="hidden" name="action" value="bulk_users">
                        <div id="bulkSelectedInputs"></div>
                        <select name="bulk_action_type" id="bulkActionType" class="form-select form-select-sm users-bulk-select">
                            <option value="">Bulk Action</option>
                            <option value="make_admin">Set Role: Admin</option>
                            <option value="make_user">Set Role: User</option>
                            <option value="set_active">Set Status: Active</option>
                            <option value="set_inactive">Set Status: Inactive</option>
                            <option value="set_suspended">Set Status: Suspended</option>
                            <option value="send_reset">Send Password Reset</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkApplyBtn">
                            <i class="bi bi-lightning-charge me-1"></i> Apply
                        </button>
                        <span class="small text-muted" id="bulkSelectedCount">0 selected</span>
                    </form>
                </div>
                <div class="col-xl-5 d-flex flex-wrap gap-2 justify-content-xl-end align-items-center">
                    <span class="users-result-count text-muted small" id="usersResultCount">
                        <?= (int)count($users) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th class="users-check-col">
                            <input type="checkbox" class="form-check-input" id="selectAllUsers" aria-label="Select all users">
                        </th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                    $userId = (int)$user['id'];
                    $json = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8');
                    $searchText = strtolower(trim(
                        (string)($user['id'] ?? '') . ' ' .
                        (string)($user['username'] ?? '') . ' ' .
                        (string)($user['email'] ?? '') . ' ' .
                        (string)($user['phone'] ?? '') . ' ' .
                        (string)($user['role'] ?? '') . ' ' .
                        (string)($user['status'] ?? '')
                    ));
                    $roleValue = (string)($user['role'] ?? 'user');
                    $statusValue = (string)($user['status'] ?? 'inactive');
                    $createdDate = '';
                    if (!empty($user['created_at'])) {
                        $createdDate = date('Y-m-d', strtotime((string)$user['created_at']));
                    }
                    $hasCustomAvatar = !empty($user['avatar_url']) && (string)$user['avatar_url'] !== '../assets/images/prof.jpg';
                    ?>
                    <tr
                        class="user-row"
                        data-id="<?= $userId ?>"
                        data-user="<?= $json ?>"
                        data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                        data-role="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>"
                        data-status="<?= htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8') ?>"
                        data-created="<?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <td>
                            <input type="checkbox" class="form-check-input user-select" value="<?= $userId ?>" aria-label="Select user <?= $userId ?>">
                        </td>
                        <td>
                            <div class="user-identity">
                                <div class="user-avatar-shell">
                                    <?php if ($hasCustomAvatar): ?>
                                        <img src="<?= htmlspecialchars((string)$user['avatar_url']) ?>" alt="Avatar" class="user-avatar-img" loading="lazy">
                                    <?php else: ?>
                                        <span class="user-avatar-fallback"><?= htmlspecialchars((string)$user['avatar_initials']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars((string)($user['username'] ?? '')) ?></div>
                                    <small class="text-muted">Phone: <?= htmlspecialchars((string)($user['phone'] ?? '-')) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)($user['email'] ?? '')) ?></td>
                        <td>
                            <span class="badge user-role-badge <?= $roleValue === 'admin' ? 'user-role-admin' : 'user-role-user' ?>">
                                <?= ucfirst($roleValue) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge user-status-badge user-status-<?= htmlspecialchars($statusValue) ?>">
                                <?= ucfirst($statusValue) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string)($user['created_at_display'] ?? '-')) ?></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary view-user-btn"
                                data-user="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#viewUserModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="View user details"
                            >
                                <i class="bi bi-eye"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary edit-user-btn"
                                data-user="<?= $json ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#editUserModal"
                                data-bs-toggle-tooltip="tooltip"
                                title="Edit user"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" data-confirm="Send password reset for this user?" class="d-inline">
                                <input type="hidden" name="action" value="send_password_reset">
                                <input type="hidden" name="id" value="<?= $userId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning" data-bs-toggle-tooltip="tooltip" title="Send password reset email">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            <form method="post" data-confirm="Disable this user account?" class="d-inline">
                                <input type="hidden" name="action" value="disable_user">
                                <input type="hidden" name="id" value="<?= $userId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle-tooltip="tooltip" title="Disable user account">
                                    <i class="bi bi-person-x"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="usersNoResultsRow" class="<?= empty($users) ? '' : 'd-none' ?>">
                        <td colspan="7" class="text-center text-muted py-4">
                            <?= empty($users) ? 'No users found.' : 'No users match your filters.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="users-lazy-bar mt-3">
            <span class="small text-muted" id="usersLoadedInfo">Showing 0 of 0</span>
            <span class="small text-muted">Scroll down to load more</span>
        </div>
        <div id="usersLazySentinel" class="users-lazy-sentinel" aria-hidden="true"></div>
    </div>
</div>

<div class="modal fade users-modal" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control form-control-sm" required pattern="[A-Za-z0-9_]+( [A-Za-z0-9_]+)*" title="Letters, numbers, underscores, and spaces between words are allowed">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm" <?= !empty($supports['status']) ? '' : 'disabled' ?>>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" required minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check-circle me-1"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" autocomplete="off" id="editUserForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="editUserUsername" class="form-control form-control-sm" required pattern="[A-Za-z0-9_]+( [A-Za-z0-9_]+)*" title="Letters, numbers, underscores, and spaces between words are allowed">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editUserEmail" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="editUserPhone" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="editUserRole" class="form-select form-select-sm">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editUserStatus" class="form-select form-select-sm" <?= !empty($supports['status']) ? '' : 'disabled' ?>>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-check-circle me-1"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-vcard"></i> User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Username:</strong> <span id="viewUserUsername">-</span></p>
                        <p class="mb-2"><strong>Email:</strong> <span id="viewUserEmail">-</span></p>
                        <p class="mb-2"><strong>Phone:</strong> <span id="viewUserPhone">-</span></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Role:</strong> <span id="viewUserRole">-</span></p>
                        <p class="mb-2"><strong>Status:</strong> <span id="viewUserStatus">-</span></p>
                        <p class="mb-2"><strong>Last Login:</strong> <span id="viewUserLastLogin">-</span></p>
                        <p class="mb-2"><strong>Joined:</strong> <span id="viewUserCreated">-</span></p>
                    </div>
                    <div class="col-12">
                        <h6 class="mb-2">Recent Activity</h6>
                        <ul class="mb-0 users-activity-list" id="viewUserActivityList">
                            <li class="text-muted">No activity yet.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div
    id="manageUsersConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-total-users="<?= (int)count($users) ?>"
    data-support-status="<?= !empty($supports['status']) ? '1' : '0' ?>">
</div>
<script src="../assets/js/admin-manage-users.js"></script>
