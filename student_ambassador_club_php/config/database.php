<?php
// Database connection for Student Ambassador Club Management

$host = "localhost";
$dbname = "student_ambassador_club";
$username = "root";
$password = ""; // XAMPP default password is empty

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
