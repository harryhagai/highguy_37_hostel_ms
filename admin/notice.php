<?php
require_once __DIR__ . '/../config/db_connection.php';

$errors = [];
$success = '';
$openModal = '';
$editFormData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_notice') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO notices (title, content) VALUES (?, ?)');
            $stmt->execute([$title, $content]);
            $success = 'Notice added successfully.';
        } else {
            $openModal = 'addNoticeModal';
        }
    }

    if ($action === 'update_notice') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '') $errors[] = 'Content is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE notices SET title = ?, content = ? WHERE id = ?');
            $stmt->execute([$title, $content, $id]);
            $success = 'Notice updated successfully.';
        } else {
            $openModal = 'editNoticeModal';
            $editFormData = [
                'id' => $id,
                'title' => $title,
                'content' => $content
            ];
        }
    }

    if ($action === 'delete_notice') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM notices WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Notice deleted successfully.';
        }
    }
}

$notices = $pdo->query('SELECT id, title, content, created_at FROM notices ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Manage Notices</h4>
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                <i class="bi bi-plus-circle"></i> Add Notice
            </button>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Content</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($notices as $notice): ?>
                    <?php $json = htmlspecialchars(json_encode($notice), ENT_QUOTES, 'UTF-8'); ?>
                    <tr>
                        <td><?= (int)$notice['id'] ?></td>
                        <td><?= htmlspecialchars($notice['title']) ?></td>
                        <td><?= nl2br(htmlspecialchars($notice['content'])) ?></td>
                        <td><?= htmlspecialchars($notice['created_at']) ?></td>
                        <td class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-info text-white view-notice-btn" data-notice="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewNoticeModal">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="button" class="btn btn-sm btn-warning edit-notice-btn" data-notice="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#editNoticeModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="post" onsubmit="return confirm('Delete this notice?');" class="d-inline">
                                <input type="hidden" name="action" value="delete_notice">
                                <input type="hidden" name="id" value="<?= (int)$notice['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="add_notice">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-megaphone"></i> Add Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Save Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="update_notice">
                <input type="hidden" name="id" id="editNoticeId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" id="editNoticeTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" id="editNoticeContent" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Update Notice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-card-text"></i> Notice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>ID:</strong> <span id="viewNoticeId">-</span></p>
                <p class="mb-2"><strong>Title:</strong> <span id="viewNoticeTitle">-</span></p>
                <p class="mb-2"><strong>Created:</strong> <span id="viewNoticeCreated">-</span></p>
                <p class="mb-0"><strong>Content:</strong><br><span id="viewNoticeContent">-</span></p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function fillNoticeEdit(notice) {
        document.getElementById('editNoticeId').value = notice.id ?? '';
        document.getElementById('editNoticeTitle').value = notice.title ?? '';
        document.getElementById('editNoticeContent').value = notice.content ?? '';
    }

    function fillNoticeView(notice) {
        document.getElementById('viewNoticeId').textContent = notice.id ?? '-';
        document.getElementById('viewNoticeTitle').textContent = notice.title ?? '-';
        document.getElementById('viewNoticeCreated').textContent = notice.created_at ?? '-';
        document.getElementById('viewNoticeContent').textContent = notice.content ?? '-';
    }

    document.querySelectorAll('.edit-notice-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillNoticeEdit(JSON.parse(this.dataset.notice));
        });
    });

    document.querySelectorAll('.view-notice-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillNoticeView(JSON.parse(this.dataset.notice));
        });
    });

    var openModal = <?= json_encode($openModal) ?>;
    var editFormData = <?= json_encode($editFormData) ?>;

    if (openModal === 'editNoticeModal' && editFormData) {
        fillNoticeEdit(editFormData);
    }

    if (openModal) {
        var target = document.getElementById(openModal);
        if (target && window.bootstrap) {
            new bootstrap.Modal(target).show();
        }
    }
})();
</script>
