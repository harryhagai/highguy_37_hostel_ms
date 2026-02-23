<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../../config/db_connection.php';
}
require_once __DIR__ . '/../../includes/admin_post_guard.php';
require_once __DIR__ . '/../../includes/payment_helpers.php';

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};

$success = '';
$errors = [];
$adminId = (int)($_SESSION['user_id'] ?? 0);

payment_expire_unpaid_pending_bookings($pdo, payment_booking_hold_minutes());

$flash = admin_prg_consume('application_management');
if (is_array($flash)) {
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $success = (string)($flash['success'] ?? '');
}

$proofTableReady = payment_proof_table_ready($pdo);
$proofHasStatus = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'status');
$proofHasSmsText = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'sms_text');
$proofHasSubmittedAt = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'submitted_at');
$proofHasVerifiedAt = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'verified_at');
$proofHasAdminNote = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'admin_note');
$proofHasVerifiedBy = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'verified_by');
$proofHasUpdatedAt = $proofTableReady && payment_column_exists($pdo, 'booking_payment_proofs', 'updated_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        $errors[] = 'Invalid application ID.';
    } else {
        $stmt = $pdo->prepare('SELECT id, status FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $errors[] = 'Application not found.';
        } else {
            $bookingStatus = strtolower(trim((string)($booking['status'] ?? 'pending')));
            if ($bookingStatus === 'approved') {
                $bookingStatus = 'confirmed';
            }

            if ($action === 'approve_application') {
                if ($bookingStatus !== 'pending') {
                    $success = 'Application is already processed.';
                } elseif (!$proofTableReady) {
                    $errors[] = 'Payment proof table is missing. Run payment migration first.';
                } else {
                    $proofStmt = $pdo->prepare('SELECT id, transaction_id FROM booking_payment_proofs WHERE booking_id = ? LIMIT 1');
                    $proofStmt->execute([$id]);
                    $proofRow = $proofStmt->fetch(PDO::FETCH_ASSOC);
                    $transactionId = trim((string)($proofRow['transaction_id'] ?? ''));

                    if ($transactionId === '') {
                        $errors[] = 'Cannot approve. Transaction ID is missing for this application.';
                    } else {
                        try {
                            $pdo->beginTransaction();

                            $updateBooking = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND LOWER(COALESCE(status, '')) = 'pending'");
                            $updateBooking->execute([$id]);

                            if ($proofRow) {
                                $set = [];
                                if ($proofHasStatus) {
                                    $set[] = "status = 'verified'";
                                }
                                if ($proofHasVerifiedAt) {
                                    $set[] = 'verified_at = NOW()';
                                }
                                if ($proofHasVerifiedBy) {
                                    $set[] = 'verified_by = ' . (int)$adminId;
                                }
                                if ($proofHasUpdatedAt) {
                                    $set[] = 'updated_at = NOW()';
                                }
                                if ($proofHasAdminNote) {
                                    $set[] = 'admin_note = NULL';
                                }

                                if (!empty($set)) {
                                    $proofUpdateSql = 'UPDATE booking_payment_proofs SET ' . implode(', ', $set) . ' WHERE id = ?';
                                    $updateProof = $pdo->prepare($proofUpdateSql);
                                    $updateProof->execute([(int)$proofRow['id']]);
                                }
                            }

                            $pdo->commit();
                            $success = $updateBooking->rowCount() ? 'Application approved successfully.' : 'Application is already processed.';
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $errors[] = 'Failed to approve application. Please try again.';
                        }
                    }
                }
            }

            if ($action === 'reject_application') {
                if ($bookingStatus !== 'pending') {
                    $success = 'Application is already processed.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $updateBooking = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND LOWER(COALESCE(status, '')) = 'pending'");
                        $updateBooking->execute([$id]);

                        if ($proofTableReady) {
                            $proofStmt = $pdo->prepare('SELECT id FROM booking_payment_proofs WHERE booking_id = ? LIMIT 1');
                            $proofStmt->execute([$id]);
                            $proofRow = $proofStmt->fetch(PDO::FETCH_ASSOC);
                            if ($proofRow) {
                                $set = [];
                                if ($proofHasStatus) {
                                    $set[] = "status = 'rejected'";
                                }
                                if ($proofHasVerifiedAt) {
                                    $set[] = 'verified_at = NOW()';
                                }
                                if ($proofHasVerifiedBy) {
                                    $set[] = 'verified_by = ' . (int)$adminId;
                                }
                                if ($proofHasUpdatedAt) {
                                    $set[] = 'updated_at = NOW()';
                                }

                                if (!empty($set)) {
                                    $proofUpdateSql = 'UPDATE booking_payment_proofs SET ' . implode(', ', $set) . ' WHERE id = ?';
                                    $updateProof = $pdo->prepare($proofUpdateSql);
                                    $updateProof->execute([(int)$proofRow['id']]);
                                }
                            }
                        }

                        $pdo->commit();
                        $success = $updateBooking->rowCount() ? 'Application rejected successfully.' : 'Application is already processed.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errors[] = 'Failed to reject application. Please try again.';
                    }
                }
            }
        }
    }

    admin_prg_redirect('application_management', [
        'errors' => $errors,
        'success' => $success,
    ]);
}

$hasBookingRoom = $columnExists($pdo, 'bookings', 'room_id');
$hasBookingBed = $columnExists($pdo, 'bookings', 'bed_id');
$hasUserPhone = $columnExists($pdo, 'users', 'phone');
$bookingTokenColumn = '';
if ($columnExists($pdo, 'bookings', 'tokens')) {
    $bookingTokenColumn = 'tokens';
} elseif ($columnExists($pdo, 'bookings', 'tockens')) {
    $bookingTokenColumn = 'tockens';
}

$orderColumn = $columnExists($pdo, 'bookings', 'created_at')
    ? 'b.created_at'
    : ($columnExists($pdo, 'bookings', 'booking_date') ? 'b.booking_date' : 'b.id');

$bookingTokenSelect = $bookingTokenColumn !== ''
    ? 'b.' . $bookingTokenColumn . ' AS application_token'
    : "'' AS application_token";
$userPhoneSelect = $hasUserPhone ? 'u.phone' : "'' AS phone";

$proofSelect = "'' AS payment_transaction_id, '' AS payment_sms_text, 'not_submitted' AS payment_status, NULL AS payment_submitted_at, NULL AS payment_verified_at, '' AS payment_admin_note";
$proofJoin = '';
if ($proofTableReady) {
    $proofSelect = 'TRIM(COALESCE(bp.transaction_id, \'\')) AS payment_transaction_id, '
        . ($proofHasSmsText ? "COALESCE(bp.sms_text, '')" : "''") . ' AS payment_sms_text, '
        . ($proofHasStatus ? "LOWER(COALESCE(bp.status, CASE WHEN TRIM(COALESCE(bp.transaction_id, '')) <> '' THEN 'pending' ELSE 'not_submitted' END))" : "CASE WHEN TRIM(COALESCE(bp.transaction_id, '')) <> '' THEN 'pending' ELSE 'not_submitted' END") . ' AS payment_status, '
        . ($proofHasSubmittedAt ? 'bp.submitted_at' : 'NULL') . ' AS payment_submitted_at, '
        . ($proofHasVerifiedAt ? 'bp.verified_at' : 'NULL') . ' AS payment_verified_at, '
        . ($proofHasAdminNote ? "COALESCE(bp.admin_note, '')" : "''") . ' AS payment_admin_note';
    $proofJoin = ' LEFT JOIN booking_payment_proofs bp ON bp.booking_id = b.id';
}

if ($hasBookingBed) {
    $stmt = $pdo->query(
        "SELECT
            b.id,
            {$bookingTokenSelect},
            u.username,
            u.email,
            {$userPhoneSelect},
            r.room_number,
            bd.bed_number,
            h.name AS hostel_name,
            b.status,
            b.booking_date,
            b.created_at,
            {$proofSelect}
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN beds bd ON b.bed_id = bd.id
        JOIN rooms r ON bd.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        {$proofJoin}
        ORDER BY {$orderColumn} DESC"
    );
} elseif ($hasBookingRoom) {
    $stmt = $pdo->query(
        "SELECT
            b.id,
            {$bookingTokenSelect},
            u.username,
            u.email,
            {$userPhoneSelect},
            r.room_number,
            '' AS bed_number,
            h.name AS hostel_name,
            b.status,
            b.booking_date,
            b.created_at,
            {$proofSelect}
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN rooms r ON b.room_id = r.id
        JOIN hostels h ON r.hostel_id = h.id
        {$proofJoin}
        ORDER BY {$orderColumn} DESC"
    );
} else {
    $stmt = null;
    $errors[] = 'Bookings schema is not compatible: missing room_id/bed_id.';
}

$applications = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

foreach ($applications as &$application) {
    $application['payment_status'] = strtolower(trim((string)($application['payment_status'] ?? 'not_submitted')));
    if (!in_array($application['payment_status'], ['pending', 'verified', 'rejected', 'not_submitted'], true)) {
        $application['payment_status'] = trim((string)($application['payment_transaction_id'] ?? '')) !== '' ? 'pending' : 'not_submitted';
    }
    $application['payment_transaction_id'] = trim((string)($application['payment_transaction_id'] ?? ''));
    $application['payment_submitted_at_display'] = !empty($application['payment_submitted_at'])
        ? date('d M Y H:i', strtotime((string)$application['payment_submitted_at']))
        : '-';
    $application['payment_verified_at_display'] = !empty($application['payment_verified_at'])
        ? date('d M Y H:i', strtotime((string)$application['payment_verified_at']))
        : '-';
}
unset($application);

return [
    'errors' => $errors,
    'success' => $success,
    'applications' => $applications,
    'proof_table_ready' => $proofTableReady,
];
