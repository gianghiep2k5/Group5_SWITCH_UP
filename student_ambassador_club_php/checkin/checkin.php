<?php
require_once "../config/database.php";
require_once "../includes_header.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $event_id = intval($_POST["event_id"]);
    $member_id = intval($_POST["member_id"]);
    $checked_by = intval($_POST["checked_by"]);

    // Business rule: prevent duplicate check-in
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM checkin_logs WHERE event_id = ? AND member_id = ?");
    $stmt->execute([$event_id, $member_id]);
    $alreadyCheckedIn = $stmt->fetchColumn();

    if ($alreadyCheckedIn > 0) {
        $error = "Thành viên này đã check-in sự kiện này rồi. Không được check-in trùng.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO checkin_logs (event_id, member_id, checked_by, checkin_method, note)
            VALUES (?, ?, ?, 'manual', 'Check-in from demo form')
        ");
        $stmt->execute([$event_id, $member_id, $checked_by]);
        $message = "Check-in thành công.";

        // Optional: add points after valid check-in
        $pointStmt = $pdo->prepare("SELECT points FROM activity_point_rules WHERE activity_type = 'event_checkin' AND status = 'active'");
        $pointStmt->execute();
        $points = intval($pointStmt->fetchColumn());

        $updatePoint = $pdo->prepare("
            INSERT INTO student_points (member_id, semester, total_points)
            VALUES (?, 'Spring 2026', ?)
            ON DUPLICATE KEY UPDATE total_points = total_points + VALUES(total_points)
        ");
        $updatePoint->execute([$member_id, $points]);
    }
}

$events = $pdo->query("SELECT event_id, event_name FROM events WHERE status IN ('published', 'completed') ORDER BY start_time")->fetchAll(PDO::FETCH_ASSOC);
$members = $pdo->query("
    SELECT cm.member_id, u.full_name, cm.member_code
    FROM club_members cm
    JOIN users u ON cm.user_id = u.user_id
    WHERE cm.status = 'active'
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);
$checkers = $pdo->query("SELECT user_id, full_name FROM users WHERE role IN ('admin', 'department_staff', 'club_leader')")->fetchAll(PDO::FETCH_ASSOC);

$logs = $pdo->query("
    SELECT cl.checkin_id, e.event_name, u.full_name AS member_name, cm.member_code,
           checker.full_name AS checked_by_name, cl.checkin_time, cl.checkin_method
    FROM checkin_logs cl
    JOIN events e ON cl.event_id = e.event_id
    JOIN club_members cm ON cl.member_id = cm.member_id
    JOIN users u ON cm.user_id = u.user_id
    JOIN users checker ON cl.checked_by = checker.user_id
    ORDER BY cl.checkin_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

render_header("Check-in");
?>

<h3>Check-in sự kiện</h3>

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
                <?php echo htmlspecialchars($event["event_name"]); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Thành viên check-in</label>
    <select name="member_id" required>
        <?php foreach ($members as $member): ?>
            <option value="<?php echo $member["member_id"]; ?>">
                <?php echo htmlspecialchars($member["member_code"] . " - " . $member["full_name"]); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Người xác nhận check-in</label>
    <select name="checked_by" required>
        <?php foreach ($checkers as $checker): ?>
            <option value="<?php echo $checker["user_id"]; ?>">
                <?php echo htmlspecialchars($checker["full_name"]); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Check-in</button>
</form>

<h4>Lịch sử check-in</h4>

<table>
    <tr>
        <th>ID</th>
        <th>Sự kiện</th>
        <th>Thành viên</th>
        <th>Mã thành viên</th>
        <th>Người xác nhận</th>
        <th>Thời gian</th>
        <th>Phương thức</th>
    </tr>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo $log["checkin_id"]; ?></td>
            <td><?php echo htmlspecialchars($log["event_name"]); ?></td>
            <td><?php echo htmlspecialchars($log["member_name"]); ?></td>
            <td><?php echo htmlspecialchars($log["member_code"]); ?></td>
            <td><?php echo htmlspecialchars($log["checked_by_name"]); ?></td>
            <td><?php echo htmlspecialchars($log["checkin_time"]); ?></td>
            <td><?php echo htmlspecialchars($log["checkin_method"]); ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php render_footer(); ?>
