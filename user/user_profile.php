<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT username, email, phone, profile_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle missing user
if (!$user) {
    echo '<div class="alert alert-danger m-4">User not found.</div>';
    exit;
}

$profile_pic = $user['profile_photo'] ? $user['profile_photo'] : '../assets/images/prof.jpg';

// Handle profile photo update only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $target = '../assets/images/' . $filename;
    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
        $profile_pic = $target;
        $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$profile_pic, $user_id]);
        header("Location: user_dashboard_layout.php?page=profile&success=1");
        exit;
    } else {
        $upload_error = "Failed to upload image. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | HostelPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/user-profile.css">
</head>
<body>
<div class="container d-flex justify-content-center align-items-center profile-page-container">
    <div class="col-lg-9 col-md-11">
        <div class="card profile-card shadow">
            <div class="card-header">
                <h3 class="mb-1"><i class="bi bi-person-circle"></i> My Profile</h3>
                <small>Update your profile picture</small>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success mb-4"><i class="bi bi-check-circle"></i> Profile photo updated successfully!</div>
                <?php endif; ?>
                <?php if (!empty($upload_error)): ?>
                    <div class="alert alert-danger mb-4"><i class="bi bi-x-circle"></i> <?= htmlspecialchars($upload_error) ?></div>
                <?php endif; ?>
                <div class="row">
                    <!-- Left: Profile Picture Update -->
                    <div class="col-md-4 profile-left">
                        <img src="<?= htmlspecialchars($profile_pic) ?>" class="profile-avatar" alt="Profile Photo">
                        <div class="mb-3 w-100">
                            <label class="form-label text-center w-100">Change Photo</label>
                            <form method="post" enctype="multipart/form-data" id="photoForm">
                                <input type="file" class="form-control" id="profilePhotoInput" name="profile_photo" accept="image/*">
                            </form>
                        </div>
                    </div>
                    <!-- Right: Profile Details (read-only) -->
                    <div class="col-md-8 profile-details">
                        <div class="mb-3">
                            <label class="profile-info-label"><i class="bi bi-person"></i> Username</label>
                            <div class="profile-info-value"><?= htmlspecialchars($user['username']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="profile-info-label"><i class="bi bi-envelope"></i> Email</label>
                            <div class="profile-info-value"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="profile-info-label"><i class="bi bi-telephone"></i> Phone</label>
                            <div class="profile-info-value"><?= htmlspecialchars($user['phone']) ?></div>
                        </div>
                        <a href="user_dashboard_layout.php" class="btn btn-secondary mt-4 px-4"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/user-profile.js"></script>
</body>
</html>

