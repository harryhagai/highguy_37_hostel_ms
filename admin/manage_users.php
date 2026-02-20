<?php
$state = require __DIR__ . '/../controllers/admin/manage_users_controller.php';
$errors = $state['errors'];
$success = $state['success'];
$openModal = $state['openModal'];
$editFormData = $state['editFormData'];
$users = $state['users'];
?>
<div class="container-fluid px-0 users-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Manage Users</h4>
                <p class="text-muted mb-0">Create, update, and review user accounts.</p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle me-1"></i> Add User
            </button>
        </div>

        <div class="users-filters mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm users-search-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input
                            type="search"
                            class="form-control"
                            id="usersSearchInput"
                            placeholder="Search by username, email, or phone">
                        <button class="btn btn-outline-secondary" type="button" id="clearUsersSearch">
                            Clear
                        </button>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <select class="form-select form-select-sm" id="usersRoleFilter">
                        <option value="">All Roles</option>
                        <option value="user">Users Only</option>
                        <option value="admin">Admins Only</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3 text-lg-end">
                    <span class="users-result-count text-muted small" id="usersResultCount">
                        <?= (int)count($users) ?> results
                    </span>
                </div>
            </div>
        </div>

        <div class="table-responsive users-table-wrap">
            <table class="table table-hover align-middle users-table mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $json = htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>
                    <?php
                    $searchText = strtolower(trim((string)($user['id'] ?? '') . ' ' . (string)($user['username'] ?? '') . ' ' . (string)($user['email'] ?? '') . ' ' . (string)($user['phone'] ?? '')));
                    $roleValue = (string)($user['role'] ?? 'user');
                    ?>
                    <tr class="user-row" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>" data-role="<?= htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8') ?>">
                        <td><?= (int)$user['id'] ?></td>
                        <td><?= htmlspecialchars((string)($user['username'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($user['email'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($user['phone'] ?? '-')) ?></td>
                        <td>
                            <span class="badge user-role-badge <?= $roleValue === 'admin' ? 'user-role-admin' : 'user-role-user' ?>">
                                <?= ucfirst($roleValue) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars((string)($user['created_at'] ?? '')) ?></td>
                        <td class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary view-user-btn" data-user="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewUserModal">
                                <i class="bi bi-eye me-1"></i> View
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary edit-user-btn" data-user="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#editUserModal">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <form method="post" data-confirm="Delete this user?" class="d-inline">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr id="usersNoResultsRow" class="<?= empty($users) ? '' : 'd-none' ?>">
                        <td colspan="7" class="text-center text-muted py-4">
                            <?= empty($users) ? 'No users found.' : 'No users match your search.' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
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
                            <input type="text" name="username" class="form-control form-control-sm" required pattern="[A-Za-z0-9_]+">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
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
                            <input type="text" name="username" id="editUserUsername" class="form-control form-control-sm" required pattern="[A-Za-z0-9_]+">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editUserEmail" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="editUserPhone" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select name="role" id="editUserRole" class="form-select form-select-sm">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password (optional)</label>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-vcard"></i> User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>ID:</strong> <span id="viewUserId">-</span></p>
                <p class="mb-2"><strong>Username:</strong> <span id="viewUserUsername">-</span></p>
                <p class="mb-2"><strong>Email:</strong> <span id="viewUserEmail">-</span></p>
                <p class="mb-2"><strong>Phone:</strong> <span id="viewUserPhone">-</span></p>
                <p class="mb-2"><strong>Role:</strong> <span id="viewUserRole">-</span></p>
                <p class="mb-0"><strong>Created:</strong> <span id="viewUserCreated">-</span></p>
            </div>
        </div>
    </div>
</div>

<div
    id="manageUsersConfig"
    data-open-modal="<?= htmlspecialchars($openModal, ENT_QUOTES, 'UTF-8') ?>"
    data-edit-form="<?= htmlspecialchars(json_encode($editFormData), ENT_QUOTES, 'UTF-8') ?>"
    data-total-users="<?= (int)count($users) ?>">
</div>
<script src="../assets/js/admin-manage-users.js"></script>
