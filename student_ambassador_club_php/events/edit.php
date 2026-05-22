<?php
require_once "../config/database.php";
require_once "../includes_header.php";

$id = $_GET["id"] ?? null;
if (!$id) {
    die("Missing event ID");
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $event_name = trim($_POST["event_name"]);
    $event_type = $_POST["event_type"];
    $description = trim($_POST["description"]);
    $location = trim($_POST["location"]);
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $capacity = intval($_POST["capacity"]);
    $status = $_POST["status"];

    if ($event_name === "" || $location === "" || $start_time === "" || $end_time === "") {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } elseif ($capacity <= 0) {
        $error = "Sức chứa phải lớn hơn 0.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error = "Thời gian kết thúc phải sau thời gian bắt đầu.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE events
            SET event_name = ?, event_type = ?, description = ?, location = ?,
                start_time = ?, end_time = ?, capacity = ?, status = ?
            WHERE event_id = ?
        ");
        $stmt->execute([$event_name, $event_type, $description, $location, $start_time, $end_time, $capacity, $status, $id]);

        header("Location: index.php");
        exit;
    }
}

render_header("Edit Event");
?>

<h3>Sửa sự kiện</h3>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <label>Tên sự kiện</label>
    <input type="text" name="event_name" value="<?php echo htmlspecialchars($event["event_name"]); ?>" required>

    <label>Loại sự kiện</label>
    <select name="event_type">
        <?php
        $types = ["open_day", "campus_tour", "workshop", "training", "admission_support", "other"];
        foreach ($types as $type):
        ?>
            <option value="<?php echo $type; ?>" <?php if ($event["event_type"] === $type) echo "selected"; ?>>
                <?php echo $type; ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Mô tả</label>
    <textarea name="description"><?php echo htmlspecialchars($event["description"]); ?></textarea>

    <label>Địa điểm</label>
    <input type="text" name="location" value="<?php echo htmlspecialchars($event["location"]); ?>" required>

    <label>Thời gian bắt đầu</label>
    <input type="datetime-local" name="start_time" value="<?php echo date('Y-m-d\TH:i', strtotime($event["start_time"])); ?>" required>

    <label>Thời gian kết thúc</label>
    <input type="datetime-local" name="end_time" value="<?php echo date('Y-m-d\TH:i', strtotime($event["end_time"])); ?>" required>

    <label>Sức chứa</label>
    <input type="number" name="capacity" value="<?php echo $event["capacity"]; ?>" min="1" required>

    <label>Trạng thái</label>
    <select name="status">
        <?php
        $statuses = ["draft", "published", "completed", "cancelled"];
        foreach ($statuses as $status):
        ?>
            <option value="<?php echo $status; ?>" <?php if ($event["status"] === $status) echo "selected"; ?>>
                <?php echo $status; ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Cập nhật</button>
    <a class="btn btn-secondary" href="index.php">Quay lại</a>
</form>

<?php render_footer(); ?>
