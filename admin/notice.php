<?php


require_once __DIR__ . '/../config/db_connection.php';

// Handle Create
if (isset($_POST['add_notice'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $errors = [];

    if (!$title) $errors[] = "Title is required.";
    if (!$content) $errors[] = "Content is required.";

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO notices (title, content) VALUES (?, ?)");
        $stmt->execute([$title, $content]);
        $success = "Notice added successfully!";
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard_layout.php?page=notice");
    exit;
}

// Handle Edit (fetch notice)
$edit_notice = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
    $stmt->execute([$id]);
    $edit_notice = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Update
if (isset($_POST['update_notice'])) {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $errors = [];

    if (!$title) $errors[] = "Title is required.";
    if (!$content) $errors[] = "Content is required.";

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE notices SET title=?, content=? WHERE id=?");
        $stmt->execute([$title, $content, $id]);
        $success = "Notice updated successfully!";
        $edit_notice = null;
    }
}

// Fetch all notices
$notices = $pdo->query("SELECT id, title, content, created_at FROM notices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid px-0">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><?= $edit_notice ? 'Edit Notice' : 'Add Notice' ?></h4>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
            <?php elseif (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?php if ($edit_notice): ?>
                    <input type="hidden" name="id" value="<?= $edit_notice['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($edit_notice['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="2" required><?= htmlspecialchars($edit_notice['content'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <?php if ($edit_notice): ?>
                        <button class="btn btn-success" name="update_notice" type="submit"><i class="bi bi-check-circle"></i> Update</button>
                        <a href="admin_dashboard_layout.php?page=notice" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button class="btn btn-primary" name="add_notice" type="submit"><i class="bi bi-plus-circle"></i> Add</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h4 class="mb-0">All Notices</h4>
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
                <?php foreach($notices as $notice): ?>
                    <tr>
                        <td><?= $notice['id'] ?></td>
                        <td><?= htmlspecialchars($notice['title']) ?></td>
                        <td><?= nl2br(htmlspecialchars($notice['content'])) ?></td>
                        <td><?= $notice['created_at'] ?></td>
                        <td>
                            <a href="admin_dashboard_layout.php?page=notice&edit=<?= $notice['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="admin_dashboard_layout.php?page=notice&delete=<?= $notice['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this notice?')"><i class="bi bi-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">