<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/user_helpers.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$hostelId = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;

payment_expire_unpaid_pending_bookings($pdo, payment_booking_hold_minutes());

$state = [
    'errors' => [],
    'message' => null,
    'hostel' => null,
    'rooms' => [],
    'existing_booking' => null,
    'booking_mode' => 'room',
    'selected_start_date' => date('Y-m-d'),
    'selected_end_date' => date('Y-m-d', strtotime('+30 days')),
    'semester_options' => [],
    'default_semester_id' => 0,
    'user_phone' => '',
    'semester_ready' => true,
];

if ($hostelId <= 0) {
    $state['errors'][] = 'Select a hostel first before booking.';
    return $state;
}

if (!user_table_exists($pdo, 'hostels')) {
    $state['errors'][] = 'Hostels table is missing.';
    return $state;
}

$hostelSelect = ['id', 'name', 'location', 'description', 'hostel_image'];
if (user_column_exists($pdo, 'hostels', 'gender')) {
    $hostelSelect[] = 'gender';
}
if (user_column_exists($pdo, 'hostels', 'status')) {
    $hostelSelect[] = 'status';
}
$stmt = $pdo->prepare('SELECT ' . implode(', ', $hostelSelect) . ' FROM hostels WHERE id = ? LIMIT 1');
$stmt->execute([$hostelId]);
$hostel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hostel) {
    $state['errors'][] = 'Hostel not found.';
    return $state;
}

$hostelStatus = strtolower(trim((string)($hostel['status'] ?? 'active')));
if ($hostelStatus === 'inactive') {
    $state['errors'][] = 'This hostel is currently inactive.';
    return $state;
}

$hostel['gender'] = user_normalize_gender((string)($hostel['gender'] ?? 'all'));
$hostel['gender_label'] = user_gender_label((string)$hostel['gender']);
$hostel['hostel_image_url'] = user_to_public_asset_path((string)($hostel['hostel_image'] ?? ''), '../assets/images/logo.png');
$state['hostel'] = $hostel;

if (!user_table_exists($pdo, 'rooms')) {
    $state['errors'][] = 'Rooms table is missing.';
    return $state;
}

$bookingHasStatus = user_column_exists($pdo, 'bookings', 'status');
$bookingHasStartDate = user_column_exists($pdo, 'bookings', 'start_date');
$bookingHasEndDate = user_column_exists($pdo, 'bookings', 'end_date');
$bookingHasBookingDate = user_column_exists($pdo, 'bookings', 'booking_date');
$bookingTokenColumn = user_booking_token_column($pdo);
$bookingHasRoomId = user_column_exists($pdo, 'bookings', 'room_id');
$bookingHasBedId = user_column_exists($pdo, 'bookings', 'bed_id');
$bookingHasSemesterId = user_column_exists($pdo, 'bookings', 'semester_id');
$bookingHasSemesterName = user_column_exists($pdo, 'bookings', 'semester_name');
$bookingHasSemesterMonths = user_column_exists($pdo, 'bookings', 'semester_months');
$bookingHasMonthlyPrice = user_column_exists($pdo, 'bookings', 'monthly_price');
$bookingHasTotalPrice = user_column_exists($pdo, 'bookings', 'total_price');

$hasBeds = user_table_exists($pdo, 'beds');
$usesBedBooking = $hasBeds && $bookingHasBedId;
$usesRoomBooking = $bookingHasRoomId;
$state['booking_mode'] = $usesBedBooking ? 'bed' : 'room';

$roomHasAvailable = user_column_exists($pdo, 'rooms', 'available');
$roomHasCapacity = user_column_exists($pdo, 'rooms', 'capacity');
$roomHasType = user_column_exists($pdo, 'rooms', 'room_type');
$roomHasPrice = user_column_exists($pdo, 'rooms', 'price');
$roomHasDescription = user_column_exists($pdo, 'rooms', 'description');
$supportsRoomImages = user_table_exists($pdo, 'room_images') && user_column_exists($pdo, 'rooms', 'room_image_id');
$userHasPhone = user_column_exists($pdo, 'users', 'phone');
$bookingLock = user_get_booking_lock_info($pdo, $userId);
$semesters = user_fetch_active_semesters($pdo);
$state['semester_options'] = $semesters;
$defaultSemester = user_find_current_semester($semesters) ?? ($semesters[0] ?? null);
$state['default_semester_id'] = (int)($defaultSemester['id'] ?? 0);

if ($userHasPhone && user_table_exists($pdo, 'users')) {
    $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $state['user_phone'] = trim((string)$stmt->fetchColumn());
}

if (empty($semesters)) {
    $state['semester_ready'] = false;
    $state['errors'][] = 'No active semester has been configured by admin.';
}

$selectedStartDate = $bookingHasStartDate
    ? trim((string)($_GET['start_date'] ?? (string)($defaultSemester['start_date'] ?? date('Y-m-d'))))
    : date('Y-m-d');
$selectedEndDate = $bookingHasEndDate
    ? trim((string)($_GET['end_date'] ?? (string)($defaultSemester['end_date'] ?? date('Y-m-d', strtotime('+30 days')))))
    : date('Y-m-d', strtotime('+30 days'));

if ($bookingHasStartDate && strtotime($selectedStartDate) === false) {
    $selectedStartDate = date('Y-m-d');
}
if ($bookingHasEndDate && strtotime($selectedEndDate) === false) {
    $selectedEndDate = date('Y-m-d', strtotime('+30 days'));
}

$state['selected_start_date'] = $selectedStartDate;
$state['selected_end_date'] = $selectedEndDate;

$dbNow = '';
try {
    $dbNow = (string)$pdo->query('SELECT NOW()')->fetchColumn();
} catch (Throwable $e) {
    $dbNow = date('Y-m-d H:i:s');
}

$state['existing_booking'] = $bookingLock['blocked'] ? $bookingLock['booking'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'book_room') {
    if ($bookingLock['blocked']) {
        $state['message'] = [
            'type' => 'warning',
            'text' => (string)($bookingLock['message'] ?? 'You already have an active booking request.'),
        ];
    } else {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $bedId = (int)($_POST['bed_id'] ?? 0);
        $semesterId = (int)($_POST['semester_id'] ?? (int)($defaultSemester['id'] ?? 0));
        $selectedSemester = user_find_semester_by_id($semesters, $semesterId);
        $bookingStartDate = (string)($selectedSemester['start_date'] ?? '');
        $bookingEndDate = (string)($selectedSemester['end_date'] ?? '');
        $semesterName = (string)($selectedSemester['name'] ?? '');
        $semesterMonths = (int)($selectedSemester['months'] ?? 0);

        if ($roomId <= 0) {
            $state['message'] = [
                'type' => 'danger',
                'text' => 'Invalid room selected.',
            ];
        } elseif (!$selectedSemester) {
            $state['message'] = [
                'type' => 'danger',
                'text' => 'Please select a valid semester.',
            ];
        } elseif ($bookingHasStartDate && $bookingHasEndDate && strtotime($bookingEndDate) <= strtotime($bookingStartDate)) {
            $state['message'] = [
                'type' => 'danger',
                'text' => 'Selected semester has invalid date range.',
            ];
        } else {
            $roomStmt = $pdo->prepare('SELECT id FROM rooms WHERE id = ? AND hostel_id = ? LIMIT 1');
            $roomStmt->execute([$roomId, $hostelId]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

            if (!$room) {
                $state['message'] = [
                    'type' => 'danger',
                    'text' => 'Selected room is not available in this hostel.',
                ];
            } else {
                if ($usesBedBooking) {
                    if ($bedId <= 0) {
                        $state['message'] = [
                            'type' => 'warning',
                            'text' => 'Please choose an available bed before booking.',
                        ];
                    } else {
                        $stmt = $pdo->prepare('SELECT id, bed_number FROM beds WHERE id = ? AND room_id = ? AND status = ? LIMIT 1');
                        $stmt->execute([$bedId, $roomId, 'active']);
                        $bed = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$bed) {
                            $state['message'] = [
                                'type' => 'danger',
                                'text' => 'Selected bed is not valid for this room.',
                            ];
                        } else {
                            $availabilitySql =
                                "SELECT COUNT(*)
                                 FROM bookings
                                 WHERE bed_id = ?
                                   AND LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved')";
                            $availabilityParams = [$bedId];

                            if ($bookingHasStartDate && $bookingHasEndDate) {
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
                                    'text' => 'Selected bed is no longer available for those dates. Please choose another bed.',
                                ];
                            } else {
                                $roomPriceStmt = $pdo->prepare('SELECT COALESCE(price, 0) FROM rooms WHERE id = ? LIMIT 1');
                                $roomPriceStmt->execute([$roomId]);
                                $monthlyPrice = (float)$roomPriceStmt->fetchColumn();
                                $totalPrice = $monthlyPrice * max(1, $semesterMonths);
                                $columns = ['user_id', 'bed_id'];
                                $values = [$userId, $bedId];
                                $placeholders = ['?', '?'];

                                if ($bookingHasStatus) {
                                    $columns[] = 'status';
                                    $values[] = 'pending';
                                    $placeholders[] = '?';
                                }
                                if ($bookingHasStartDate) {
                                    $columns[] = 'start_date';
                                    $values[] = $bookingStartDate;
                                    $placeholders[] = '?';
                                }
                                if ($bookingHasEndDate) {
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
                } elseif ($usesRoomBooking) {
                    $roomFull = false;
                    if ($roomHasAvailable) {
                        $stmt = $pdo->prepare('SELECT COALESCE(available, 0) AS available FROM rooms WHERE id = ? LIMIT 1');
                        $stmt->execute([$roomId]);
                        $available = (int)$stmt->fetchColumn();
                        $roomFull = $available <= 0;
                    } elseif ($roomHasCapacity) {
                        $sql =
                            "SELECT
                                COALESCE(r.capacity, 0) AS capacity,
                                COALESCE(bk.booked_count, 0) AS booked_count
                             FROM rooms r
                             LEFT JOIN (
                                SELECT room_id, COUNT(*) AS booked_count
                                FROM bookings
                                WHERE LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved')
                                GROUP BY room_id
                             ) bk ON bk.room_id = r.id
                             WHERE r.id = ?
                             LIMIT 1";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$roomId]);
                        $capacityRow = $stmt->fetch(PDO::FETCH_ASSOC);
                        $roomFull = ((int)($capacityRow['booked_count'] ?? 0) >= (int)($capacityRow['capacity'] ?? 0));
                    }

                    if ($roomFull) {
                        $state['message'] = [
                            'type' => 'warning',
                            'text' => 'This room is already full.',
                        ];
                    } else {
                        $roomPriceStmt = $pdo->prepare('SELECT COALESCE(price, 0) FROM rooms WHERE id = ? LIMIT 1');
                        $roomPriceStmt->execute([$roomId]);
                        $monthlyPrice = (float)$roomPriceStmt->fetchColumn();
                        $totalPrice = $monthlyPrice * max(1, $semesterMonths);
                        $columns = ['user_id', 'room_id'];
                        $values = [$userId, $roomId];
                        $placeholders = ['?', '?'];

                        if ($bookingHasStatus) {
                            $columns[] = 'status';
                            $values[] = 'pending';
                            $placeholders[] = '?';
                        }
                        if ($bookingHasStartDate) {
                            $columns[] = 'start_date';
                            $values[] = $bookingStartDate;
                            $placeholders[] = '?';
                        }
                        if ($bookingHasEndDate) {
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

                        if ($roomHasAvailable) {
                            $stmt = $pdo->prepare('UPDATE rooms SET available = GREATEST(COALESCE(available, 0) - 1, 0) WHERE id = ?');
                            $stmt->execute([$roomId]);
                        }
                        if (!headers_sent()) {
                            $redirect = 'user_dashboard_layout.php?page=payment_verification&booking_id=' . $bookingId . '&just_booked=1';
                            header('Location: ' . $redirect, true, 303);
                            exit;
                        }

                        $state['message'] = [
                            'type' => 'success',
                            'text' => 'Room booked successfully. Please submit payment transaction ID to complete verification.',
                        ];
                    }
                } else {
                    $state['message'] = [
                        'type' => 'danger',
                        'text' => 'Bookings schema is not compatible with this page.',
                    ];
                }
            }
        }

        $state['existing_booking'] = $bookingLock['blocked'] ? $bookingLock['booking'] : null;
    }
}

$roomColumns = [
    'r.id',
    'r.room_number',
    $roomHasType ? 'r.room_type' : "'' AS room_type",
    $roomHasPrice ? 'r.price' : '0 AS price',
    $roomHasDescription ? 'r.description' : "'' AS description",
    $supportsRoomImages ? 'r.room_image_id' : 'NULL AS room_image_id',
    $supportsRoomImages ? 'ri.image_path AS room_image_path' : 'NULL AS room_image_path',
];

if ($usesBedBooking) {
    $joinCondition = "LOWER(COALESCE(bk.status, '')) IN ('pending', 'confirmed', 'approved')";
    $roomStatsParams = [];

    if ($bookingHasStartDate && $bookingHasEndDate) {
        $joinCondition .= ' AND ? < bk.end_date AND ? > bk.start_date';
        $roomStatsParams[] = $selectedStartDate;
        $roomStatsParams[] = $selectedEndDate;
    }

    $groupBy = ['r.id', 'r.room_number'];
    if ($roomHasType) {
        $groupBy[] = 'r.room_type';
    }
    if ($roomHasPrice) {
        $groupBy[] = 'r.price';
    }
    if ($roomHasDescription) {
        $groupBy[] = 'r.description';
    }
    if ($supportsRoomImages) {
        $groupBy[] = 'r.room_image_id';
        $groupBy[] = 'ri.image_path';
    }

    $sql =
        'SELECT ' . implode(', ', $roomColumns) . ",
            COUNT(b.id) AS total_beds,
            SUM(CASE WHEN b.status = 'active' THEN 1 ELSE 0 END) AS active_beds,
            SUM(CASE WHEN b.status = 'active' AND bk.id IS NULL THEN 1 ELSE 0 END) AS free_beds
         FROM rooms r
         " . ($supportsRoomImages ? 'LEFT JOIN room_images ri ON ri.id = r.room_image_id' : '') . "
         LEFT JOIN beds b ON b.room_id = r.id
         LEFT JOIN bookings bk
           ON bk.bed_id = b.id
           AND " . $joinCondition . '
         WHERE r.hostel_id = ?
         GROUP BY ' . implode(', ', $groupBy) . '
         ORDER BY r.room_number ASC';

    $roomStatsParams[] = $hostelId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($roomStatsParams);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $roomsById = [];
    foreach ($rooms as &$room) {
        $activeBeds = max(0, (int)($room['active_beds'] ?? 0));
        $freeBeds = max(0, (int)($room['free_beds'] ?? 0));

        $room['capacity'] = $activeBeds > 0 ? $activeBeds : (int)($room['total_beds'] ?? 0);
        $room['spots_left'] = $freeBeds;
        $room['current_occupancy'] = max(0, $room['capacity'] - $freeBeds);
        $room['is_full'] = $freeBeds <= 0;
        $room['price'] = (float)($room['price'] ?? 0);
        $room['room_image_url'] = user_to_public_asset_path((string)($room['room_image_path'] ?? ''), '../assets/images/logo.png');
        $room['available_beds'] = [];

        $roomsById[(int)$room['id']] = &$room;
    }
    unset($room);

    if (!empty($roomsById)) {
        $bedJoinCondition = "LOWER(COALESCE(bk.status, '')) IN ('pending', 'confirmed', 'approved')";
        $bedParams = [];

        if ($bookingHasStartDate && $bookingHasEndDate) {
            $bedJoinCondition .= ' AND ? < bk.end_date AND ? > bk.start_date';
            $bedParams[] = $selectedStartDate;
            $bedParams[] = $selectedEndDate;
        }

        $bedSql =
            "SELECT b.id, b.room_id, b.bed_number
             FROM beds b
             JOIN rooms r ON r.id = b.room_id
             LEFT JOIN bookings bk
               ON bk.bed_id = b.id
              AND " . $bedJoinCondition . "
             WHERE r.hostel_id = ?
               AND b.status = 'active'
               AND bk.id IS NULL
             ORDER BY r.room_number ASC, b.bed_number ASC, b.id ASC";

        $bedParams[] = $hostelId;

        $stmt = $pdo->prepare($bedSql);
        $stmt->execute($bedParams);
        $availableBeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($availableBeds as $bed) {
            $roomKey = (int)($bed['room_id'] ?? 0);
            if (!isset($roomsById[$roomKey])) {
                continue;
            }

            $roomsById[$roomKey]['available_beds'][] = [
                'id' => (int)$bed['id'],
                'bed_number' => (string)$bed['bed_number'],
                'label' => 'Bed ' . (string)$bed['bed_number'],
            ];
        }

        foreach ($rooms as &$room) {
            $room['spots_left'] = count($room['available_beds']);
            $room['current_occupancy'] = max(0, (int)$room['capacity'] - (int)$room['spots_left']);
            $room['is_full'] = (int)$room['spots_left'] <= 0;
        }
        unset($room);
    }

    $state['rooms'] = $rooms;
} else {
    if ($roomHasAvailable) {
        $sql = 'SELECT ' . implode(', ', $roomColumns) . ', COALESCE(r.available, 0) AS spots_left, COALESCE(r.capacity, 0) AS capacity
            FROM rooms r
            ' . ($supportsRoomImages ? 'LEFT JOIN room_images ri ON ri.id = r.room_image_id' : '') . '
            WHERE r.hostel_id = ?
            ORDER BY r.room_number ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hostelId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as &$room) {
            $capacity = max(0, (int)($room['capacity'] ?? 0));
            $spots = max(0, (int)($room['spots_left'] ?? 0));
            $room['capacity'] = $capacity;
            $room['spots_left'] = $spots;
            $room['current_occupancy'] = max(0, $capacity - $spots);
            $room['is_full'] = $spots <= 0;
            $room['price'] = (float)($room['price'] ?? 0);
            $room['room_image_url'] = user_to_public_asset_path((string)($room['room_image_path'] ?? ''), '../assets/images/logo.png');
            $room['available_beds'] = [];
        }
        unset($room);

        $state['rooms'] = $rooms;
    } elseif ($roomHasCapacity && $usesRoomBooking) {
        $sql =
            'SELECT ' . implode(', ', $roomColumns) . ",
                COALESCE(r.capacity, 0) AS capacity,
                COALESCE(bk.booked_count, 0) AS booked_count
             FROM rooms r
             " . ($supportsRoomImages ? 'LEFT JOIN room_images ri ON ri.id = r.room_image_id' : '') . "
             LEFT JOIN (
                SELECT room_id, COUNT(*) AS booked_count
                FROM bookings
                WHERE LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved')
                GROUP BY room_id
             ) bk ON bk.room_id = r.id
             WHERE r.hostel_id = ?
             ORDER BY r.room_number ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hostelId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as &$room) {
            $capacity = max(0, (int)($room['capacity'] ?? 0));
            $booked = max(0, (int)($room['booked_count'] ?? 0));
            $spots = max(0, $capacity - $booked);
            $room['capacity'] = $capacity;
            $room['spots_left'] = $spots;
            $room['current_occupancy'] = $booked;
            $room['is_full'] = $spots <= 0;
            $room['price'] = (float)($room['price'] ?? 0);
            $room['room_image_url'] = user_to_public_asset_path((string)($room['room_image_path'] ?? ''), '../assets/images/logo.png');
            $room['available_beds'] = [];
        }
        unset($room);

        $state['rooms'] = $rooms;
    } else {
        $sql = 'SELECT ' . implode(', ', $roomColumns) . '
            FROM rooms r
            ' . ($supportsRoomImages ? 'LEFT JOIN room_images ri ON ri.id = r.room_image_id' : '') . '
            WHERE r.hostel_id = ?
            ORDER BY r.room_number ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hostelId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as &$room) {
            $room['capacity'] = 0;
            $room['spots_left'] = 0;
            $room['current_occupancy'] = 0;
            $room['is_full'] = false;
            $room['price'] = (float)($room['price'] ?? 0);
            $room['room_image_url'] = user_to_public_asset_path((string)($room['room_image_path'] ?? ''), '../assets/images/logo.png');
            $room['available_beds'] = [];
        }
        unset($room);

        $state['rooms'] = $rooms;
    }
}

return $state;
