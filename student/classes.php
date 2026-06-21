<?php
require_once __DIR__ . '/../includes/auth.php';
require_student();
$title = 'My Classes';
$user = current_user();
$stmt = $pdo->prepare("SELECT c.*, co.code, co.name course_name, u.full_name teacher_name, cs.status, cs.joined_at,
                              COUNT(DISTINCT sc.id) schedule_count
                       FROM class_students cs
                       JOIN classes c ON c.id=cs.class_id
                       JOIN courses co ON co.id=c.course_id
                       JOIN users u ON u.id=c.teacher_id
                       LEFT JOIN schedules sc ON sc.class_id=c.id
                       WHERE cs.student_id=?
                       GROUP BY cs.id
                       ORDER BY c.is_active DESC, c.start_date DESC");
$stmt->execute([$user['id']]);
$classes = $stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head"><div><h2>Enrolled Classes</h2><p class="page-intro">Your Data Science classes, lecturers and learning schedule summary.</p></div><a class="btn navy" href="<?= BASE_URL ?>/student/chat.php">Ask a question</a></div>
    <?php if (!$classes): ?><div class="empty-state">You are not enrolled in any class yet.</div><?php endif; ?>
    <?php if ($classes): ?><div class="grid grid-3">
        <?php foreach($classes as $c): ?>
            <div class="card" style="box-shadow:none;background:#fffdf8">
                <h3><?= e($c['name']) ?></h3>
                <p class="muted"><?= e($c['code'].' · '.$c['course_name']) ?></p>
                <p><b>Lecturer:</b> <?= e($c['teacher_name']) ?></p>
                <div class="actions"><?= badge($c['status'], $c['status']==='active'?'low':'medium') ?><?= badge((int)$c['schedule_count'].' schedules','info') ?></div>
                <hr class="mini-divider">
                <small class="muted">Joined: <?= e($c['joined_at']) ?></small>
            </div>
        <?php endforeach; ?>
    </div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
