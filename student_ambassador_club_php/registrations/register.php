<?php
require_once "../config/database.php";
require_once "../includes_header.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $event_id = intval($_POST["event_id"]);
    $member_id = intval($_POST["member_id"]);

    // Rule 1: prevent duplicate registration
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND member_id = ?");
    $stmt->execute([$event_id, $member_id]);
    $alreadyRegistered = $stmt->fetchColumn();

    // Rule 2: prevent registration if event is full
    $stmt = $pdo->prepare("
        SELECT e.capacity, COUNT(r.registration_id) AS total_registered
        FROM events e
        LEFT JOIN event_registrations r
            ON e.event_id = r.event_id
            AND r.registration_status IN ('registered', 'approved')
        WHERE e.event_id = ?
        GROUP BY e.event_id, e.capacity
    ");
    $stmt->execute([$event_id]);
    $eventCapacity = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($alreadyRegistered > 0) {
        $error = "Thành viên này đã đăng ký sự kiện này rồi. Không được đăng ký trùng.";
    } elseif ($eventCapacity && $eventCapacity["total_registered"] >= $eventCapacity["capacity"]) {
        $error = "Sự kiện đã đủ sức chứa. Không thể đăng ký thêm.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO event_registrations (event_id, member_id, registration_status, note)
            VALUES (?, ?, 'registered', 'Registered from demo form')
        ");
        $stmt->execute([$event_id, $member_id]);
        $message = "Đăng ký sự kiện thành công.";
    }
}

$events = $pdo->query("SELECT event_id, event_name, capacity FROM events WHERE status = 'published' ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);
$members = $pdo->query("
    SELECT cm.member_id, u.full_name, cm.member_code
    FROM club_members cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.status = 'active'
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

$registrations = $pdo->query("
    SELECT r.registration_id, e.event_name, u.full_name, cm.member_code, r.registration_status, r.registered_at
    FROM event_registrations r
    JOIN events e ON r.event_id = e.event_id
    JOIN club_members cm ON r.member_id = cm.member_id
    JOIN users u ON cm.user_id = u.user_id
    ORDER BY r.registered_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

render_header("Event Registration");
?>

<h3>Đăng ký tham gia sự kiện</h3>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <label>Sự kiện</label>
    <select name="event_id" required>
        <?php foreach ($events as $event): ?>
            <option value="<?php echo $event["event_id"]; ?>">
                <?php echo htmlspecialchars($event["event_name"]); ?> - Capacity: <?php echo $event["capacity"]; ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Thành viên CLB</label>
    <select name="member_id" required>
        <?php foreach ($members as $member): ?>
            <option value="<?php echo $member["member_id"]; ?>">
                <?php echo htmlspecialchars($member["member_code"] . " - " . $member["full_name"]); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Đăng ký</button>
</form>

<h4>Danh sách đăng ký</h4>

<table>
    <tr>
        <th>ID</th>
        <th>Sự kiện</th>
        <th>Thành viên</th>
        <th>Mã thành viên</th>
        <th>Trạng thái</th>
        <th>Thời gian đăng ký</th>
    </tr>
    <?php foreach ($registrations as $r): ?>
        <tr>
            <td><?php echo $r["registration_id"]; ?></td>
            <td><?php echo htmlspecialchars($r["event_name"]); ?></td>
            <td><?php echo htmlspecialchars($r["full_name"]); ?></td>
            <td><?php echo htmlspecialchars($r["member_code"]); ?></td>
            <td><?php echo htmlspecialchars($r["registration_status"]); ?></td>
            <td><?php echo htmlspecialchars($r["registered_at"]); ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php render_footer(); ?>
