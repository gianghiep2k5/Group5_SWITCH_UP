<?php
require_once __DIR__ . '/app.php';

$DB_HOST = '127.0.0.1';
$DB_NAME = 'ds_chatbot';
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP thường để trống. Nếu có mật khẩu thì sửa ở đây.
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Check config/db.php and import database/schema.sql + seed.sql. Error: ' . htmlspecialchars($e->getMessage()));
}
