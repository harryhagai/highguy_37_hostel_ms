<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/admin_post_guard.php';
require_once __DIR__ . '/../../includes/contact_helpers.php';

$errors = [];
$success = '';

$flash = admin_prg_consume('contact_messages');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
}

$tableReady = contact_ensure_contact_messages_table($pdo);
$allowedStatuses = ['new', 'read', 'replied', 'resolved'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if (in_array($action, ['update_contact_message_status', 'delete_contact_message'], true)) {
        if (!$tableReady) {
            $errors[] = 'contact_messages table is not ready.';
        } elseif ($action === 'update_contact_message_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = contact_normalize_message_status((string)($_POST['status'] ?? 'new'));

            if ($id <= 0) {
                $errors[] = 'Invalid contact message selected.';
            }
            if (!in_array($status, $allowedStatuses, true)) {
                $errors[] = 'Invalid status selected.';
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare('UPDATE contact_messages SET status = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$status, $id]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Message status updated successfully.';
                    } else {
                        $checkStmt = $pdo->prepare('SELECT id FROM contact_messages WHERE id = ? LIMIT 1');
                        $checkStmt->execute([$id]);
                        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                            $success = 'No status change was needed.';
                        } else {
                            $errors[] = 'Contact message not found.';
                        }
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Failed to update message status.';
                }
            }
        } elseif ($action === 'delete_contact_message') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid contact message selected.';
            } else {
                try {
                    $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ? LIMIT 1');
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Contact message deleted successfully.';
                    } else {
                        $errors[] = 'Contact message not found.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Failed to delete contact message.';
                }
            }
        }

        admin_prg_redirect('contact_messages', [
            'errors' => $errors,
            'success' => $success,
        ]);
    }
}

$messages = [];
$stats = [
    'total' => 0,
    'new' => 0,
    'read' => 0,
    'replied' => 0,
    'resolved' => 0,
    'today' => 0,
];

if ($tableReady) {
    try {
        $messages = $pdo->query(
            'SELECT
                id,
                full_name,
                email,
                phone,
                topic,
                message,
                ip_address,
                user_agent,
                status,
                created_at,
                updated_at
             FROM contact_messages
             ORDER BY created_at DESC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $messages = [];
        $errors[] = 'Unable to load contact messages.';
    }

    $today = date('Y-m-d');

    foreach ($messages as &$message) {
        $message['id'] = (int)($message['id'] ?? 0);
        $message['full_name'] = trim((string)($message['full_name'] ?? ''));
        $message['email'] = trim((string)($message['email'] ?? ''));
        $message['phone'] = trim((string)($message['phone'] ?? ''));
        $message['topic'] = trim((string)($message['topic'] ?? ''));
        $message['message'] = trim((string)($message['message'] ?? ''));
        $message['status'] = contact_normalize_message_status((string)($message['status'] ?? 'new'));
        $message['status_label'] = ucwords(str_replace('_', ' ', (string)$message['status']));
        $message['created_at_display'] = !empty($message['created_at']) ? date('d M Y H:i', strtotime((string)$message['created_at'])) : '-';
        $message['updated_at_display'] = !empty($message['updated_at']) ? date('d M Y H:i', strtotime((string)$message['updated_at'])) : '-';

        $preview = $message['message'];
        if (strlen($preview) > 120) {
            $preview = substr($preview, 0, 120) . '...';
        }
        $message['message_preview'] = $preview;

        $stats['total']++;
        if (isset($stats[$message['status']])) {
            $stats[$message['status']]++;
        }

        $createdDate = !empty($message['created_at']) ? date('Y-m-d', strtotime((string)$message['created_at'])) : '';
        if ($createdDate === $today) {
            $stats['today']++;
        }
    }
    unset($message);
}

return [
    'errors' => $errors,
    'success' => $success,
    'table_ready' => $tableReady,
    'messages' => $messages,
    'stats' => $stats,
    'allowed_statuses' => $allowedStatuses,
];
