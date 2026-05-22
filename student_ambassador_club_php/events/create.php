<?php
require_once "../config/database.php";
require_once "../includes_header.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $club_id = $_POST["club_id"];
    $created_by = $_POST["created_by"];
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
            INSERT INTO events
            (club_id, created_by, event_name, event_type, description, location, start_time, end_time, capacity, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$club_id, $created_by, $event_name, $event_type, $description, $location, $start_time, $end_time, $capacity, $status]);

        header("Location: index.php");
        exit;
    }
}

$clubs = $pdo->query("SELECT club_id, club_name FROM clubs")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT user_id, full_name FROM users WHERE role IN ('admin', 'department_staff', 'club_leader')")->fetchAll(PDO::FETCH_ASSOC);

render_header("Create Event");
?>

<h3>Thêm sự kiện mới</h3>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <label>CLB</label>
    <select name="club_id" required>
        <?php foreach ($clubs as $club): ?>
            <option value="<?php echo $club["club_id"]; ?>"><?php echo htmlspecialchars($club["club_name"]); ?></option>
        <?php endforeach; ?>
    </select>

    <label>Người tạo</label>
    <select name="created_by" required>
        <?php foreach ($users as $user): ?>
            <option value="<?php echo $user["user_id"]; ?>"><?php echo htmlspecialchars($user["full_name"]); ?></option>
        <?php endforeach; ?>
    </select>

    <label>Tên sự kiện</label>
    <input type="text" name="event_name" required>

    <label>Loại sự kiện</label>
    <select name="event_type">
        <option value="open_day">Open Day</option>
        <option value="campus_tour">Campus Tour</option>
        <option value="workshop">Workshop</option>
        <option value="training">Training</option>
        <option value="admission_support">Admission Support</option>
        <option value="other">Other</option>
    </select>

    <label>Mô tả</label>
    <textarea name="description"></textarea>

    <label>Địa điểm</label>
    <input type="text" name="location" required>

    <label>Thời gian bắt đầu</label>
    <input type="datetime-local" name="start_time" required>

    <label>Thời gian kết thúc</label>
    <input type="datetime-local" name="end_time" required>

    <label>Sức chứa</label>
    <input type="number" name="capacity" value="30" min="1" required>

    <label>Trạng thái</label>
    <select name="status">
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
    </select>

    <button type="submit">Lưu</button>
    <a class="btn btn-secondary" href="index.php">Quay lại</a>
</form>

<?php render_footer(); ?>
