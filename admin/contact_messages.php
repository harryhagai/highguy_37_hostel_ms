<?php
require_once __DIR__ . '/../permission/role_permission.php';
rp_require_roles(['admin'], '../auth/login.php');

$state = require __DIR__ . '/../controllers/admin/contact_messages_controller.php';
$errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
$success = (string)($state['success'] ?? '');
$tableReady = (bool)($state['table_ready'] ?? false);
$messages = is_array($state['messages'] ?? null) ? $state['messages'] : [];
$stats = is_array($state['stats'] ?? null) ? $state['stats'] : [
    'total' => 0,
    'new' => 0,
    'read' => 0,
    'replied' => 0,
    'resolved' => 0,
    'today' => 0,
];
$allowedStatuses = is_array($state['allowed_statuses'] ?? null) ? $state['allowed_statuses'] : ['new', 'read', 'replied', 'resolved'];

$statusLabel = static function (string $status): string {
    return ucwords(str_replace('_', ' ', $status));
};

$statusBadgeClass = static function (string $status): string {
    if ($status === 'resolved') {
        return 'text-bg-success';
    }
    if ($status === 'replied') {
        return 'text-bg-primary';
    }
    if ($status === 'read') {
        return 'text-bg-info';
    }
    return 'text-bg-warning';
};
?>

<div class="container-fluid px-0 users-page contact-messages-page">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="users-quick-stats mb-3">
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Total Messages</p>
            <h5 class="mb-0"><?= (int)$stats['total'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">New</p>
            <h5 class="mb-0"><?= (int)$stats['new'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Read</p>
            <h5 class="mb-0"><?= (int)$stats['read'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Replied</p>
            <h5 class="mb-0"><?= (int)$stats['replied'] ?></h5>
        </article>
        <article class="users-mini-stat">
            <p class="users-mini-label mb-1">Today</p>
            <h5 class="mb-0"><?= (int)$stats['today'] ?></h5>
        </article>
    </div>

    <div class="dashboard-card users-shell mb-4">
        <div class="users-toolbar mb-3">
            <div>
                <h4 class="mb-1">Contact Messages</h4>
                <p class="text-muted mb-0">Read incoming messages from public contact form and manage their status.</p>
            </div>
        </div>

        <?php if (!$tableReady): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Contact messages table is not ready.
            </div>
        <?php else: ?>
            <div class="users-filters mb-3">
                <div class="row g-2 align-items-center">
                    <div class="col-xl-5 col-lg-6">
                        <div class="users-field-icon users-field-search">
                            <i class="bi bi-search"></i>
                            <input type="search" class="form-control form-control-sm" id="contactMessagesSearchInput" placeholder="Search name, email, topic, message">
                            <button type="button" id="clearContactMessagesSearch" class="users-field-clear" aria-label="Clear search">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-sm-6">
                        <div class="users-field-icon">
                            <i class="bi bi-funnel"></i>
                            <select class="form-select form-select-sm" id="contactMessagesStatusFilter">
                                <option value="">All Status</option>
                                <?php foreach ($allowedStatuses as $statusOption): ?>
                                    <option value="<?= htmlspecialchars((string)$statusOption, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($statusLabel((string)$statusOption), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-2 d-flex justify-content-lg-end align-items-center">
                        <span class="users-result-count text-muted small" id="contactMessagesResultCount"><?= (int)count($messages) ?> results</span>
                    </div>
                </div>
            </div>

            <div class="table-responsive users-table-wrap">
                <table class="table table-hover align-middle users-table mb-0" id="contactMessagesTable">
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Topic & Message</th>
                            <th>Received</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $messageId = (int)($message['id'] ?? 0);
                        $messageStatus = (string)($message['status'] ?? 'new');
                        $senderName = trim((string)($message['full_name'] ?? ''));
                        $senderEmail = trim((string)($message['email'] ?? ''));
                        $senderPhone = trim((string)($message['phone'] ?? ''));
                        $messageTopic = trim((string)($message['topic'] ?? ''));
                        $messageText = trim((string)($message['message'] ?? ''));
                        $messagePreview = trim((string)($message['message_preview'] ?? ''));

                        if ($senderName === '') {
                            $senderName = 'Unknown Sender';
                        }
                        if ($messageTopic === '') {
                            $messageTopic = 'General Inquiry';
                        }

                        $payload = [
                            'id' => $messageId,
                            'full_name' => $senderName,
                            'email' => $senderEmail,
                            'phone' => $senderPhone,
                            'topic' => $messageTopic,
                            'message' => $messageText,
                            'status' => $messageStatus,
                            'status_label' => (string)($message['status_label'] ?? $statusLabel($messageStatus)),
                            'created_at_display' => (string)($message['created_at_display'] ?? '-'),
                            'updated_at_display' => (string)($message['updated_at_display'] ?? '-'),
                            'ip_address' => (string)($message['ip_address'] ?? ''),
                            'user_agent' => (string)($message['user_agent'] ?? ''),
                        ];
                        $payloadJson = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
                        $searchText = strtolower(trim($senderName . ' ' . $senderEmail . ' ' . $senderPhone . ' ' . $messageTopic . ' ' . $messageText . ' ' . (string)$messageStatus));
                        ?>
                        <tr
                            class="contact-message-row"
                            data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                            data-status="<?= htmlspecialchars($messageStatus, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <td>
                                <div class="user-identity">
                                    <div class="user-avatar-shell">
                                        <span class="user-avatar-fallback">
                                            <?= htmlspecialchars(strtoupper(substr($senderName, 0, 1))) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($senderName) ?></div>
                                        <small class="text-muted d-block"><?= htmlspecialchars($senderEmail !== '' ? $senderEmail : '-') ?></small>
                                        <small class="text-muted d-block">Phone: <?= htmlspecialchars($senderPhone !== '' ? $senderPhone : '-') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold mb-1"><?= htmlspecialchars($messageTopic) ?></div>
                                <small class="text-muted d-block"><?= htmlspecialchars($messagePreview !== '' ? $messagePreview : '-') ?></small>
                            </td>
                            <td>
                                <span class="badge user-status-badge user-status-active"><?= htmlspecialchars((string)($message['created_at_display'] ?? '-')) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= htmlspecialchars($statusBadgeClass($messageStatus), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)($message['status_label'] ?? $statusLabel($messageStatus))) ?>
                                </span>
                            </td>
                            <td class="d-flex gap-2 flex-wrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary view-contact-message-btn"
                                    data-message="<?= $payloadJson ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewContactMessageModal"
                                >
                                    <i class="bi bi-eye me-1"></i>View
                                </button>

                                <form method="post" class="d-flex gap-1 align-items-center">
                                    <input type="hidden" name="action" value="update_contact_message_status">
                                    <input type="hidden" name="id" value="<?= $messageId ?>">
                                    <select name="status" class="form-select form-select-sm">
                                        <?php foreach ($allowedStatuses as $statusOption): ?>
                                            <option value="<?= htmlspecialchars((string)$statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= $messageStatus === $statusOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($statusLabel((string)$statusOption), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </form>

                                <form method="post" class="d-inline" data-confirm="Delete this contact message?">
                                    <input type="hidden" name="action" value="delete_contact_message">
                                    <input type="hidden" name="id" value="<?= $messageId ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        <tr id="contactMessagesNoResultsRow" class="<?= empty($messages) ? '' : 'd-none' ?>">
                            <td colspan="5" class="text-center text-muted py-4">
                                <?= empty($messages) ? 'No contact messages found.' : 'No messages match your filters.' ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade users-modal" id="viewContactMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-left-text me-1"></i>Contact Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>ID:</strong> <span id="viewContactMessageId">-</span></p>
                        <p class="mb-2"><strong>Full Name:</strong> <span id="viewContactMessageName">-</span></p>
                        <p class="mb-2"><strong>Email:</strong> <span id="viewContactMessageEmail">-</span></p>
                        <p class="mb-2"><strong>Phone:</strong> <span id="viewContactMessagePhone">-</span></p>
                        <p class="mb-2"><strong>Topic:</strong> <span id="viewContactMessageTopic">-</span></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Status:</strong> <span id="viewContactMessageStatus">-</span></p>
                        <p class="mb-2"><strong>Received:</strong> <span id="viewContactMessageCreated">-</span></p>
                        <p class="mb-2"><strong>Updated:</strong> <span id="viewContactMessageUpdated">-</span></p>
                        <p class="mb-2"><strong>IP Address:</strong> <span id="viewContactMessageIp">-</span></p>
                        <p class="mb-2"><strong>User Agent:</strong> <span id="viewContactMessageUserAgent">-</span></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold mb-2">Message</label>
                        <div class="border rounded-3 bg-light p-3">
                            <span id="viewContactMessageBody">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin-contact-messages.js"></script>
