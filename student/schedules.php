<?php
require_once __DIR__ . '/../includes/auth.php';
require_student();
$title = 'Schedule';
$user = current_user();
$stmt = $pdo->prepare("SELECT sc.*, c.name class_name, co.code course_code, co.name course_name, u.full_name teacher_name
                       FROM schedules sc
                       JOIN classes c ON c.id=sc.class_id
                       JOIN courses co ON co.id=c.course_id
                       JOIN users u ON u.id=c.teacher_id
                       JOIN class_students cs ON cs.class_id=c.id
                       WHERE cs.student_id=? AND cs.status='active'
                       ORDER BY sc.start_time ASC");
$stmt->execute([$user['id']]);
$schedules = $stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head"><div><h2>Learning Schedule</h2><p class="page-intro">All sessions from the classes you are currently enrolled in.</p></div></div>
    <?php if (!$schedules): ?><div class="empty-state">No schedule available.</div><?php endif; ?>
    <?php if ($schedules): ?><div class="table-wrap"><table class="table">
        <tr><th>Lesson</th><th>Course</th><th>Class</th><th>Time</th><th>Room</th><th>Lecturer</th><th>Note</th></tr>
        <?php foreach($schedules as $s): ?>
            <tr>
                <td><b><?= e($s['title']) ?></b></td>
                <td><?= e($s['course_code'].' - '.$s['course_name']) ?></td>
                <td><?= e($s['class_name']) ?></td>
                <td><?= e($s['start_time'].' → '.$s['end_time']) ?></td>
                <td><?= e($s['room'] ?? '-') ?></td>
                <td><?= e($s['teacher_name']) ?></td>
                <td><?= e($s['note'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
