<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

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

return [
    'errors' => $errors,
    'success' => $success,
    'openModal' => $openModal,
    'editFormData' => $editFormData,
    'notices' => $notices
];
