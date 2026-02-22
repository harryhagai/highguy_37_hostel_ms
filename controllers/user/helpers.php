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

if (!function_exists('user_get_latest_notice')) {
    function user_get_latest_notice(PDO $db): ?array
    {
        if (!user_table_exists($db, 'notices')) {
            return null;
        }

        $supportsTargeting = user_column_exists($db, 'notices', 'target_scope')
            && user_column_exists($db, 'notices', 'hostel_id')
            && user_column_exists($db, 'notices', 'room_id')
            && user_column_exists($db, 'notices', 'bed_id');

        if (!$supportsTargeting) {
            $stmt = $db->query('SELECT title, content, created_at FROM notices ORDER BY created_at DESC, id DESC LIMIT 1');
            $notice = $stmt->fetch(PDO::FETCH_ASSOC);
            return $notice ?: null;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $contextHostelId = 0;
        $contextRoomId = 0;
        $contextBedId = 0;

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

                $contextRoomId = (int)($booking['room_id'] ?? 0);
                $contextBedId = (int)($booking['bed_id'] ?? 0);
            }
        }

        if (
            $contextBedId > 0
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
            $stmt->execute([$contextBedId]);
            $bedCtx = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bedCtx) {
                $contextRoomId = (int)($bedCtx['room_id'] ?? $contextRoomId);
                $contextHostelId = (int)($bedCtx['hostel_id'] ?? $contextHostelId);
            }
        }

        if ($contextHostelId <= 0 && $contextRoomId > 0 && user_table_exists($db, 'rooms')) {
            $stmt = $db->prepare('SELECT hostel_id FROM rooms WHERE id = ? LIMIT 1');
            $stmt->execute([$contextRoomId]);
            $contextHostelId = (int)$stmt->fetchColumn();
        }

        $conditions = ["n.target_scope = 'public'"];
        $params = [];

        if ($contextHostelId > 0) {
            $conditions[] = "(n.target_scope = 'hostel' AND n.hostel_id = ?)";
            $params[] = $contextHostelId;
        }
        if ($contextRoomId > 0) {
            $conditions[] = "(n.target_scope = 'room' AND n.room_id = ?)";
            $params[] = $contextRoomId;
        }
        if ($contextBedId > 0) {
            $conditions[] = "(n.target_scope = 'bed' AND n.bed_id = ?)";
            $params[] = $contextBedId;
        }

        $sql = 'SELECT n.title, n.content, n.created_at
                FROM notices n
                WHERE ' . implode(' OR ', $conditions) . '
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $notice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $notice ?: null;
    }
}
