<?php
require_once "../config/database.php";

$id = $_GET["id"] ?? null;
if (!$id) {
    die("Missing event ID");
}

// Business rule: do not delete event if it already has registrations or check-ins
$stmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM event_registrations WHERE event_id = ?) AS registration_count,
        (SELECT COUNT(*) FROM checkin_logs WHERE event_id = ?) AS checkin_count
");
$stmt->execute([$id, $id]);
$count = $stmt->fetch(PDO::FETCH_ASSOC);

if ($count["registration_count"] > 0 || $count["checkin_count"] > 0) {
    die("Không thể xóa sự kiện vì đã có đăng ký hoặc check-in liên quan. Đây là business rule backend.");
}

$stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;
?>
