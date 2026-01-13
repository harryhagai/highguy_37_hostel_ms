<?php
// seed_admin.php
// Include your PDO connection (update the path if needed)
require_once __DIR__ . '/../config/db_connection.php';


// Admin credentials
$username = "hagai";
$password_plain = "44242444";
$email = "hngobey@gmail.com"; // You can change this if you want
$role = "admin";

// Check if admin already exists (by username or email)
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
$stmt->execute([
    ':username' => $username,
    ':email' => $email
]);
if ($stmt->fetch()) {
    echo "Admin user already exists.\n";
    exit;
}

// Hash the password securely
$hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);

// Insert the admin user
$stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
$success = $stmt->execute([
    ':username' => $username,
    ':email'    => $email,
    ':password' => $hashed_password,
    ':role'     => $role
]);

if ($success) {
    echo "Admin user seeded successfully.\n";
} else {
    echo "Failed to seed admin user.\n";
}
?>
