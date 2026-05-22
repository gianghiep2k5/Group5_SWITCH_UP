<?php
require_once "../config/database.php";
require_once "../includes_header.php";

$stmt = $pdo->query("
    SELECT e.*, c.club_name, u.full_name AS creator_name
    FROM events e
    JOIN clubs c ON e.club_id = c.club_id
    JOIN users u ON e.created_by = u.user_id
    ORDER BY e.start_time DESC
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

render_header("Events");
?>

<h3>Danh sách sự kiện / hoạt động</h3>
<a class="btn" href="create.php">+ Thêm sự kiện</a>

<table>
    <tr>
        <th>ID</th>
        <th>Tên sự kiện</th>
        <th>Loại</th>
        <th>Địa điểm</th>
        <th>Thời gian</th>
        <th>Sức chứa</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
    </tr>

    <?php foreach ($events as $event): ?>
        <tr>
            <td><?php echo $event["event_id"]; ?></td>
            <td><?php echo htmlspecialchars($event["event_name"]); ?></td>
            <td><?php echo htmlspecialchars($event["event_type"]); ?></td>
            <td><?php echo htmlspecialchars($event["location"]); ?></td>
            <td>
                <?php echo htmlspecialchars($event["start_time"]); ?>
                <br>to<br>
                <?php echo htmlspecialchars($event["end_time"]); ?>
            </td>
            <td><?php echo $event["capacity"]; ?></td>
            <td><?php echo htmlspecialchars($event["status"]); ?></td>
            <td>
                <a class="btn btn-secondary" href="edit.php?id=<?php echo $event["event_id"]; ?>">Sửa</a>
                <a class="btn btn-danger" href="delete.php?id=<?php echo $event["event_id"]; ?>"
                   onclick="return confirm('Bạn có chắc muốn xóa sự kiện này?')">Xóa</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php render_footer(); ?>
