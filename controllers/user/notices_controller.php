<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

$state = [
    'message' => null,
    'stats' => [
        'total' => 0,
        'today' => 0,
        'this_week' => 0,
    ],
    'filters' => [
        'q' => '',
    ],
    'notices' => [],
];

if ($userId <= 0) {
    $state['message'] = ['type' => 'danger', 'text' => 'Session expired. Please login again.'];
    return $state;
}

$search = trim((string)($_GET['q'] ?? ''));
$noticesState = user_fetch_notices_for_user($pdo, 500);
$allNotices = is_array($noticesState['items'] ?? null) ? $noticesState['items'] : [];

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-6 days'));

foreach ($allNotices as $notice) {
    $createdAt = (string)($notice['created_at'] ?? '');
    $createdDate = $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '';

    if ($createdDate === $today) {
        $state['stats']['today']++;
    }
    if ($createdDate !== '' && $createdDate >= $weekStart && $createdDate <= $today) {
        $state['stats']['this_week']++;
    }
}

$state['stats']['total'] = (int)($noticesState['count'] ?? count($allNotices));

if ($search !== '') {
    $needle = strtolower($search);
    $allNotices = array_values(array_filter($allNotices, static function (array $notice) use ($needle): bool {
        $blob = strtolower(trim(
            (string)($notice['title'] ?? '') . ' ' .
            (string)($notice['content'] ?? '') . ' ' .
            (string)($notice['created_at_display'] ?? '')
        ));
        return strpos($blob, $needle) !== false;
    }));
}

$state['filters']['q'] = $search;
$state['notices'] = $allNotices;

if (empty($allNotices) && $search === '') {
    $state['message'] = ['type' => 'info', 'text' => 'No notices found for your account at the moment.'];
}

return $state;
