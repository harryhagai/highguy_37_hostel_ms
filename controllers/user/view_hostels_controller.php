<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/helpers.php';

$hostels = user_fetch_hostel_cards($pdo, true);
$currentUserGender = '';

if (
    !empty($_SESSION['user_id'])
    && user_table_exists($pdo, 'users')
    && user_column_exists($pdo, 'users', 'gender')
) {
    $userId = (int)$_SESSION['user_id'];
    if ($userId > 0) {
        $stmt = $pdo->prepare('SELECT gender FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $rawUserGender = user_normalize_gender((string)($userRow['gender'] ?? ''));
        if (in_array($rawUserGender, ['male', 'female'], true)) {
            $currentUserGender = $rawUserGender;
        }
    }
}

$hostels = array_values(array_filter(
    $hostels,
    static function (array $hostel) use ($currentUserGender): bool {
        $hostelGender = user_normalize_gender((string)($hostel['gender'] ?? 'all'));
        if ($hostelGender === 'all') {
            return true;
        }
        if ($currentUserGender === '') {
            return false;
        }
        return $hostelGender === $currentUserGender;
    }
));

$totalRooms = 0;
$totalFreeRooms = 0;
$locationMap = [];
$genderMap = [];

foreach ($hostels as &$hostel) {
    $hostel['gender_label'] = user_gender_label((string)($hostel['gender'] ?? 'all'));
    $hostel['description_preview'] = trim((string)($hostel['description'] ?? ''));
    if ($hostel['description_preview'] === '') {
        $hostel['description_preview'] = 'No description available for this hostel.';
    }
    $descLength = function_exists('mb_strlen') ? mb_strlen($hostel['description_preview']) : strlen($hostel['description_preview']);
    if ($descLength > 130) {
        $hostel['description_preview'] = function_exists('mb_substr')
            ? mb_substr($hostel['description_preview'], 0, 127) . '...'
            : substr($hostel['description_preview'], 0, 127) . '...';
    }

    $totalRooms += (int)($hostel['total_rooms'] ?? 0);
    $totalFreeRooms += (int)($hostel['free_rooms'] ?? 0);

    $location = trim((string)($hostel['location'] ?? ''));
    if ($location !== '') {
        $locationMap[strtolower($location)] = $location;
    }

    $gender = user_normalize_gender((string)($hostel['gender'] ?? 'all'));
    $genderMap[$gender] = user_gender_label($gender);
}
unset($hostel);

$locationOptions = array_values($locationMap);
natcasesort($locationOptions);
$locationOptions = array_values($locationOptions);

$genderOptions = [];
foreach (['male', 'female', 'all'] as $genderKey) {
    if (isset($genderMap[$genderKey])) {
        $genderOptions[$genderKey] = $genderMap[$genderKey];
    }
}
if (empty($genderOptions)) {
    $genderOptions = [
        'male' => 'Male Only',
        'female' => 'Female Only',
        'all' => 'All Genders',
    ];
}

return [
    'hostels' => $hostels,
    'stats' => [
        'total_hostels' => count($hostels),
        'total_rooms' => $totalRooms,
        'free_rooms' => $totalFreeRooms,
        'locations' => count($locationOptions),
    ],
    'location_options' => $locationOptions,
    'gender_options' => $genderOptions,
];
