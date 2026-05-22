<?php
function render_header($title = "Student Ambassador Club") {
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="/student_ambassador_club_php/assets/style.css">
</head>
<body>
<header>
    <h2>Student Ambassador Club Management</h2>
    <div>Câu lạc bộ Đại sứ Sinh viên</div>
</header>
<nav>
    <a href="/student_ambassador_club_php/index.php">Home</a>
    <a href="/student_ambassador_club_php/events/index.php">Events CRUD</a>
    <a href="/student_ambassador_club_php/registrations/register.php">Event Registration</a>
    <a href="/student_ambassador_club_php/checkin/checkin.php">Check-in</a>
</nav>
<div class="container">
<?php
}

function render_footer() {
?>
</div>
</body>
</html>
<?php
}
?>
