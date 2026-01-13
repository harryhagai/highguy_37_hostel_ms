<?php
$host = 'localhost';
$db   = 'hostel_management';
$user = 'root'; // Change this if your username is different
$pass = 'hagai44242444';     // Set your password if you have one
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // In production, avoid showing raw errors. Log them instead.
    die("Database connection failed.");
}
?>
