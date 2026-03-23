<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/admin_post_guard.php';
require_once __DIR__ . '/../../includes/contact_helpers.php';

$errors = [];
$success = '';
$openModal = '';
$editDraft = null;

$createDraft = [
    'phone' => '',
    'email' => '',
    'location' => '',
    'working_hours' => '',
    'support_note' => '',
    'is_active' => 1,
];

$flash = admin_prg_consume('contact_information_settings');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
    $openModal = (string)($flash['openModal'] ?? '');
    if (is_array($flash['createDraft'] ?? null)) {
        $createDraft = array_merge($createDraft, $flash['createDraft']);
    }
    $editDraft = is_array($flash['editDraft'] ?? null) ? $flash['editDraft'] : null;
}

$tableReady = contact_ensure_contact_information_table($pdo);

$normalizeText = static function (string $value): string {
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
};

$validateContactInformation = static function (array $input, array &$bucketErrors, callable $normalizer): array {
    $payload = [
        'phone' => $normalizer((string)($input['phone'] ?? '')),
        'email' => $normalizer((string)($input['email'] ?? '')),
        'location' => $normalizer((string)($input['location'] ?? '')),
        'working_hours' => $normalizer((string)($input['working_hours'] ?? '')),
        'support_note' => trim((string)($input['support_note'] ?? '')),
        'is_active' => isset($input['is_active']) ? 1 : 0,
    ];

    if ($payload['phone'] === '') {
        $bucketErrors[] = 'Phone number is required.';
    } elseif (strlen($payload['phone']) > 30) {
        $bucketErrors[] = 'Phone number must be 30 characters or less.';
    }

    if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $bucketErrors[] = 'A valid email address is required.';
    } elseif (strlen($payload['email']) > 190) {
        $bucketErrors[] = 'Email must be 190 characters or less.';
    }

    if ($payload['location'] === '') {
        $bucketErrors[] = 'Location is required.';
    } elseif (strlen($payload['location']) > 190) {
        $bucketErrors[] = 'Location must be 190 characters or less.';
    }

    if ($payload['working_hours'] === '') {
        $bucketErrors[] = 'Working hours are required.';
    } elseif (strlen($payload['working_hours']) > 190) {
        $bucketErrors[] = 'Working hours must be 190 characters or less.';
    }

    if (strlen($payload['support_note']) > 255) {
        $bucketErrors[] = 'Support note must be 255 characters or less.';
    }

    return $payload;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $contactActions = [
        'create_contact_information',
        'update_contact_information',
        'delete_contact_information',
        'activate_contact_information',
    ];

    if (in_array($action, $contactActions, true)) {
        if (!$tableReady) {
            $errors[] = 'contact_information table is not ready. Please check database permissions.';
        } elseif ($action === 'create_contact_information') {
            $payload = $validateContactInformation($_POST, $errors, $normalizeText);
            $createDraft = $payload;

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();
                    if ((int)$payload['is_active'] === 1) {
                        $pdo->exec('UPDATE contact_information SET is_active = 0');
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO contact_information
                            (phone, email, location, working_hours, support_note, is_active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
                    );
                    $stmt->execute([
                        $payload['phone'],
                        $payload['email'],
                        $payload['location'],
                        $payload['working_hours'],
                        $payload['support_note'] !== '' ? $payload['support_note'] : null,
                        (int)$payload['is_active'],
                    ]);
                    $pdo->commit();
                    $success = 'Contact information created successfully.';
                    $createDraft = [
                        'phone' => '',
                        'email' => '',
                        'location' => '',
                        'working_hours' => '',
                        'support_note' => '',
                        'is_active' => 1,
                    ];
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Failed to create contact information.';
                    $openModal = 'createContactInformationModal';
                }
            } else {
                $openModal = 'createContactInformationModal';
            }
        } elseif ($action === 'update_contact_information') {
            $id = (int)($_POST['id'] ?? 0);
            $payload = $validateContactInformation($_POST, $errors, $normalizeText);
            $payload['id'] = $id;
            $editDraft = $payload;

            if ($id <= 0) {
                $errors[] = 'Invalid contact information selected.';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();
                    if ((int)$payload['is_active'] === 1) {
                        $stmt = $pdo->prepare('UPDATE contact_information SET is_active = 0 WHERE id <> ?');
                        $stmt->execute([$id]);
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE contact_information
                         SET phone = ?, email = ?, location = ?, working_hours = ?, support_note = ?, is_active = ?, updated_at = NOW()
                         WHERE id = ?'
                    );
                    $stmt->execute([
                        $payload['phone'],
                        $payload['email'],
                        $payload['location'],
                        $payload['working_hours'],
                        $payload['support_note'] !== '' ? $payload['support_note'] : null,
                        (int)$payload['is_active'],
                        $id,
                    ]);

                    if ($stmt->rowCount() <= 0) {
                        $existsStmt = $pdo->prepare('SELECT id FROM contact_information WHERE id = ? LIMIT 1');
                        $existsStmt->execute([$id]);
                        if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                            $errors[] = 'Contact information record not found.';
                        }
                    }

                    if (empty($errors)) {
                        $pdo->commit();
                        $success = 'Contact information updated successfully.';
                    } else {
                        $pdo->rollBack();
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Failed to update contact information.';
                }
            }

            if (!empty($errors)) {
                $openModal = 'editContactInformationModal';
            }
        } elseif ($action === 'activate_contact_information') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid contact information selected.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->exec('UPDATE contact_information SET is_active = 0');
                    $stmt = $pdo->prepare('UPDATE contact_information SET is_active = 1, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() <= 0) {
                        $pdo->rollBack();
                        $errors[] = 'Contact information record not found.';
                    } else {
                        $pdo->commit();
                        $success = 'Contact information activated successfully.';
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Failed to activate contact information.';
                }
            }
        } elseif ($action === 'delete_contact_information') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid contact information selected.';
            } else {
                try {
                    $stmt = $pdo->prepare('DELETE FROM contact_information WHERE id = ? LIMIT 1');
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() > 0) {
                        $success = 'Contact information deleted successfully.';

                        $activeCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_information WHERE is_active = 1')->fetchColumn();
                        if ($activeCount <= 0) {
                            $latestId = (int)$pdo->query('SELECT id FROM contact_information ORDER BY updated_at DESC, id DESC LIMIT 1')->fetchColumn();
                            if ($latestId > 0) {
                                $activateStmt = $pdo->prepare('UPDATE contact_information SET is_active = 1, updated_at = NOW() WHERE id = ?');
                                $activateStmt->execute([$latestId]);
                            }
                        }
                    } else {
                        $errors[] = 'Contact information record not found.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Failed to delete contact information.';
                }
            }
        }

        admin_prg_redirect('contact_information_settings', [
            'errors' => $errors,
            'success' => $success,
            'openModal' => $openModal,
            'createDraft' => $createDraft,
            'editDraft' => $editDraft,
        ]);
    }
}

$records = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
];

if ($tableReady) {
    try {
        $records = $pdo->query(
            'SELECT id, phone, email, location, working_hours, support_note, is_active, created_at, updated_at
             FROM contact_information
             ORDER BY COALESCE(is_active, 0) DESC, updated_at DESC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $records = [];
        $errors[] = 'Unable to load contact information records.';
    }

    foreach ($records as &$record) {
        $record['id'] = (int)($record['id'] ?? 0);
        $record['phone'] = trim((string)($record['phone'] ?? ''));
        $record['email'] = trim((string)($record['email'] ?? ''));
        $record['location'] = trim((string)($record['location'] ?? ''));
        $record['working_hours'] = trim((string)($record['working_hours'] ?? ''));
        $record['support_note'] = trim((string)($record['support_note'] ?? ''));
        $record['is_active'] = (int)($record['is_active'] ?? 0) === 1 ? 1 : 0;
        $record['created_at_display'] = !empty($record['created_at']) ? date('d M Y H:i', strtotime((string)$record['created_at'])) : '-';
        $record['updated_at_display'] = !empty($record['updated_at']) ? date('d M Y H:i', strtotime((string)$record['updated_at'])) : '-';

        $stats['total']++;
        if ($record['is_active'] === 1) {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }
    unset($record);
}

return [
    'errors' => $errors,
    'success' => $success,
    'open_modal' => $openModal,
    'table_ready' => $tableReady,
    'create_draft' => $createDraft,
    'edit_draft' => $editDraft,
    'records' => $records,
    'stats' => $stats,
];
