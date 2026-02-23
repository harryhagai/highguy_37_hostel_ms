<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/admin_post_guard.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$errors = [];
$success = '';

$flash = admin_prg_consume('payment_settings');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
}

$tableReady = payment_control_numbers_table_ready($pdo);
$hasIsActive = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'is_active');
$hasSortOrder = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'sort_order');
$hasUpdatedAt = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'updated_at');
$hasCreatedAt = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'created_at');
$hasNetworkIcon = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'network_icon');
$hasCompanyName = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'company_name');
$hasInfo = $tableReady && payment_column_exists($pdo, 'payment_control_numbers', 'info');

$sanitizeText = static fn(string $value): string => trim(preg_replace('/\s+/', ' ', $value) ?? '');

$saveIcon = static function (array $file): array {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => '', 'error' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Failed to upload network icon.'];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'path' => '', 'error' => 'Invalid uploaded icon file.'];
    }

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mimeType === '' || strpos($mimeType, 'image/') !== 0) {
        return ['ok' => false, 'path' => '', 'error' => 'Network icon must be a valid image.'];
    }

    $maxBytes = 3 * 1024 * 1024;
    if ((int)($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'path' => '', 'error' => 'Network icon size must be under 3MB.'];
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext ?? '');
    if ($ext === '') {
        $ext = 'png';
    }

    $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    if ($safeBase === '') {
        $safeBase = 'network';
    }

    $targetDir = __DIR__ . '/../../uploads/payment_networks';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = $safeBase . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return ['ok' => false, 'path' => '', 'error' => 'Unable to save uploaded icon.'];
    }

    return ['ok' => true, 'path' => 'uploads/payment_networks/' . $filename, 'error' => ''];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if (!$tableReady) {
        $errors[] = 'Payment control numbers table is missing. Run payment migration first.';
    } elseif ($action === 'create_control_number' || $action === 'update_control_number') {
        $id = (int)($_POST['id'] ?? 0);
        $networkName = $sanitizeText((string)($_POST['network_name'] ?? ''));
        $controlNumber = $sanitizeText((string)($_POST['control_number'] ?? ''));
        $companyName = $sanitizeText((string)($_POST['company_name'] ?? ''));
        $info = trim((string)($_POST['info'] ?? ''));
        $isActive = $hasIsActive ? ((int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0) : 1;
        $sortOrder = $hasSortOrder ? (int)($_POST['sort_order'] ?? 0) : 0;

        if ($networkName === '') {
            $errors[] = 'Network name is required.';
        }
        if ($controlNumber === '') {
            $errors[] = 'Control number is required.';
        } elseif (!preg_match('/^[0-9A-Za-z\\-\\s]{4,60}$/', $controlNumber)) {
            $errors[] = 'Control number format is invalid.';
        }

        $iconPath = '';
        $iconResult = $saveIcon($_FILES['network_icon'] ?? []);
        if (!$iconResult['ok']) {
            $errors[] = (string)$iconResult['error'];
        } else {
            $iconPath = (string)$iconResult['path'];
        }

        if (empty($errors)) {
            if ($action === 'create_control_number') {
                $columns = ['network_name', 'control_number'];
                $values = [$networkName, $controlNumber];
                $placeholders = ['?', '?'];

                if ($hasNetworkIcon) {
                    $columns[] = 'network_icon';
                    $values[] = $iconPath;
                    $placeholders[] = '?';
                }
                if ($hasCompanyName) {
                    $columns[] = 'company_name';
                    $values[] = $companyName;
                    $placeholders[] = '?';
                }
                if ($hasInfo) {
                    $columns[] = 'info';
                    $values[] = $info;
                    $placeholders[] = '?';
                }
                if ($hasIsActive) {
                    $columns[] = 'is_active';
                    $values[] = $isActive;
                    $placeholders[] = '?';
                }
                if ($hasSortOrder) {
                    $columns[] = 'sort_order';
                    $values[] = $sortOrder;
                    $placeholders[] = '?';
                }
                if ($hasCreatedAt) {
                    $columns[] = 'created_at';
                    $values[] = date('Y-m-d H:i:s');
                    $placeholders[] = '?';
                }
                if ($hasUpdatedAt) {
                    $columns[] = 'updated_at';
                    $values[] = date('Y-m-d H:i:s');
                    $placeholders[] = '?';
                }

                $sql = 'INSERT INTO payment_control_numbers (' . implode(', ', $columns) . ')
                        VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $success = 'Control number created successfully.';
            } else {
                if ($id <= 0) {
                    $errors[] = 'Invalid control number ID.';
                } else {
                    $set = ['network_name = ?', 'control_number = ?'];
                    $values = [$networkName, $controlNumber];

                    if ($hasCompanyName) {
                        $set[] = 'company_name = ?';
                        $values[] = $companyName;
                    }
                    if ($hasInfo) {
                        $set[] = 'info = ?';
                        $values[] = $info;
                    }
                    if ($hasIsActive) {
                        $set[] = 'is_active = ?';
                        $values[] = $isActive;
                    }
                    if ($hasSortOrder) {
                        $set[] = 'sort_order = ?';
                        $values[] = $sortOrder;
                    }
                    if ($hasNetworkIcon && $iconPath !== '') {
                        $set[] = 'network_icon = ?';
                        $values[] = $iconPath;
                    }
                    if ($hasUpdatedAt) {
                        $set[] = 'updated_at = NOW()';
                    }

                    $values[] = $id;
                    $sql = 'UPDATE payment_control_numbers SET ' . implode(', ', $set) . ' WHERE id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    $success = 'Control number updated successfully.';
                }
            }
        }
    } elseif ($action === 'delete_control_number') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid control number ID.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM payment_control_numbers WHERE id = ?');
            $stmt->execute([$id]);
            $success = $stmt->rowCount() > 0 ? 'Control number deleted successfully.' : 'Record already removed.';
        }
    }

    admin_prg_redirect('payment_settings', [
        'errors' => $errors,
        'success' => $success,
    ]);
}

$controlNumbers = [];
if ($tableReady) {
    $select = [
        'id',
        'network_name',
        $hasNetworkIcon ? 'network_icon' : "'' AS network_icon",
        'control_number',
        $hasCompanyName ? 'company_name' : "'' AS company_name",
        $hasInfo ? 'info' : "'' AS info",
        $hasIsActive ? 'is_active' : '1 AS is_active',
        $hasSortOrder ? 'sort_order' : '0 AS sort_order',
        $hasUpdatedAt ? 'updated_at' : 'NULL AS updated_at',
    ];

    $orderBy = $hasSortOrder ? 'COALESCE(sort_order, 9999) ASC, id ASC' : 'id ASC';
    $sql = 'SELECT ' . implode(', ', $select) . ' FROM payment_control_numbers ORDER BY ' . $orderBy;
    $controlNumbers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

return [
    'errors' => $errors,
    'success' => $success,
    'table_ready' => $tableReady,
    'control_numbers' => $controlNumbers,
];
