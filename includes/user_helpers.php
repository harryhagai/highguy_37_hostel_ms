<?php
if (!function_exists('user_table_exists')) {
    function user_table_exists(PDO $db, string $table): bool
    {
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
    }
}

if (!function_exists('user_column_exists')) {
    function user_column_exists(PDO $db, string $table, string $column): bool
    {
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
    }
}

if (!function_exists('user_normalize_gender')) {
    function user_normalize_gender(?string $value): string
    {
        $gender = strtolower(trim((string)$value));
        if (!in_array($gender, ['male', 'female', 'all'], true)) {
            return 'all';
        }
        return $gender;
    }
}

if (!function_exists('user_gender_label')) {
    function user_gender_label(?string $value): string
    {
        $gender = user_normalize_gender($value);
        if ($gender === 'male') {
            return 'Male Only';
        }
        if ($gender === 'female') {
            return 'Female Only';
        }
        return 'All Genders';
    }
}

if (!function_exists('user_normalize_booking_status')) {
    function user_normalize_booking_status(?string $value): string
    {
        $status = strtolower(trim((string)$value));
        if ($status === 'approved') {
            return 'confirmed';
        }
        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
            return 'pending';
        }
        return $status;
    }
}

if (!function_exists('user_booking_order_column')) {
    function user_booking_order_column(PDO $db): string
    {
        if (user_column_exists($db, 'bookings', 'booking_date')) {
            return 'booking_date';
        }
        if (user_column_exists($db, 'bookings', 'created_at')) {
            return 'created_at';
        }
        return 'id';
    }
}

if (!function_exists('user_booking_token_column')) {
    function user_booking_token_column(PDO $db): string
    {
        if (!user_table_exists($db, 'bookings')) {
            return '';
        }
        if (user_column_exists($db, 'bookings', 'tokens')) {
            return 'tokens';
        }
        if (user_column_exists($db, 'bookings', 'tockens')) {
            return 'tockens';
        }
        return '';
    }
}

if (!function_exists('user_generate_booking_token')) {
    function user_generate_booking_token(): string
    {
        try {
            $random = strtoupper(bin2hex(random_bytes(4)));
        } catch (Throwable $e) {
            $random = strtoupper(substr(str_replace('.', '', uniqid('', true)), -8));
        }

        return 'BK-' . date('Ymd') . '-' . $random;
    }
}

if (!function_exists('user_get_booking_lock_info')) {
    function user_get_booking_lock_info(PDO $db, int $userId): array
    {
        $result = [
            'blocked' => false,
            'reason' => 'none',
            'message' => '',
            'booking' => null,
        ];

        if ($userId <= 0 || !user_table_exists($db, 'bookings') || !user_column_exists($db, 'bookings', 'user_id')) {
            return $result;
        }

        $hasStatus = user_column_exists($db, 'bookings', 'status');
        $hasStart = user_column_exists($db, 'bookings', 'start_date');
        $hasEnd = user_column_exists($db, 'bookings', 'end_date');
        $orderBy = user_booking_order_column($db);

        $select = ['id'];
        if ($hasStatus) {
            $select[] = 'status';
        } else {
            $select[] = "'pending' AS status";
        }
        if ($hasStart) {
            $select[] = 'start_date';
        }
        if ($hasEnd) {
            $select[] = 'end_date';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM bookings WHERE user_id = ?';
        if ($hasStatus) {
            $sql .= " AND LOWER(COALESCE(status, '')) IN ('pending', 'confirmed', 'approved', 'completed')";
            $sql .= " ORDER BY CASE
                WHEN LOWER(COALESCE(status, '')) IN ('confirmed', 'approved') THEN 0
                WHEN LOWER(COALESCE(status, '')) = 'pending' THEN 1
                ELSE 2
            END, {$orderBy} DESC";
        } else {
            $sql .= " ORDER BY {$orderBy} DESC";
        }
        $sql .= ' LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            return $result;
        }

        $status = user_normalize_booking_status((string)($booking['status'] ?? 'pending'));
        $result['blocked'] = true;
        $result['booking'] = $booking;

        if ($status === 'confirmed') {
            $result['reason'] = 'confirmed';
            $result['message'] = 'You already have a confirmed bed. New booking is disabled.';
        } elseif ($status === 'completed') {
            $result['reason'] = 'completed';
            $result['message'] = 'You already used your booking slot. A new booking is disabled.';
        } else {
            $result['reason'] = 'pending';
            $result['message'] = 'You already have a pending booking request. Wait for admin decision first.';
        }

        return $result;
    }
}

if (!function_exists('user_to_public_asset_path')) {
    function user_to_public_asset_path(?string $path, string $fallback = ''): string
    {
        $value = trim((string)$path);
        if ($value === '') {
            return $fallback;
        }

        if (strpos($value, '../') === 0 || strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
            return $value;
        }

        return '../' . ltrim($value, '/');
    }
}

if (!function_exists('user_fetch_active_semesters')) {
    function user_fetch_active_semesters(PDO $db): array
    {
        if (!user_table_exists($db, 'semester_settings')) {
            return [];
        }

        $required = ['start_date', 'end_date', 'months'];
        foreach ($required as $column) {
            if (!user_column_exists($db, 'semester_settings', $column)) {
                return [];
            }
        }

        $hasId = user_column_exists($db, 'semester_settings', 'id');
        $hasKey = user_column_exists($db, 'semester_settings', 'semester_key');
        $hasName = user_column_exists($db, 'semester_settings', 'semester_name');
        $hasActive = user_column_exists($db, 'semester_settings', 'is_active');

        $columns = [];
        $columns[] = $hasId ? 'id' : '0 AS id';
        $columns[] = $hasKey ? 'semester_key' : 'NULL AS semester_key';
        $columns[] = $hasName ? 'semester_name' : "'' AS semester_name";
        $columns[] = 'start_date';
        $columns[] = 'end_date';
        $columns[] = 'months';

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM semester_settings';
        if ($hasActive) {
            $sql .= ' WHERE COALESCE(is_active, 1) = 1';
        }
        if ($hasKey) {
            $sql .= ' ORDER BY semester_key ASC, start_date ASC, id ASC';
        } else {
            $sql .= ' ORDER BY start_date ASC, id ASC';
        }

        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return [];
        }

        $semesters = [];
        foreach ($rows as $index => $row) {
            $startDate = trim((string)($row['start_date'] ?? ''));
            $endDate = trim((string)($row['end_date'] ?? ''));
            $months = (int)($row['months'] ?? 0);

            if ($startDate === '' || $endDate === '') {
                continue;
            }
            if (strtotime($startDate) === false || strtotime($endDate) === false) {
                continue;
            }
            if (strtotime($endDate) <= strtotime($startDate)) {
                continue;
            }
            if ($months <= 0) {
                continue;
            }

            $key = (int)($row['semester_key'] ?? 0);
            if ($key <= 0) {
                $key = $index + 1;
            }

            $name = trim((string)($row['semester_name'] ?? ''));
            if ($name === '') {
                $name = 'Semester ' . $key;
            }

            $semesters[] = [
                'id' => (int)($row['id'] ?? 0),
                'key' => $key,
                'name' => $name,
                'start_date' => date('Y-m-d', strtotime($startDate)),
                'end_date' => date('Y-m-d', strtotime($endDate)),
                'months' => $months,
            ];
        }

        return $semesters;
    }
}

if (!function_exists('user_find_semester_by_id')) {
    function user_find_semester_by_id(array $semesters, int $semesterId): ?array
    {
        if ($semesterId <= 0) {
            return null;
        }

        foreach ($semesters as $semester) {
            if ((int)($semester['id'] ?? 0) === $semesterId) {
                return $semester;
            }
        }

        return null;
    }
}

if (!function_exists('user_find_current_semester')) {
    function user_find_current_semester(array $semesters, ?string $date = null): ?array
    {
        $targetDate = $date !== null ? trim($date) : date('Y-m-d');
        if ($targetDate === '' || strtotime($targetDate) === false) {
            $targetDate = date('Y-m-d');
        }

        $targetTs = strtotime($targetDate);
        if ($targetTs === false) {
            return null;
        }

        $matched = [];
        foreach ($semesters as $semester) {
            $start = (string)($semester['start_date'] ?? '');
            $end = (string)($semester['end_date'] ?? '');
            if (strtotime($start) === false || strtotime($end) === false) {
                continue;
            }

            $startTs = strtotime($start);
            $endTs = strtotime($end);
            if ($startTs === false || $endTs === false) {
                continue;
            }

            if ($targetTs >= $startTs && $targetTs <= $endTs) {
                $semester['_start_ts'] = $startTs;
                $semester['_end_ts'] = $endTs;
                $semester['_months'] = (int)($semester['months'] ?? 0);
                $matched[] = $semester;
            }
        }

        if (!empty($matched)) {
            usort($matched, static function (array $a, array $b): int {
                $cmpKey = ((int)($a['key'] ?? 0)) <=> ((int)($b['key'] ?? 0));
                if ($cmpKey !== 0) {
                    return $cmpKey;
                }

                $cmpEnd = ((int)($a['_end_ts'] ?? 0)) <=> ((int)($b['_end_ts'] ?? 0));
                if ($cmpEnd !== 0) {
                    return $cmpEnd;
                }

                return ((int)($a['_months'] ?? 0)) <=> ((int)($b['_months'] ?? 0));
            });
            return $matched[0];
        }

        return null;
    }
}

if (!function_exists('user_fetch_hostel_cards')) {
    function user_fetch_hostel_cards(PDO $db, bool $onlyActive = true): array
    {
        if (!user_table_exists($db, 'hostels')) {
            return [];
        }

        $hasHostelStatus = user_column_exists($db, 'hostels', 'status');
        $hasHostelGender = user_column_exists($db, 'hostels', 'gender');

        $hostelSql = 'SELECT h.id, h.name, h.description, h.location, h.hostel_image, h.created_at';
        if ($hasHostelStatus) {
            $hostelSql .= ', h.status';
        } else {
            $hostelSql .= ", 'active' AS status";
        }
        if ($hasHostelGender) {
            $hostelSql .= ', h.gender';
        } else {
            $hostelSql .= ", 'all' AS gender";
        }
        $hostelSql .= ' FROM hostels h';
        if ($onlyActive && $hasHostelStatus) {
            $hostelSql .= " WHERE h.status = 'active'";
        }
        $hostelSql .= ' ORDER BY h.created_at DESC, h.id DESC';

        $hostels = $db->query($hostelSql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($hostels as &$hostel) {
            $hostel['gender'] = user_normalize_gender($hostel['gender'] ?? 'all');
            $hostelStatus = strtolower(trim((string)($hostel['status'] ?? 'active')));
            $hostel['status'] = in_array($hostelStatus, ['active', 'inactive'], true) ? $hostelStatus : 'active';
            $hostel['total_rooms'] = 0;
            $hostel['free_rooms'] = 0;
            $hostel['bed_capacity'] = 0;
            $hostel['created_at_display'] = !empty($hostel['created_at']) ? date('d M Y', strtotime((string)$hostel['created_at'])) : '-';
            $hostel['created_date'] = !empty($hostel['created_at']) ? date('Y-m-d', strtotime((string)$hostel['created_at'])) : '';
            $hostel['hostel_image_url'] = user_to_public_asset_path((string)($hostel['hostel_image'] ?? ''), '../assets/images/logo.png');
            $hostel['has_image'] = !empty($hostel['hostel_image']) ? 'yes' : 'no';
        }
        unset($hostel);

        if (empty($hostels) || !user_table_exists($db, 'rooms')) {
            return $hostels;
        }

        $statsByHostel = [];
        $roomHasAvailable = user_column_exists($db, 'rooms', 'available');
        $roomHasCapacity = user_column_exists($db, 'rooms', 'capacity');
        $bookingHasRoomId = user_column_exists($db, 'bookings', 'room_id');
        $hasBeds = user_table_exists($db, 'beds');
        $bookingHasBedId = user_column_exists($db, 'bookings', 'bed_id');
        $bookingHasStart = user_column_exists($db, 'bookings', 'start_date');
        $bookingHasEnd = user_column_exists($db, 'bookings', 'end_date');

        if ($roomHasAvailable) {
            $rows = $db->query(
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
                    'bed_capacity' => max(0, (int)($row['bed_capacity'] ?? 0)),
                ];
            }
        } elseif ($hasBeds && $bookingHasBedId && $bookingHasStart && $bookingHasEnd) {
            $rows = $db->query(
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
                    $statsByHostel[$hostelId] = ['total_rooms' => 0, 'free_rooms' => 0, 'bed_capacity' => 0];
                }

                $statsByHostel[$hostelId]['total_rooms']++;
                if ((int)($row['free_beds'] ?? 0) > 0) {
                    $statsByHostel[$hostelId]['free_rooms']++;
                }
                $statsByHostel[$hostelId]['bed_capacity'] += (int)($row['total_beds'] ?? 0);
            }
        } elseif ($roomHasCapacity && $bookingHasRoomId) {
            $rows = $db->query(
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
                    $statsByHostel[$hostelId] = ['total_rooms' => 0, 'free_rooms' => 0, 'bed_capacity' => 0];
                }

                $capacity = max(0, (int)($row['capacity'] ?? 0));
                $booked = max(0, (int)($row['booked_count'] ?? 0));

                $statsByHostel[$hostelId]['total_rooms']++;
                if ($capacity > $booked) {
                    $statsByHostel[$hostelId]['free_rooms']++;
                }
                $statsByHostel[$hostelId]['bed_capacity'] += $capacity;
            }
        } else {
            $rows = $db->query(
                'SELECT r.hostel_id, COUNT(*) AS total_rooms
                 FROM rooms r
                 GROUP BY r.hostel_id'
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $hostelId = (int)$row['hostel_id'];
                $statsByHostel[$hostelId] = [
                    'total_rooms' => (int)($row['total_rooms'] ?? 0),
                    'free_rooms' => 0,
                    'bed_capacity' => 0,
                ];
            }
        }

        foreach ($hostels as &$hostel) {
            $hostelId = (int)$hostel['id'];
            if (isset($statsByHostel[$hostelId])) {
                $hostel['total_rooms'] = (int)$statsByHostel[$hostelId]['total_rooms'];
                $hostel['free_rooms'] = (int)$statsByHostel[$hostelId]['free_rooms'];
                $hostel['bed_capacity'] = (int)$statsByHostel[$hostelId]['bed_capacity'];
            }
        }
        unset($hostel);

        return $hostels;
    }
}

if (!function_exists('user_count_available_rooms')) {
    function user_count_available_rooms(PDO $db): int
    {
        $hostels = user_fetch_hostel_cards($db, true);
        $total = 0;
        foreach ($hostels as $hostel) {
            $total += (int)($hostel['free_rooms'] ?? 0);
        }
        return $total;
    }
}

if (!function_exists('user_get_notice_target_context')) {
    function user_get_notice_target_context(PDO $db): array
    {
        $context = [
            'supports_targeting' => false,
            'hostel_id' => 0,
            'room_id' => 0,
            'bed_id' => 0,
        ];

        if (!user_table_exists($db, 'notices')) {
            return $context;
        }

        $context['supports_targeting'] = user_column_exists($db, 'notices', 'target_scope')
            && user_column_exists($db, 'notices', 'hostel_id')
            && user_column_exists($db, 'notices', 'room_id')
            && user_column_exists($db, 'notices', 'bed_id');

        if (!$context['supports_targeting']) {
            return $context;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);

        if (
            $userId > 0
            && user_table_exists($db, 'bookings')
            && user_column_exists($db, 'bookings', 'user_id')
        ) {
            $bookingHasStatus = user_column_exists($db, 'bookings', 'status');
            $bookingHasRoomId = user_column_exists($db, 'bookings', 'room_id');
            $bookingHasBedId = user_column_exists($db, 'bookings', 'bed_id');
            $bookingHasStartDate = user_column_exists($db, 'bookings', 'start_date');
            $bookingHasEndDate = user_column_exists($db, 'bookings', 'end_date');
            $orderBy = user_booking_order_column($db);

            $select = [];
            if ($bookingHasRoomId) {
                $select[] = 'b.room_id';
            }
            if ($bookingHasBedId) {
                $select[] = 'b.bed_id';
            }
            if ($bookingHasStatus) {
                $select[] = 'b.status';
            }
            if ($bookingHasStartDate) {
                $select[] = 'b.start_date';
            }
            if ($bookingHasEndDate) {
                $select[] = 'b.end_date';
            }

            if (!empty($select)) {
                $sql = 'SELECT ' . implode(', ', $select) . ' FROM bookings b WHERE b.user_id = ?';
                $params = [$userId];

                if ($bookingHasStatus) {
                    $sql .= " AND LOWER(COALESCE(b.status, '')) IN ('pending', 'confirmed', 'approved')";
                }
                if ($bookingHasStartDate && $bookingHasEndDate) {
                    $sql .= ' AND CURDATE() >= b.start_date AND CURDATE() < b.end_date';
                }
                $sql .= ' ORDER BY b.' . $orderBy . ' DESC LIMIT 1';

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $context['room_id'] = (int)($booking['room_id'] ?? 0);
                $context['bed_id'] = (int)($booking['bed_id'] ?? 0);
            }
        }

        if (
            $context['bed_id'] > 0
            && user_table_exists($db, 'beds')
            && user_table_exists($db, 'rooms')
        ) {
            $stmt = $db->prepare(
                'SELECT b.room_id, r.hostel_id
                 FROM beds b
                 JOIN rooms r ON r.id = b.room_id
                 WHERE b.id = ?
                 LIMIT 1'
            );
            $stmt->execute([$context['bed_id']]);
            $bedCtx = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bedCtx) {
                $context['room_id'] = (int)($bedCtx['room_id'] ?? $context['room_id']);
                $context['hostel_id'] = (int)($bedCtx['hostel_id'] ?? $context['hostel_id']);
            }
        }

        if ($context['hostel_id'] <= 0 && $context['room_id'] > 0 && user_table_exists($db, 'rooms')) {
            $stmt = $db->prepare('SELECT hostel_id FROM rooms WHERE id = ? LIMIT 1');
            $stmt->execute([$context['room_id']]);
            $context['hostel_id'] = (int)$stmt->fetchColumn();
        }

        return $context;
    }
}

if (!function_exists('user_fetch_notices_for_user')) {
    function user_fetch_notices_for_user(PDO $db, int $limit = 20): array
    {
        $result = [
            'count' => 0,
            'items' => [],
        ];

        if (!user_table_exists($db, 'notices')) {
            return $result;
        }

        $limit = max(1, min(1000, $limit));
        $context = user_get_notice_target_context($db);

        if (empty($context['supports_targeting'])) {
            $result['count'] = (int)$db->query('SELECT COUNT(*) FROM notices')->fetchColumn();
            $stmt = $db->query(
                'SELECT id, title, content, created_at
                 FROM notices
                 ORDER BY created_at DESC, id DESC
                 LIMIT ' . (int)$limit
            );
            $result['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $conditions = ["n.target_scope = 'public'"];
            $params = [];

            if ((int)$context['hostel_id'] > 0) {
                $conditions[] = "(n.target_scope = 'hostel' AND n.hostel_id = ?)";
                $params[] = (int)$context['hostel_id'];
            }
            if ((int)$context['room_id'] > 0) {
                $conditions[] = "(n.target_scope = 'room' AND n.room_id = ?)";
                $params[] = (int)$context['room_id'];
            }
            if ((int)$context['bed_id'] > 0) {
                $conditions[] = "(n.target_scope = 'bed' AND n.bed_id = ?)";
                $params[] = (int)$context['bed_id'];
            }

            $where = implode(' OR ', $conditions);

            $countSql = 'SELECT COUNT(*) FROM notices n WHERE ' . $where;
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $result['count'] = (int)$countStmt->fetchColumn();

            $sql = 'SELECT n.id, n.title, n.content, n.created_at
                    FROM notices n
                    WHERE ' . $where . '
                    ORDER BY n.created_at DESC, n.id DESC
                    LIMIT ' . (int)$limit;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($result['items'] as &$item) {
            $item['title'] = trim((string)($item['title'] ?? ''));
            $item['content'] = trim((string)($item['content'] ?? ''));
            $item['created_at_display'] = !empty($item['created_at'])
                ? date('d M Y, H:i', strtotime((string)$item['created_at']))
                : '-';
        }
        unset($item);

        return $result;
    }
}

if (!function_exists('user_get_latest_notice')) {
    function user_get_latest_notice(PDO $db): ?array
    {
        $noticesState = user_fetch_notices_for_user($db, 1);
        if (empty($noticesState['items'])) {
            return null;
        }
        return $noticesState['items'][0];
    }
}
