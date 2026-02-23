<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

payment_expire_unpaid_pending_bookings($pdo, payment_booking_hold_minutes());

$state = [
    'errors' => [],
    'message' => null,
    'beds' => [],
    'can_book' => true,
    'booking_lock' => null,
    'stats' => [
        'available_beds' => 0,
        'hostels' => 0,
        'locations' => 0,
    ],
    'semester_options' => [],
    'default_semester_id' => 0,
    'user_phone' => '',
    'semester_ready' => true,
    'filters' => [
        'search' => '',
        'hostel_id' => 0,
        'location' => '',
        'room_type' => '',
        'gender' => '',
        'price_min' => '',
        'price_max' => '',
        'sort' => 'hostel_asc',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+30 days')),
        'hostel_options' => [],
        'location_options' => [],
        'room_type_options' => [],
        'gender_options' => [],
    ],
];

if ($userId <= 0) {
    $state['errors'][] = 'Session expired. Please login again.';
    return $state;
}

if (!user_table_exists($pdo, 'beds') || !user_table_exists($pdo, 'rooms') || !user_table_exists($pdo, 'hostels')) {
    $state['errors'][] = 'Beds, rooms, or hostels table is missing.';
    return $state;
}

if (!user_table_exists($pdo, 'bookings')) {
    $state['errors'][] = 'Bookings table is missing.';
    return $state;
}

$bookingHasUser = user_column_exists($pdo, 'bookings', 'user_id');
$bookingHasBedId = user_column_exists($pdo, 'bookings', 'bed_id');
$bookingHasRoomId = user_column_exists($pdo, 'bookings', 'room_id');
$bookingHasStatus = user_column_exists($pdo, 'bookings', 'status');
$bookingHasStart = user_column_exists($pdo, 'bookings', 'start_date');
$bookingHasEnd = user_column_exists($pdo, 'bookings', 'end_date');
$bookingHasBookingDate = user_column_exists($pdo, 'bookings', 'booking_date');
$bookingTokenColumn = user_booking_token_column($pdo);
$bookingHasSemesterId = user_column_exists($pdo, 'bookings', 'semester_id');
$bookingHasSemesterName = user_column_exists($pdo, 'bookings', 'semester_name');
$bookingHasSemesterMonths = user_column_exists($pdo, 'bookings', 'semester_months');
$bookingHasMonthlyPrice = user_column_exists($pdo, 'bookings', 'monthly_price');
$bookingHasTotalPrice = user_column_exists($pdo, 'bookings', 'total_price');

if (!$bookingHasUser || !$bookingHasBedId) {
    $state['errors'][] = 'Bookings schema must include user_id and bed_id for bed booking.';
    return $state;
}

$roomHasType = user_column_exists($pdo, 'rooms', 'room_type');
$roomHasPrice = user_column_exists($pdo, 'rooms', 'price');
$roomHasDescription = user_column_exists($pdo, 'rooms', 'description');
$bedsHaveStatus = user_column_exists($pdo, 'beds', 'status');
$hostelHasStatus = user_column_exists($pdo, 'hostels', 'status');
$hostelHasGender = user_column_exists($pdo, 'hostels', 'gender');
$supportRoomImages = user_table_exists($pdo, 'room_images') && user_column_exists($pdo, 'rooms', 'room_image_id');
$usersHavePhone = user_column_exists($pdo, 'users', 'phone');
$bookingLock = user_get_booking_lock_info($pdo, $userId);
$state['can_book'] = !$bookingLock['blocked'];
$state['booking_lock'] = $bookingLock;

$semesters = user_fetch_active_semesters($pdo);
$state['semester_options'] = $semesters;
$defaultSemester = user_find_current_semester($semesters) ?? ($semesters[0] ?? null);
$state['default_semester_id'] = (int)($defaultSemester['id'] ?? 0);

if ($usersHavePhone && user_table_exists($pdo, 'users')) {
    $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $state['user_phone'] = trim((string)$stmt->fetchColumn());
}

if (empty($semesters)) {
    $state['errors'][] = 'No active semester has been configured by admin.';
    $state['semester_ready'] = false;
}

$selectedStartDate = trim((string)($_GET['start_date'] ?? (string)($defaultSemester['start_date'] ?? date('Y-m-d'))));
$selectedEndDate = trim((string)($_GET['end_date'] ?? (string)($defaultSemester['end_date'] ?? date('Y-m-d', strtotime('+30 days')))));
if (strtotime($selectedStartDate) === false) {
    $selectedStartDate = date('Y-m-d');
}
if (strtotime($selectedEndDate) === false || strtotime($selectedEndDate) <= strtotime($selectedStartDate)) {
    $selectedEndDate = date('Y-m-d', strtotime($selectedStartDate . ' +30 days'));
}

$search = trim((string)($_GET['q'] ?? ''));
$selectedHostelId = (int)($_GET['hostel_id'] ?? 0);
$selectedLocation = strtolower(trim((string)($_GET['location'] ?? '')));
$selectedRoomType = strtolower(trim((string)($_GET['room_type'] ?? '')));
$selectedGender = strtolower(trim((string)($_GET['gender'] ?? '')));
$selectedPriceMinRaw = trim((string)($_GET['price_min'] ?? ''));
$selectedPriceMaxRaw = trim((string)($_GET['price_max'] ?? ''));
$selectedSort = strtolower(trim((string)($_GET['sort'] ?? 'hostel_asc')));

if (!in_array($selectedSort, ['hostel_asc', 'price_asc', 'price_desc', 'room_asc'], true)) {
    $selectedSort = 'hostel_asc';
}

$priceMin = is_numeric($selectedPriceMinRaw) ? max(0, (float)$selectedPriceMinRaw) : null;
$priceMax = is_numeric($selectedPriceMaxRaw) ? max(0, (float)$selectedPriceMaxRaw) : null;
if ($priceMin !== null && $priceMax !== null && $priceMax < $priceMin) {
    $tmp = $priceMin;
    $priceMin = $priceMax;
    $priceMax = $tmp;
}

$currentUserGender = '';
if (user_table_exists($pdo, 'users') && user_column_exists($pdo, 'users', 'gender')) {
    $stmt = $pdo->prepare('SELECT gender FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $normalized = user_normalize_gender((string)($row['gender'] ?? ''));
    if (in_array($normalized, ['male', 'female'], true)) {
        $currentUserGender = $normalized;
    }
}

$dbNow = '';
try {
    $dbNow = (string)$pdo->query('SELECT NOW()')->fetchColumn();
} catch (Throwable $e) {
    $dbNow = date('Y-m-d H:i:s');
}

$canAccessHostelByGender = static function (string $hostelGender, string $userGender): bool {
    if ($hostelGender === 'all') {
        return true;
    }
    if ($userGender === '') {
        return false;
    }
    return $hostelGender === $userGender;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'book_bed') {
    $bedId = (int)($_POST['bed_id'] ?? 0);
    $semesterId = (int)($_POST['semester_id'] ?? (int)($defaultSemester['id'] ?? 0));
    $selectedSemester = user_find_semester_by_id($semesters, $semesterId);
    $bookingStartDate = (string)($selectedSemester['start_date'] ?? '');
    $bookingEndDate = (string)($selectedSemester['end_date'] ?? '');
    $semesterName = (string)($selectedSemester['name'] ?? '');
    $semesterMonths = (int)($selectedSemester['months'] ?? 0);

    if ($bookingLock['blocked']) {
        $state['message'] = [
            'type' => 'warning',
            'text' => (string)($bookingLock['message'] ?? 'You already have an active booking request.'),
        ];
    } elseif (!$selectedSemester) {
        $state['message'] = [
            'type' => 'danger',
            'text' => 'Please select a valid semester.',
        ];
    } elseif ($bedId <= 0) {
        $state['message'] = [
            'type' => 'danger',
            'text' => 'Please select a valid bed.',
        ];
    } elseif ($bookingHasStart && $bookingHasEnd && strtotime($bookingEndDate) <= strtotime($bookingStartDate)) {
        $state['message'] = [
            'type' => 'danger',
            'text' => 'Selected semester has invalid date range.',
        ];
    } else {
        $verifyFields = [
            'b.id AS bed_id',
            'b.bed_number',
            'r.id AS room_id',
            'r.room_number',
            $roomHasPrice ? 'r.price' : '0 AS price',
            'h.id AS hostel_id',
            'h.name AS hostel_name',
            $hostelHasGender ? 'h.gender AS hostel_gender' : "'all' AS hostel_gender",
            $hostelHasStatus ? 'h.status AS hostel_status' : "'active' AS hostel_status",
        ];

        $verifySql = 'SELECT ' . implode(', ', $verifyFields) . '
            FROM beds b
            JOIN rooms r ON r.id = b.room_id
            JOIN hostels h ON h.id = r.hostel_id
            WHERE b.id = ?';
        if ($bedsHaveStatus) {
            $verifySql .= " AND b.status = 'active'";
        }
        if ($hostelHasStatus) {
            $verifySql .= " AND h.status = 'active'";
        }
        $verifySql .= ' LIMIT 1';

        $stmt = $pdo->prepare($verifySql);
        $stmt->execute([$bedId]);
        $selectedBed = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$selectedBed) {
            $state['message'] = [
                'type' => 'danger',
                'text' => 'Selected bed is not available.',
            ];
        } else {
            $hostelGender = user_normalize_gender((string)($selectedBed['hostel_gender'] ?? 'all'));
            if (!$canAccessHostelByGender($hostelGender, $currentUserGender)) {
                $state['message'] = [
                    'type' => 'danger',
                    'text' => 'You are not allowed to book beds in this hostel due to gender policy.',
                ];
            } else {
                $availabilitySql = "SELECT COUNT(*)
                    FROM bookings
                    WHERE bed_id = ?";
                $availabilityParams = [$bedId];
                if ($bookingHasStatus) {
                    $availabilitySql .= " AND LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved')";
                }
                if ($bookingHasStart && $bookingHasEnd) {
                    $availabilitySql .= ' AND ? < end_date AND ? > start_date';
                    $availabilityParams[] = $bookingStartDate;
                    $availabilityParams[] = $bookingEndDate;
                }

                $stmt = $pdo->prepare($availabilitySql);
                $stmt->execute($availabilityParams);
                $hasConflict = (int)$stmt->fetchColumn() > 0;

                if ($hasConflict) {
                    $state['message'] = [
                        'type' => 'warning',
                        'text' => 'Selected bed has already been booked for that period.',
                    ];
                } else {
                    $monthlyPrice = max(0, (float)($selectedBed['price'] ?? 0));
                    $totalPrice = $monthlyPrice * max(1, $semesterMonths);
                    $columns = ['user_id', 'bed_id'];
                    $values = [$userId, $bedId];
                    $placeholders = ['?', '?'];

                    if ($bookingHasRoomId) {
                        $columns[] = 'room_id';
                        $values[] = (int)$selectedBed['room_id'];
                        $placeholders[] = '?';
                    }
                    if ($bookingHasStatus) {
                        $columns[] = 'status';
                        $values[] = 'pending';
                        $placeholders[] = '?';
                    }
                    if ($bookingHasStart) {
                        $columns[] = 'start_date';
                        $values[] = $bookingStartDate;
                        $placeholders[] = '?';
                    }
                    if ($bookingHasEnd) {
                        $columns[] = 'end_date';
                        $values[] = $bookingEndDate;
                        $placeholders[] = '?';
                    }
                    if ($bookingHasBookingDate) {
                        $columns[] = 'booking_date';
                        $values[] = $dbNow;
                        $placeholders[] = '?';
                    }
                    if ($bookingTokenColumn !== '') {
                        $columns[] = $bookingTokenColumn;
                        $values[] = user_generate_booking_token();
                        $placeholders[] = '?';
                    }
                    if ($bookingHasSemesterId) {
                        $columns[] = 'semester_id';
                        $values[] = (int)$selectedSemester['id'];
                        $placeholders[] = '?';
                    }
                    if ($bookingHasSemesterName) {
                        $columns[] = 'semester_name';
                        $values[] = $semesterName;
                        $placeholders[] = '?';
                    }
                    if ($bookingHasSemesterMonths) {
                        $columns[] = 'semester_months';
                        $values[] = $semesterMonths;
                        $placeholders[] = '?';
                    }
                    if ($bookingHasMonthlyPrice) {
                        $columns[] = 'monthly_price';
                        $values[] = $monthlyPrice;
                        $placeholders[] = '?';
                    }
                    if ($bookingHasTotalPrice) {
                        $columns[] = 'total_price';
                        $values[] = $totalPrice;
                        $placeholders[] = '?';
                    }

                    $sql = 'INSERT INTO bookings (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    $bookingId = (int)$pdo->lastInsertId();
                    if (!headers_sent()) {
                        $redirect = 'user_dashboard_layout.php?page=payment_verification&booking_id=' . $bookingId . '&just_booked=1';
                        header('Location: ' . $redirect, true, 303);
                        exit;
                    }

                    $state['message'] = [
                        'type' => 'success',
                        'text' => 'Bed booked successfully. Please submit payment transaction ID to complete verification.',
                    ];
                }
            }
        }
    }
}

$joinConflictSql = "LOWER(COALESCE(bk.status, '')) IN ('pending', 'confirmed', 'approved')";
$queryParams = [];
if (!$bookingHasStatus) {
    $joinConflictSql = '1=1';
}
if ($bookingHasStart && $bookingHasEnd) {
    $joinConflictSql .= ' AND ? < bk.end_date AND ? > bk.start_date';
    $queryParams[] = $selectedStartDate;
    $queryParams[] = $selectedEndDate;
}

$selectFields = [
    'b.id AS bed_id',
    'b.bed_number',
    'r.id AS room_id',
    'r.room_number',
    $roomHasType ? 'r.room_type' : "'' AS room_type",
    $roomHasPrice ? 'r.price' : '0 AS price',
    $roomHasDescription ? 'r.description' : "'' AS room_description",
    $supportRoomImages ? 'ri.image_path AS room_image_path' : 'NULL AS room_image_path',
    'h.id AS hostel_id',
    'h.name AS hostel_name',
    'h.location AS hostel_location',
    $hostelHasGender ? 'h.gender AS hostel_gender' : "'all' AS hostel_gender",
    'h.hostel_image',
];

$bedsSql = 'SELECT ' . implode(', ', $selectFields) . "
    FROM beds b
    JOIN rooms r ON r.id = b.room_id
    JOIN hostels h ON h.id = r.hostel_id
    " . ($supportRoomImages ? 'LEFT JOIN room_images ri ON ri.id = r.room_image_id' : '') . "
    LEFT JOIN bookings bk
        ON bk.bed_id = b.id
       AND {$joinConflictSql}
    WHERE bk.id IS NULL";

if ($bedsHaveStatus) {
    $bedsSql .= " AND b.status = 'active'";
}
if ($hostelHasStatus) {
    $bedsSql .= " AND h.status = 'active'";
}

$bedsSql .= ' ORDER BY h.name ASC, r.room_number ASC, b.bed_number ASC';

$stmt = $pdo->prepare($bedsSql);
$stmt->execute($queryParams);
$allBeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allBeds = array_values(array_filter($allBeds, static function (array $bed) use ($currentUserGender, $canAccessHostelByGender): bool {
    $hostelGender = user_normalize_gender((string)($bed['hostel_gender'] ?? 'all'));
    return $canAccessHostelByGender($hostelGender, $currentUserGender);
}));

foreach ($allBeds as &$bed) {
    $bed['hostel_gender'] = user_normalize_gender((string)($bed['hostel_gender'] ?? 'all'));
    $bed['hostel_gender_label'] = user_gender_label((string)$bed['hostel_gender']);
    $bed['price'] = max(0, (float)($bed['price'] ?? 0));
    $bed['hostel_image_url'] = user_to_public_asset_path((string)($bed['hostel_image'] ?? ''), '../assets/images/logo.png');
    $bed['room_image_url'] = user_to_public_asset_path((string)($bed['room_image_path'] ?? ''), $bed['hostel_image_url']);
    $bed['hostel_location'] = trim((string)($bed['hostel_location'] ?? ''));
    $bed['room_type'] = trim((string)($bed['room_type'] ?? ''));
    $bed['search_blob'] = strtolower(trim(
        (string)($bed['hostel_name'] ?? '') . ' ' .
        (string)($bed['hostel_location'] ?? '') . ' ' .
        (string)($bed['room_number'] ?? '') . ' ' .
        (string)($bed['room_type'] ?? '') . ' ' .
        (string)($bed['bed_number'] ?? '')
    ));
}
unset($bed);

$hostelMap = [];
$locationMap = [];
$roomTypeMap = [];
$genderMap = [];

foreach ($allBeds as $bed) {
    $hostelId = (int)($bed['hostel_id'] ?? 0);
    $hostelName = trim((string)($bed['hostel_name'] ?? ''));
    if ($hostelId > 0 && $hostelName !== '') {
        $hostelMap[$hostelId] = $hostelName;
    }

    $location = trim((string)($bed['hostel_location'] ?? ''));
    if ($location !== '') {
        $locationMap[strtolower($location)] = $location;
    }

    $roomType = trim((string)($bed['room_type'] ?? ''));
    if ($roomType !== '') {
        $roomTypeMap[strtolower($roomType)] = $roomType;
    }

    $gender = user_normalize_gender((string)($bed['hostel_gender'] ?? 'all'));
    $genderMap[$gender] = user_gender_label($gender);
}

natcasesort($hostelMap);
natcasesort($locationMap);
natcasesort($roomTypeMap);

$filteredBeds = array_values(array_filter($allBeds, static function (array $bed) use (
    $search,
    $selectedHostelId,
    $selectedLocation,
    $selectedRoomType,
    $selectedGender,
    $priceMin,
    $priceMax
): bool {
    if ($selectedHostelId > 0 && (int)$bed['hostel_id'] !== $selectedHostelId) {
        return false;
    }

    if ($selectedLocation !== '' && strtolower((string)$bed['hostel_location']) !== $selectedLocation) {
        return false;
    }

    if ($selectedRoomType !== '' && strtolower((string)$bed['room_type']) !== $selectedRoomType) {
        return false;
    }

    if ($selectedGender !== '' && strtolower((string)$bed['hostel_gender']) !== $selectedGender) {
        return false;
    }

    if ($search !== '' && strpos((string)$bed['search_blob'], strtolower($search)) === false) {
        return false;
    }

    $price = (float)($bed['price'] ?? 0);
    if ($priceMin !== null && $price < $priceMin) {
        return false;
    }
    if ($priceMax !== null && $price > $priceMax) {
        return false;
    }

    return true;
}));

if ($selectedSort === 'price_asc') {
    usort($filteredBeds, static function (array $a, array $b): int {
        return ((float)$a['price']) <=> ((float)$b['price']);
    });
} elseif ($selectedSort === 'price_desc') {
    usort($filteredBeds, static function (array $a, array $b): int {
        return ((float)$b['price']) <=> ((float)$a['price']);
    });
} elseif ($selectedSort === 'room_asc') {
    usort($filteredBeds, static function (array $a, array $b): int {
        return strnatcasecmp((string)$a['room_number'], (string)$b['room_number']);
    });
} else {
    usort($filteredBeds, static function (array $a, array $b): int {
        $byHostel = strnatcasecmp((string)$a['hostel_name'], (string)$b['hostel_name']);
        if ($byHostel !== 0) {
            return $byHostel;
        }
        $byRoom = strnatcasecmp((string)$a['room_number'], (string)$b['room_number']);
        if ($byRoom !== 0) {
            return $byRoom;
        }
        return strnatcasecmp((string)$a['bed_number'], (string)$b['bed_number']);
    });
}

$state['beds'] = $filteredBeds;

$filteredHostels = [];
$filteredLocations = [];
foreach ($filteredBeds as $bed) {
    $filteredHostels[(int)$bed['hostel_id']] = true;
    $filteredLocations[strtolower((string)$bed['hostel_location'])] = true;
}

$state['stats'] = [
    'available_beds' => count($filteredBeds),
    'hostels' => count($filteredHostels),
    'locations' => count($filteredLocations),
];

$state['filters'] = [
    'search' => $search,
    'hostel_id' => $selectedHostelId,
    'location' => $selectedLocation,
    'room_type' => $selectedRoomType,
    'gender' => $selectedGender,
    'price_min' => $selectedPriceMinRaw,
    'price_max' => $selectedPriceMaxRaw,
    'sort' => $selectedSort,
    'start_date' => $selectedStartDate,
    'end_date' => $selectedEndDate,
    'hostel_options' => $hostelMap,
    'location_options' => array_values($locationMap),
    'room_type_options' => array_values($roomTypeMap),
    'gender_options' => $genderMap,
];

return $state;
