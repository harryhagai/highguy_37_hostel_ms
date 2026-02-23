<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/admin_post_guard.php';

$tableExists = static function (PDO $db, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    $cache[$table] = (int)$stmt->fetchColumn() > 0;
    return $cache[$table];
};

$columnExists = static function (PDO $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
};

$errors = [];
$success = '';
$flash = admin_prg_consume('semester_settings');
$draftCreate = null;
$draftUpdate = null;
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
    $draftCreate = is_array($flash['draft_create'] ?? null) ? $flash['draft_create'] : null;
    $draftUpdate = is_array($flash['draft_update'] ?? null) ? $flash['draft_update'] : null;
}

$requiredColumns = ['semester_key', 'term_type', 'semester_name', 'start_date', 'end_date', 'months', 'is_active'];
$tableReady = $tableExists($pdo, 'semester_settings');
if ($tableReady) {
    foreach ($requiredColumns as $column) {
        if (!$columnExists($pdo, 'semester_settings', $column)) {
            $tableReady = false;
            break;
        }
    }
}

if (!$columnExists($pdo, 'semester_settings', 'id')) {
    $tableReady = false;
}
if (!$columnExists($pdo, 'semester_settings', 'created_at')) {
    $tableReady = false;
}

$calculateEndDate = static function (string $startDate, int $months): ?string {
    if (strtotime($startDate) === false || !in_array($months, [4, 6], true)) {
        return null;
    }
    try {
        $date = new DateTime($startDate);
        $date->modify('+' . $months . ' months');
        $date->modify('-1 day');
        return $date->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
};

$termTypeToMonths = static function (string $termType): int {
    return $termType === 'long' ? 6 : 4;
};

$defaultCreate = [
    'semester_key' => 1,
    'term_type' => 'short',
    'semester_name' => 'Semester 1',
    'start_date' => '',
    'is_active' => 1,
];
if (is_array($draftCreate)) {
    $defaultCreate = array_merge($defaultCreate, $draftCreate);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if (in_array($action, ['create_semester', 'update_semester', 'delete_semester'], true)) {
        if (!$tableReady) {
            $errors[] = 'semester_settings table is missing or schema is incomplete. Run the provided SQL first.';
        }

        if ($action === 'create_semester') {
            $semesterKey = (int)($_POST['semester_key'] ?? 0);
            $termType = strtolower(trim((string)($_POST['term_type'] ?? '')));
            $semesterName = trim((string)($_POST['semester_name'] ?? ''));
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $months = $termTypeToMonths($termType);

            $draftCreate = [
                'semester_key' => $semesterKey,
                'term_type' => $termType,
                'semester_name' => $semesterName,
                'start_date' => $startDate,
                'is_active' => $isActive,
            ];

            if (!in_array($semesterKey, [1, 2], true)) {
                $errors[] = 'Semester type must be 1 or 2.';
            }
            if (!in_array($termType, ['short', 'long'], true)) {
                $errors[] = 'Term type must be short or long.';
            }
            if ($startDate === '' || strtotime($startDate) === false) {
                $errors[] = 'Start date is required.';
            }
            if ($semesterName === '') {
                $semesterName = 'Semester ' . $semesterKey . ' ' . ucfirst($termType) . ' Term';
            }

            $endDate = $calculateEndDate($startDate, $months);
            if ($endDate === null) {
                $errors[] = 'Unable to calculate end date from selected start date and months.';
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare('SELECT id FROM semester_settings WHERE semester_key = ? AND term_type = ? LIMIT 1');
                $stmt->execute([$semesterKey, $termType]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors[] = 'Semester ' . $semesterKey . ' (' . ucfirst($termType) . ' term) already exists. Use edit instead.';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO semester_settings
                                (semester_key, term_type, semester_name, start_date, end_date, months, is_active, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        );
                        $stmt->execute([$semesterKey, $termType, $semesterName, $startDate, $endDate, $months, $isActive]);
                        $success = 'Semester created successfully.';
                        $draftCreate = $defaultCreate;
                    } catch (Throwable $e) {
                        $errors[] = 'Failed to create semester.';
                    }
                }
            }
        }

        if ($action === 'update_semester') {
            $semesterId = (int)($_POST['id'] ?? 0);
            $semesterKey = (int)($_POST['semester_key'] ?? 0);
            $termType = strtolower(trim((string)($_POST['term_type'] ?? '')));
            $semesterName = trim((string)($_POST['semester_name'] ?? ''));
            $startDate = trim((string)($_POST['start_date'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $months = $termTypeToMonths($termType);

            if ($semesterId <= 0) {
                $errors[] = 'Invalid semester selected.';
            }
            if (!in_array($semesterKey, [1, 2], true)) {
                $errors[] = 'Semester type must be 1 or 2.';
            }
            if (!in_array($termType, ['short', 'long'], true)) {
                $errors[] = 'Term type must be short or long.';
            }
            if ($startDate === '' || strtotime($startDate) === false) {
                $errors[] = 'Start date is required.';
            }
            if ($semesterName === '') {
                $semesterName = 'Semester ' . $semesterKey . ' ' . ucfirst($termType) . ' Term';
            }

            $endDate = $calculateEndDate($startDate, $months);
            if ($endDate === null) {
                $errors[] = 'Unable to calculate end date from selected start date and months.';
            }

            $draftUpdate = [
                $semesterId => [
                    'semester_key' => $semesterKey,
                    'term_type' => $termType,
                    'semester_name' => $semesterName,
                    'start_date' => $startDate,
                    'months' => $months,
                    'is_active' => $isActive,
                ],
            ];

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT id
                         FROM semester_settings
                         WHERE semester_key = ? AND term_type = ? AND id <> ?
                         LIMIT 1'
                    );
                    $stmt->execute([$semesterKey, $termType, $semesterId]);
                    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                        $errors[] = 'Another semester already uses Semester ' . $semesterKey . ' (' . ucfirst($termType) . ' term).';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Failed to validate semester uniqueness.';
                }
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE semester_settings
                         SET semester_key = ?, term_type = ?, semester_name = ?, start_date = ?, end_date = ?, months = ?, is_active = ?, updated_at = NOW()
                         WHERE id = ?'
                    );
                    $stmt->execute([$semesterKey, $termType, $semesterName, $startDate, $endDate, $months, $isActive, $semesterId]);
                    $success = $stmt->rowCount() > 0
                        ? 'Semester updated successfully.'
                        : 'No changes saved for selected semester.';
                } catch (Throwable $e) {
                    $errors[] = 'Failed to update semester.';
                }
            }
        }

        if ($action === 'delete_semester') {
            $semesterId = (int)($_POST['id'] ?? 0);
            if ($semesterId <= 0) {
                $errors[] = 'Invalid semester selected.';
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM semester_settings WHERE id = ? LIMIT 1');
                    $stmt->execute([$semesterId]);
                    $success = $stmt->rowCount() > 0
                        ? 'Semester deleted successfully.'
                        : 'Semester not found.';
                } catch (Throwable $e) {
                    $errors[] = 'Failed to delete semester.';
                }
            }
        }

        admin_prg_redirect('semester_settings', [
            'errors' => $errors,
            'success' => $success,
            'draft_create' => $draftCreate,
            'draft_update' => $draftUpdate,
        ]);
    }
}

$semesters = [];
if ($tableReady) {
    $stmt = $pdo->query(
        'SELECT id, semester_key, term_type, semester_name, start_date, end_date, months, is_active, created_at
         FROM semester_settings
         ORDER BY semester_key ASC, term_type ASC, start_date ASC, id ASC'
    );
    $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($semesters as &$semester) {
        $semester['id'] = (int)($semester['id'] ?? 0);
        $semester['semester_key'] = (int)($semester['semester_key'] ?? 0);
        $termType = strtolower(trim((string)($semester['term_type'] ?? 'short')));
        $semester['term_type'] = in_array($termType, ['short', 'long'], true) ? $termType : 'short';
        $semester['semester_name'] = trim((string)($semester['semester_name'] ?? ''));
        $semester['start_date'] = trim((string)($semester['start_date'] ?? ''));
        $semester['end_date'] = trim((string)($semester['end_date'] ?? ''));
        $semester['months'] = (int)($semester['months'] ?? 0);
        $semester['is_active'] = (int)($semester['is_active'] ?? 1) === 1 ? 1 : 0;
        $semester['created_at_display'] = !empty($semester['created_at'])
            ? date('d M Y', strtotime((string)$semester['created_at']))
            : '-';

        if (isset($draftUpdate[$semester['id']]) && is_array($draftUpdate[$semester['id']])) {
            $semester = array_merge($semester, $draftUpdate[$semester['id']]);
            $semester['is_active'] = (int)($semester['is_active'] ?? 1) === 1 ? 1 : 0;
        }
    }
    unset($semester);
}

return [
    'errors' => $errors,
    'success' => $success,
    'table_ready' => $tableReady,
    'semesters' => $semesters,
    'create_draft' => $defaultCreate,
];
