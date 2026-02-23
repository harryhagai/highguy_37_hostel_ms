<?php
if (!function_exists('payment_table_exists')) {
    function payment_table_exists(PDO $db, string $table): bool
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

if (!function_exists('payment_column_exists')) {
    function payment_column_exists(PDO $db, string $table, string $column): bool
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

if (!function_exists('payment_booking_hold_minutes')) {
    function payment_booking_hold_minutes(): int
    {
        return 30;
    }
}

if (!function_exists('payment_proof_table_ready')) {
    function payment_proof_table_ready(PDO $db): bool
    {
        return payment_table_exists($db, 'booking_payment_proofs')
            && payment_column_exists($db, 'booking_payment_proofs', 'booking_id')
            && payment_column_exists($db, 'booking_payment_proofs', 'transaction_id');
    }
}

if (!function_exists('payment_control_numbers_table_ready')) {
    function payment_control_numbers_table_ready(PDO $db): bool
    {
        return payment_table_exists($db, 'payment_control_numbers')
            && payment_column_exists($db, 'payment_control_numbers', 'network_name')
            && payment_column_exists($db, 'payment_control_numbers', 'control_number');
    }
}

if (!function_exists('payment_hold_timestamp_expression')) {
    function payment_hold_timestamp_expression(PDO $db, string $alias = 'b'): string
    {
        $hasBookingDate = payment_column_exists($db, 'bookings', 'booking_date');
        $hasCreatedAt = payment_column_exists($db, 'bookings', 'created_at');

        if ($hasCreatedAt) {
            return "{$alias}.created_at";
        }
        if ($hasBookingDate) {
            return "{$alias}.booking_date";
        }
        return '';
    }
}

if (!function_exists('payment_has_transaction')) {
    function payment_has_transaction(?array $proof): bool
    {
        if (!is_array($proof)) {
            return false;
        }
        return trim((string)($proof['transaction_id'] ?? '')) !== '';
    }
}

if (!function_exists('payment_seconds_remaining')) {
    function payment_seconds_remaining(?string $holdFrom, int $holdMinutes = 30): int
    {
        $holdFrom = trim((string)$holdFrom);
        if ($holdFrom === '' || strtotime($holdFrom) === false) {
            return 0;
        }

        $deadlineTs = strtotime($holdFrom . ' +' . max(1, $holdMinutes) . ' minutes');
        if ($deadlineTs === false) {
            return 0;
        }

        return max(0, $deadlineTs - time());
    }
}

if (!function_exists('payment_fetch_control_numbers')) {
    function payment_fetch_control_numbers(PDO $db, bool $onlyActive = true): array
    {
        if (!payment_control_numbers_table_ready($db)) {
            return [];
        }

        $hasIsActive = payment_column_exists($db, 'payment_control_numbers', 'is_active');
        $hasSortOrder = payment_column_exists($db, 'payment_control_numbers', 'sort_order');
        $hasUpdatedAt = payment_column_exists($db, 'payment_control_numbers', 'updated_at');
        $hasNetworkIcon = payment_column_exists($db, 'payment_control_numbers', 'network_icon');
        $hasCompanyName = payment_column_exists($db, 'payment_control_numbers', 'company_name');
        $hasInfo = payment_column_exists($db, 'payment_control_numbers', 'info');

        $select = [
            'id',
            'network_name',
            $hasNetworkIcon ? 'network_icon' : "'' AS network_icon",
            'control_number',
            $hasCompanyName ? 'company_name' : "'' AS company_name",
            $hasInfo ? 'info' : "'' AS info",
            $hasIsActive ? 'is_active' : '1 AS is_active',
            $hasUpdatedAt ? 'updated_at' : 'NULL AS updated_at',
        ];

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM payment_control_numbers';
        if ($onlyActive && $hasIsActive) {
            $sql .= ' WHERE COALESCE(is_active, 1) = 1';
        }
        if ($hasSortOrder) {
            $sql .= ' ORDER BY COALESCE(sort_order, 9999) ASC, id ASC';
        } else {
            $sql .= ' ORDER BY id ASC';
        }

        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('payment_expire_unpaid_pending_bookings')) {
    function payment_expire_unpaid_pending_bookings(PDO $db, int $holdMinutes = 30): array
    {
        $result = ['expired_count' => 0];

        if (!payment_table_exists($db, 'bookings') || !payment_column_exists($db, 'bookings', 'status')) {
            return $result;
        }

        $holdExpr = payment_hold_timestamp_expression($db, 'b');
        if ($holdExpr === '') {
            return $result;
        }

        $proofReady = payment_proof_table_ready($db);
        $hasRoomId = payment_column_exists($db, 'bookings', 'room_id');
        $hasBedId = payment_column_exists($db, 'bookings', 'bed_id');
        $roomAvailabilityReady = $hasRoomId
            && payment_table_exists($db, 'rooms')
            && payment_column_exists($db, 'rooms', 'available');

        $sql = 'SELECT b.id';
        if ($hasRoomId) {
            $sql .= ', b.room_id';
        } else {
            $sql .= ', 0 AS room_id';
        }
        if ($hasBedId) {
            $sql .= ', b.bed_id';
        } else {
            $sql .= ', 0 AS bed_id';
        }
        $sql .= ' FROM bookings b';
        if ($proofReady) {
            $sql .= ' LEFT JOIN booking_payment_proofs bp ON bp.booking_id = b.id';
        }
        $sql .= " WHERE LOWER(COALESCE(b.status, '')) = 'pending'
                  AND TIMESTAMPDIFF(MINUTE, {$holdExpr}, NOW()) >= ?";

        $params = [max(1, $holdMinutes)];
        if ($proofReady) {
            $sql .= " AND TRIM(COALESCE(bp.transaction_id, '')) = ''";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return $result;
        }

        $bookingIds = [];
        $roomReleaseMap = [];
        foreach ($rows as $row) {
            $bookingId = (int)($row['id'] ?? 0);
            if ($bookingId <= 0) {
                continue;
            }
            $bookingIds[] = $bookingId;

            if (!$roomAvailabilityReady) {
                continue;
            }
            $roomId = (int)($row['room_id'] ?? 0);
            $bedId = (int)($row['bed_id'] ?? 0);
            if ($roomId > 0 && $bedId <= 0) {
                $roomReleaseMap[$roomId] = (int)($roomReleaseMap[$roomId] ?? 0) + 1;
            }
        }

        if (empty($bookingIds)) {
            return $result;
        }

        try {
            $db->beginTransaction();

            $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
            $updateSql = "UPDATE bookings
                          SET status = 'cancelled'
                          WHERE id IN ({$placeholders})
                            AND LOWER(COALESCE(status, '')) = 'pending'";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute($bookingIds);
            $result['expired_count'] = (int)$updateStmt->rowCount();

            if ($roomAvailabilityReady && !empty($roomReleaseMap)) {
                $roomStmt = $db->prepare(
                    'UPDATE rooms
                     SET available = COALESCE(available, 0) + ?
                     WHERE id = ?'
                );
                foreach ($roomReleaseMap as $roomId => $count) {
                    if ($count > 0) {
                        $roomStmt->execute([(int)$count, (int)$roomId]);
                    }
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }

        return $result;
    }
}
