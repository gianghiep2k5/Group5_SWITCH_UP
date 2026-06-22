<?php
require_once __DIR__ . '/../includes/auth.php';
require_teacher();
$title = 'My Classes';
$user = current_user();

$stmt = $pdo->prepare("SELECT c.*, co.code, co.name course_name,
                              COUNT(DISTINCT cs.student_id) student_count,
                              COUNT(DISTINCT sc.id) schedule_count,
                              COUNT(DISTINCT ta.id) alert_count
                       FROM classes c
                       JOIN courses co ON co.id=c.course_id
                       LEFT JOIN class_students cs ON cs.class_id=c.id AND cs.status='active'
                       LEFT JOIN schedules sc ON sc.class_id=c.id
                       LEFT JOIN teacher_alerts ta ON ta.class_id=c.id AND ta.teacher_id=c.teacher_id AND ta.is_read=0
                       WHERE c.teacher_id=?
                       GROUP BY c.id
                       ORDER BY c.is_active DESC, c.start_date DESC, c.id DESC");
$stmt->execute([$user['id']]);
$classes = $stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head">
        <div><h2>Class Overview</h2><p class="page-intro">A clean view of the classes assigned to you by the administrator.</p></div>
        <div class="actions"><a class="btn ghost" href="<?= BASE_URL ?>/teacher/students.php">View students</a><a class="btn ghost" href="<?= BASE_URL ?>/teacher/schedules.php">Plan schedule</a><a class="btn ghost" href="<?= BASE_URL ?>/teacher/lessons.php">Learning content</a></div>
    </div>
    <?php if (!$classes): ?><div class="empty-state">No class has been assigned to you yet.</div><?php endif; ?>
    <?php if ($classes): ?><div class="grid grid-3">
        <?php foreach($classes as $c): ?>
            <div class="card" style="box-shadow:none;background:#fffdf8">
                <h3><?= e($c['name']) ?></h3>
                <p class="muted"><?= e($c['code'].' · '.$c['course_name']) ?></p>
                <div class="actions" style="margin:14px 0">
                    <?= $c['is_active'] ? badge('active','low') : badge('inactive','medium') ?>
                    <?php if((int)$c['alert_count']>0): ?><?= badge((int)$c['alert_count'].' alerts','high') ?><?php endif; ?>
                </div>
                <div class="grid grid-2">
                    <div><div class="stat" style="font-size:28px"><?= (int)$c['student_count'] ?></div><small class="muted">Students</small></div>
                    <div><div class="stat" style="font-size:28px"><?= (int)$c['schedule_count'] ?></div><small class="muted">Schedules</small></div>
                </div>
                <hr class="mini-divider">
                <small class="muted">Semester: <?= e($c['semester'] ?? '-') ?> · <?= e($c['start_date'] ?? '-') ?> → <?= e($c['end_date'] ?? '-') ?></small>
            </div>
        <?php endforeach; ?>
    </div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
