<?php
require_once "includes_header.php";
render_header("Home");
?>

<h3>Worksheet 12 Backend Demo</h3>

<p>
    Đây là project PHP + MySQL demo cho đề tài
    <strong>Câu lạc bộ Đại sứ Sinh viên</strong>.
</p>

<h4>Module đã làm trong Worksheet 12</h4>
<ul>
    <li>CRUD bảng <code>events</code></li>
    <li>Đăng ký tham gia sự kiện bằng bảng <code>event_registrations</code></li>
    <li>Check-in sự kiện bằng bảng <code>checkin_logs</code></li>
    <li>Business rule backend:
        <ul>
            <li>Không cho đăng ký trùng cùng một sự kiện.</li>
            <li>Không cho đăng ký nếu sự kiện đã đủ sức chứa.</li>
            <li>Không cho check-in trùng.</li>
        </ul>
    </li>
</ul>

<h4>Cách chạy</h4>
<ol>
    <li>Import file <code>student_ambassador_club_database.sql</code> vào phpMyAdmin.</li>
    <li>Copy thư mục này vào <code>htdocs</code>.</li>
    <li>Mở trình duyệt: <code>http://localhost/student_ambassador_club_php/</code></li>
</ol>

<?php render_footer(); ?>
