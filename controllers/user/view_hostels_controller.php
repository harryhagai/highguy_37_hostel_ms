<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';

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

$roomMetaByHostel = [];
if (user_table_exists($pdo, 'rooms')) {
    $roomHasType = user_column_exists($pdo, 'rooms', 'room_type');
    $roomHasPrice = user_column_exists($pdo, 'rooms', 'price');
    $roomHasAvailable = user_column_exists($pdo, 'rooms', 'available');
    $roomHasCapacity = user_column_exists($pdo, 'rooms', 'capacity');
    $roomHasBedCapacity = user_column_exists($pdo, 'rooms', 'bed_capacity');

    $roomSelect = [
        'r.id',
        'r.hostel_id',
        'r.room_number',
        $roomHasType ? 'r.room_type' : "'' AS room_type",
        $roomHasPrice ? 'r.price' : 'NULL AS price',
        $roomHasAvailable ? 'r.available' : 'NULL AS available',
        $roomHasCapacity ? 'r.capacity' : 'NULL AS capacity',
        $roomHasBedCapacity ? 'r.bed_capacity' : 'NULL AS bed_capacity',
    ];

    $roomRows = $pdo->query(
        'SELECT ' . implode(', ', $roomSelect) . '
         FROM rooms r
         ORDER BY r.hostel_id ASC, r.room_number ASC, r.id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($roomRows as $roomRow) {
        $hostelId = (int)($roomRow['hostel_id'] ?? 0);
        if ($hostelId <= 0) {
            continue;
        }

        if (!isset($roomMetaByHostel[$hostelId])) {
            $roomMetaByHostel[$hostelId] = [
                'priced_rooms' => 0,
                'price_min' => null,
                'price_max' => null,
                'rooms' => [],
            ];
        }

        $spotsLabel = '-';
        if ($roomHasAvailable) {
            $spotsLabel = max(0, (int)($roomRow['available'] ?? 0)) . ' spots left';
        } elseif ($roomHasCapacity) {
            $spotsLabel = 'Capacity ' . max(0, (int)($roomRow['capacity'] ?? 0));
        } elseif ($roomHasBedCapacity) {
            $spotsLabel = 'Capacity ' . max(0, (int)($roomRow['bed_capacity'] ?? 0));
        }

        $priceValue = null;
        if ($roomHasPrice && isset($roomRow['price']) && $roomRow['price'] !== null && is_numeric($roomRow['price'])) {
            $priceValue = max(0, (float)$roomRow['price']);
            $roomMetaByHostel[$hostelId]['priced_rooms']++;

            if ($roomMetaByHostel[$hostelId]['price_min'] === null || $priceValue < (float)$roomMetaByHostel[$hostelId]['price_min']) {
                $roomMetaByHostel[$hostelId]['price_min'] = $priceValue;
            }
            if ($roomMetaByHostel[$hostelId]['price_max'] === null || $priceValue > (float)$roomMetaByHostel[$hostelId]['price_max']) {
                $roomMetaByHostel[$hostelId]['price_max'] = $priceValue;
            }
        }

        $roomMetaByHostel[$hostelId]['rooms'][] = [
            'room_number' => (string)($roomRow['room_number'] ?? '-'),
            'room_type' => trim((string)($roomRow['room_type'] ?? '')),
            'price' => $priceValue,
            'price_label' => $priceValue !== null ? ('TSh ' . number_format($priceValue, 2)) : 'Price not set',
            'spots_label' => $spotsLabel,
        ];
    }
}

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

    $hostelId = (int)($hostel['id'] ?? 0);
    $roomMeta = $roomMetaByHostel[$hostelId] ?? null;
    $hostel['priced_rooms'] = 0;
    $hostel['room_price_min'] = null;
    $hostel['room_price_max'] = null;
    $hostel['room_price_min_label'] = '-';
    $hostel['room_price_max_label'] = '-';
    $hostel['room_price_summary'] = 'Price not set';
    $hostel['room_pricing_preview'] = [];

    if (is_array($roomMeta)) {
        $hostel['priced_rooms'] = (int)($roomMeta['priced_rooms'] ?? 0);
        $hostel['room_price_min'] = $roomMeta['price_min'];
        $hostel['room_price_max'] = $roomMeta['price_max'];
        $hostel['room_pricing_preview'] = array_slice((array)($roomMeta['rooms'] ?? []), 0, 10);

        if ($hostel['room_price_min'] !== null) {
            $hostel['room_price_min_label'] = 'TSh ' . number_format((float)$hostel['room_price_min'], 2);
            $hostel['room_price_max_label'] = 'TSh ' . number_format((float)$hostel['room_price_max'], 2);

            if ((float)$hostel['room_price_min'] === (float)$hostel['room_price_max']) {
                $hostel['room_price_summary'] = $hostel['room_price_min_label'];
            } else {
                $hostel['room_price_summary'] = $hostel['room_price_min_label'] . ' - ' . $hostel['room_price_max_label'];
            }
        }
    }
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
