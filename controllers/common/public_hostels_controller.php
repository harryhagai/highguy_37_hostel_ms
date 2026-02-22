<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}

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

$normalizeGender = static function (?string $value): string {
    $gender = strtolower(trim((string)$value));
    if (!in_array($gender, ['male', 'female', 'all'], true)) {
        return 'all';
    }
    return $gender;
};

$genderLabel = static function (?string $value) use ($normalizeGender): string {
    $gender = $normalizeGender($value);
    if ($gender === 'male') {
        return 'Male Only';
    }
    if ($gender === 'female') {
        return 'Female Only';
    }
    return 'All Genders';
};

$toPublicPath = static function (?string $path): string {
    $value = trim((string)$path);
    if ($value === '') {
        return 'assets/images/logo.png';
    }
    if (strpos($value, '../') === 0 || strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
        return $value;
    }
    return ltrim($value, '/');
};

$limit = isset($catalogLimit) ? max(0, (int)$catalogLimit) : 0;

if (!$tableExists($pdo, 'hostels')) {
    return [
        'hostels' => [],
        'stats' => [
            'total_hostels' => 0,
            'total_rooms' => 0,
            'free_rooms' => 0,
            'locations' => 0,
        ],
        'location_options' => [],
        'price_options' => [],
    ];
}

$hasHostelStatus = $columnExists($pdo, 'hostels', 'status');
$hasHostelGender = $columnExists($pdo, 'hostels', 'gender');

$hostelSql = 'SELECT h.id, h.name, h.description, h.location, h.hostel_image, h.created_at';
$hostelSql .= $hasHostelStatus ? ', h.status' : ", 'active' AS status";
$hostelSql .= $hasHostelGender ? ', h.gender' : ", 'all' AS gender";
$hostelSql .= ' FROM hostels h';
if ($hasHostelStatus) {
    $hostelSql .= " WHERE h.status = 'active'";
}
$hostelSql .= ' ORDER BY h.created_at DESC, h.id DESC';

$hostels = $pdo->query($hostelSql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($hostels as &$hostel) {
    $hostel['gender'] = $normalizeGender((string)($hostel['gender'] ?? 'all'));
    $hostel['gender_label'] = $genderLabel((string)$hostel['gender']);
    $hostel['hostel_image_url'] = $toPublicPath((string)($hostel['hostel_image'] ?? ''));

    $descriptionFull = trim((string)($hostel['description'] ?? ''));
    if ($descriptionFull === '') {
        $descriptionFull = 'No description provided for this hostel.';
    }

    $description = $descriptionFull;
    if (function_exists('mb_strlen')) {
        if (mb_strlen($description) > 150) {
            $description = mb_substr($description, 0, 147) . '...';
        }
    } else {
        if (strlen($description) > 150) {
            $description = substr($description, 0, 147) . '...';
        }
    }

    $hostel['description_full'] = $descriptionFull;
    $hostel['description_preview'] = $description;
    $hostel['total_rooms'] = 0;
    $hostel['free_rooms'] = 0;
    $hostel['free_beds'] = 0;
    $hostel['bed_capacity'] = 0;
    $hostel['rooms'] = [];
    $hostel['starting_price'] = null;
    $hostel['rating_label'] = 'No ratings yet';
}
unset($hostel);

$statsByHostel = [];
$roomTableExists = $tableExists($pdo, 'rooms');
$roomHasAvailable = $roomTableExists && $columnExists($pdo, 'rooms', 'available');
$roomHasCapacity = $roomTableExists && $columnExists($pdo, 'rooms', 'capacity');
$roomHasPrice = $roomTableExists && $columnExists($pdo, 'rooms', 'price');
$roomHasNumber = $roomTableExists && $columnExists($pdo, 'rooms', 'room_number');
$roomHasType = $roomTableExists && $columnExists($pdo, 'rooms', 'room_type');
$roomHasDescription = $roomTableExists && $columnExists($pdo, 'rooms', 'description');
$bookingTableExists = $tableExists($pdo, 'bookings');
$bookingHasRoomId = $bookingTableExists && $columnExists($pdo, 'bookings', 'room_id');
$hasBeds = $tableExists($pdo, 'beds');
$bookingHasBedId = $bookingTableExists && $columnExists($pdo, 'bookings', 'bed_id');
$bookingHasStart = $bookingTableExists && $columnExists($pdo, 'bookings', 'start_date');
$bookingHasEnd = $bookingTableExists && $columnExists($pdo, 'bookings', 'end_date');
$priceByHostel = [];
$roomsByHostel = [];

if ($roomTableExists) {
    if ($roomHasPrice) {
        $priceRows = $pdo->query(
            'SELECT r.hostel_id, MIN(COALESCE(r.price, 0)) AS min_price
             FROM rooms r
             GROUP BY r.hostel_id'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($priceRows as $row) {
            $priceByHostel[(int)$row['hostel_id']] = max(0, (float)($row['min_price'] ?? 0));
        }
    }

    if ($roomHasAvailable) {
        $rows = $pdo->query(
            'SELECT
                r.hostel_id,
                COUNT(*) AS total_rooms,
                SUM(CASE WHEN COALESCE(r.available, 0) > 0 THEN 1 ELSE 0 END) AS free_rooms,
                SUM(CASE WHEN COALESCE(r.available, 0) > 0 THEN COALESCE(r.available, 0) ELSE 0 END) AS bed_capacity
             FROM rooms r
             GROUP BY r.hostel_id'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $hostelId = (int)$row['hostel_id'];
            $statsByHostel[$hostelId] = [
                'total_rooms' => (int)($row['total_rooms'] ?? 0),
                'free_rooms' => (int)($row['free_rooms'] ?? 0),
                'free_beds' => max(0, (int)($row['bed_capacity'] ?? 0)),
                'bed_capacity' => max(0, (int)($row['bed_capacity'] ?? 0)),
            ];
        }
    } elseif ($hasBeds && $bookingHasBedId && $bookingHasStart && $bookingHasEnd) {
        $rows = $pdo->query(
            "SELECT
                r.hostel_id,
                r.id AS room_id,
                COUNT(b.id) AS total_beds,
                SUM(CASE WHEN b.status = 'active' THEN 1 ELSE 0 END) AS active_beds,
                SUM(
                    CASE
                        WHEN b.status = 'active' AND bk.id IS NULL THEN 1
                        ELSE 0
                    END
                ) AS free_beds
             FROM rooms r
             LEFT JOIN beds b ON b.room_id = r.id
             LEFT JOIN bookings bk
                ON bk.bed_id = b.id
               AND LOWER(COALESCE(bk.status, '')) IN ('pending', 'confirmed', 'approved')
               AND CURDATE() >= bk.start_date
               AND CURDATE() < bk.end_date
             GROUP BY r.hostel_id, r.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $hostelId = (int)$row['hostel_id'];
            if (!isset($statsByHostel[$hostelId])) {
                $statsByHostel[$hostelId] = ['total_rooms' => 0, 'free_rooms' => 0, 'free_beds' => 0, 'bed_capacity' => 0];
            }

            $statsByHostel[$hostelId]['total_rooms']++;
            $freeBeds = max(0, (int)($row['free_beds'] ?? 0));
            if ($freeBeds > 0) {
                $statsByHostel[$hostelId]['free_rooms']++;
            }
            $statsByHostel[$hostelId]['free_beds'] += $freeBeds;
            $statsByHostel[$hostelId]['bed_capacity'] += (int)($row['total_beds'] ?? 0);
        }
    } elseif ($roomHasCapacity && $bookingHasRoomId) {
        $rows = $pdo->query(
            "SELECT
                r.hostel_id,
                r.id AS room_id,
                COALESCE(r.capacity, 0) AS capacity,
                COALESCE(bk.booked_count, 0) AS booked_count
             FROM rooms r
             LEFT JOIN (
                SELECT room_id, COUNT(*) AS booked_count
                FROM bookings
                WHERE LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved')
                GROUP BY room_id
             ) bk ON bk.room_id = r.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $hostelId = (int)$row['hostel_id'];
            if (!isset($statsByHostel[$hostelId])) {
                $statsByHostel[$hostelId] = ['total_rooms' => 0, 'free_rooms' => 0, 'free_beds' => 0, 'bed_capacity' => 0];
            }

            $capacity = max(0, (int)($row['capacity'] ?? 0));
            $booked = max(0, (int)($row['booked_count'] ?? 0));
            $freeBeds = max(0, $capacity - $booked);
            $statsByHostel[$hostelId]['total_rooms']++;
            if ($freeBeds > 0) {
                $statsByHostel[$hostelId]['free_rooms']++;
            }
            $statsByHostel[$hostelId]['free_beds'] += $freeBeds;
            $statsByHostel[$hostelId]['bed_capacity'] += $capacity;
        }
    } else {
        $rows = $pdo->query(
            'SELECT r.hostel_id, COUNT(*) AS total_rooms
             FROM rooms r
             GROUP BY r.hostel_id'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $hostelId = (int)$row['hostel_id'];
            $statsByHostel[$hostelId] = [
                'total_rooms' => (int)($row['total_rooms'] ?? 0),
                'free_rooms' => 0,
                'free_beds' => 0,
                'bed_capacity' => 0,
            ];
        }
    }

    $roomNumberExpr = $roomHasNumber
        ? "COALESCE(NULLIF(TRIM(r.room_number), ''), CONCAT('Room ', r.id))"
        : "CONCAT('Room ', r.id)";
    $roomTypeExpr = $roomHasType
        ? "COALESCE(NULLIF(TRIM(r.room_type), ''), 'Standard')"
        : "'Standard'";
    $roomDescriptionExpr = $roomHasDescription
        ? "COALESCE(NULLIF(TRIM(r.description), ''), '')"
        : "''";
    $roomPriceExpr = $roomHasPrice ? 'COALESCE(r.price, 0)' : '0';
    $roomCapacityExpr = $roomHasCapacity
        ? 'COALESCE(r.capacity, 0)'
        : ($roomHasAvailable ? 'COALESCE(r.available, 0)' : '0');

    if ($hasBeds && $bookingHasBedId && $bookingHasStart && $bookingHasEnd) {
        $roomRows = $pdo->query(
            "SELECT
                r.hostel_id,
                r.id AS room_id,
                MAX($roomNumberExpr) AS room_number,
                MAX($roomTypeExpr) AS room_type,
                MAX($roomPriceExpr) AS room_price,
                MAX($roomDescriptionExpr) AS room_description,
                SUM(CASE WHEN b.status = 'active' THEN 1 ELSE 0 END) AS total_beds,
                SUM(
                    CASE
                        WHEN b.status = 'active' AND bk.id IS NULL THEN 1
                        ELSE 0
                    END
                ) AS free_beds
             FROM rooms r
             LEFT JOIN beds b ON b.room_id = r.id
             LEFT JOIN bookings bk
                ON bk.bed_id = b.id
               AND LOWER(COALESCE(bk.status, '')) IN ('pending', 'confirmed', 'approved')
               AND CURDATE() >= bk.start_date
               AND CURDATE() < bk.end_date
             GROUP BY r.hostel_id, r.id
             ORDER BY r.hostel_id ASC, r.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($roomHasCapacity && $bookingHasRoomId) {
        $roomRows = $pdo->query(
            "SELECT
                r.hostel_id,
                r.id AS room_id,
                $roomNumberExpr AS room_number,
                $roomTypeExpr AS room_type,
                $roomPriceExpr AS room_price,
                $roomDescriptionExpr AS room_description,
                $roomCapacityExpr AS total_beds,
                GREATEST($roomCapacityExpr - COALESCE(bk.booked_count, 0), 0) AS free_beds
             FROM rooms r
             LEFT JOIN (
                SELECT room_id, COUNT(*) AS booked_count
                FROM bookings
                WHERE LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved')
                GROUP BY room_id
             ) bk ON bk.room_id = r.id
             ORDER BY r.hostel_id ASC, r.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $roomRows = $pdo->query(
            "SELECT
                r.hostel_id,
                r.id AS room_id,
                $roomNumberExpr AS room_number,
                $roomTypeExpr AS room_type,
                $roomPriceExpr AS room_price,
                $roomDescriptionExpr AS room_description,
                $roomCapacityExpr AS total_beds,
                " . ($roomHasAvailable ? 'GREATEST(COALESCE(r.available, 0), 0)' : '0') . " AS free_beds
             FROM rooms r
             ORDER BY r.hostel_id ASC, r.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($roomRows as $room) {
        $hostelId = (int)($room['hostel_id'] ?? 0);
        if ($hostelId <= 0) {
            continue;
        }

        if (!isset($roomsByHostel[$hostelId])) {
            $roomsByHostel[$hostelId] = [];
        }

        $roomPrice = max(0, (float)($room['room_price'] ?? 0));
        $roomCapacity = max(0, (int)($room['total_beds'] ?? 0));
        $roomFreeBeds = max(0, (int)($room['free_beds'] ?? 0));

        $roomsByHostel[$hostelId][] = [
            'id' => (int)($room['room_id'] ?? 0),
            'room_number' => (string)($room['room_number'] ?? ''),
            'room_type' => (string)($room['room_type'] ?? 'Standard'),
            'description' => (string)($room['room_description'] ?? ''),
            'price' => $roomPrice,
            'price_display' => number_format($roomPrice, 0),
            'capacity' => $roomCapacity,
            'free_beds' => $roomFreeBeds,
            'status' => $roomFreeBeds > 0 ? 'available' : 'full',
            'status_label' => $roomFreeBeds > 0 ? 'Available' : 'Full',
        ];
    }
}

$totalRooms = 0;
$totalFreeRooms = 0;
$locations = [];

foreach ($hostels as &$hostel) {
    $hostelId = (int)$hostel['id'];
    if (isset($statsByHostel[$hostelId])) {
        $hostel['total_rooms'] = (int)$statsByHostel[$hostelId]['total_rooms'];
        $hostel['free_rooms'] = (int)$statsByHostel[$hostelId]['free_rooms'];
        $hostel['free_beds'] = (int)($statsByHostel[$hostelId]['free_beds'] ?? 0);
        $hostel['bed_capacity'] = (int)$statsByHostel[$hostelId]['bed_capacity'];
    }
    if (isset($roomsByHostel[$hostelId])) {
        $hostel['rooms'] = $roomsByHostel[$hostelId];
    }
    if (isset($priceByHostel[$hostelId])) {
        $hostel['starting_price'] = (float)$priceByHostel[$hostelId];
    }

    $totalRooms += (int)$hostel['total_rooms'];
    $totalFreeRooms += (int)$hostel['free_rooms'];

    $location = trim((string)($hostel['location'] ?? ''));
    if ($location !== '') {
        $locations[strtolower($location)] = $location;
    }
}
unset($hostel);

$totalHostelsAll = count($hostels);

if ($limit > 0) {
    $hostels = array_slice($hostels, 0, $limit);
}

$locationOptions = array_values($locations);
natcasesort($locationOptions);
$locationOptions = array_values($locationOptions);

$priceOptions = [];
if (!empty($priceByHostel)) {
    $prices = array_map('floatval', array_values($priceByHostel));
    sort($prices, SORT_NUMERIC);

    $minPrice = (float)$prices[0];
    $maxPrice = (float)$prices[count($prices) - 1];

    if ($maxPrice <= 10000) {
        $step = 1000;
    } elseif ($maxPrice <= 50000) {
        $step = 5000;
    } elseif ($maxPrice <= 200000) {
        $step = 10000;
    } else {
        $step = 50000;
    }

    $rangeStart = max(0, ((int)floor($minPrice / $step)) * $step);
    $rangeEndLimit = ((int)ceil($maxPrice / $step)) * $step;

    if ($rangeEndLimit < $rangeStart) {
        $rangeEndLimit = $rangeStart;
    }

    for ($start = $rangeStart; $start <= $rangeEndLimit; $start += $step) {
        $end = $start + $step - 1;
        if ($end > $rangeEndLimit) {
            $end = $rangeEndLimit;
        }

        $priceOptions[] = [
            'value' => $start . '-' . $end,
            'label' => 'TSh ' . number_format((float)$start, 0) . ' - TSh ' . number_format((float)$end, 0),
        ];
    }
}

return [
    'hostels' => $hostels,
    'stats' => [
        'total_hostels' => $totalHostelsAll,
        'total_rooms' => $totalRooms,
        'free_rooms' => $totalFreeRooms,
        'locations' => count($locations),
    ],
    'location_options' => $locationOptions,
    'price_options' => $priceOptions,
];
