<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$title = 'Dashboard';

function count_query(PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$role = $user['role'];
$stats = [];
$rows = [];
$quickLinks = [];

if ($role === 'admin') {
    $stats = [
        ['Lecturers', count_query($pdo, "SELECT COUNT(*) FROM users WHERE role='teacher'"), 'Accounts that can manage classes.'],
        ['Students', count_query($pdo, "SELECT COUNT(*) FROM users WHERE role='student'"), 'Learners enrolled in the system.'],
        ['Classes', count_query($pdo, 'SELECT COUNT(*) FROM classes'), 'Active academic groups.'],
        ['Lessons', count_query($pdo, 'SELECT COUNT(*) FROM lessons'), 'Learning contents for chatbot answers.'],
    ];
    $quickLinks = [
        ['/admin/teachers.php','Manage lecturers','Create, update and lock lecturer accounts.','◎'],
        ['/admin/students.php','Manage students','Maintain learner accounts for each class.','◌'],
        ['/admin/courses.php','Manage courses','Control Data Science course information.','◇'],
        ['/admin/classes.php','Manage classes','Assign course, lecturer and semester.','▣'],
        ['/admin/lessons.php','Learning content','Prepare lessons used by the chatbot.','✦'],
    ];
    $rows = $pdo->query("SELECT c.name, co.code, u.full_name teacher_name, COUNT(cs.id) student_count
                         FROM classes c
                         JOIN courses co ON co.id=c.course_id
                         JOIN users u ON u.id=c.teacher_id
                         LEFT JOIN class_students cs ON cs.class_id=c.id
                         GROUP BY c.id
                         ORDER BY c.id DESC LIMIT 6")->fetchAll();
} elseif ($role === 'teacher') {
    $tid = (int)$user['id'];
    $stats = [
        ['My Classes', count_query($pdo, 'SELECT COUNT(*) FROM classes WHERE teacher_id=?', [$tid]), 'Classes assigned to you.'],
        ['Students', count_query($pdo, 'SELECT COUNT(DISTINCT cs.student_id) FROM class_students cs JOIN classes c ON c.id=cs.class_id WHERE c.teacher_id=? AND cs.status="active"', [$tid]), 'Active students in your classes.'],
        ['Unread Alerts', count_query($pdo, 'SELECT COUNT(*) FROM teacher_alerts WHERE teacher_id=? AND is_read=0', [$tid]), 'Learning problems waiting for review.'],
        ['Questions', count_query($pdo, 'SELECT COUNT(*) FROM chat_messages m JOIN chat_sessions s ON s.id=m.session_id JOIN class_students cs ON cs.student_id=s.student_id JOIN classes c ON c.id=cs.class_id WHERE c.teacher_id=? AND m.sender="student"', [$tid]), 'Questions asked by your students.'],
    ];
    $quickLinks = [
        ['/teacher/classes.php','My classes','View class size, course and recent status.','▣'],
        ['/teacher/students.php','Class students','Add students to class and update enrollment.','◌'],
        ['/teacher/schedules.php','Schedule','Plan lessons without time conflicts.','◷'],
        ['/teacher/alerts.php','Learning alerts','Resolve repeated-question notifications.','◇'],
        ['/teacher/analytics.php','Analytics','Review learning frequency and weak topics.','✦'],
    ];
    $stmt = $pdo->prepare("SELECT ta.message, ta.severity, ta.created_at, c.name class_name, u.full_name student_name, qt.name topic_name
                           FROM teacher_alerts ta
                           LEFT JOIN classes c ON c.id=ta.class_id
                           LEFT JOIN users u ON u.id=ta.student_id
                           LEFT JOIN question_topics qt ON qt.id=ta.topic_id
                           WHERE ta.teacher_id=?
                           ORDER BY ta.is_read ASC, ta.created_at DESC LIMIT 6");
    $stmt->execute([$tid]);
    $rows = $stmt->fetchAll();
} else {
    $sid = (int)$user['id'];
    $stats = [
        ['My Classes', count_query($pdo, 'SELECT COUNT(*) FROM class_students WHERE student_id=? AND status="active"', [$sid]), 'Classes you are enrolled in.'],
        ['Chat Sessions', count_query($pdo, 'SELECT COUNT(*) FROM chat_sessions WHERE student_id=?', [$sid]), 'Saved learning conversations.'],
        ['Questions', count_query($pdo, 'SELECT COUNT(*) FROM chat_messages m JOIN chat_sessions s ON s.id=m.session_id WHERE s.student_id=? AND m.sender="student"', [$sid]), 'Questions you have asked.'],
        ['Upcoming', count_query($pdo, 'SELECT COUNT(*) FROM schedules sc JOIN class_students cs ON cs.class_id=sc.class_id WHERE cs.student_id=? AND cs.status="active" AND sc.start_time>=NOW()', [$sid]), 'Upcoming class sessions.'],
    ];
    $quickLinks = [
        ['/student/classes.php','My classes','See courses, lecturers and enrollment status.','▣'],
        ['/student/schedules.php','Schedule','Check upcoming lessons and rooms.','◷'],
        ['/student/chat.php','Learning chat','Ask Data Science questions and get support.','✦'],
        ['/student/history.php','Chat history','Continue or review previous conversations.','◇'],
    ];
    $stmt = $pdo->prepare("SELECT sc.title, sc.start_time, sc.end_time, sc.room, c.name class_name, co.code course_code
                           FROM schedules sc
                           JOIN classes c ON c.id=sc.class_id
                           JOIN courses co ON co.id=c.course_id
                           JOIN class_students cs ON cs.class_id=c.id
                           WHERE cs.student_id=? AND cs.status='active' AND sc.start_time>=NOW()
                           ORDER BY sc.start_time ASC LIMIT 6");
    $stmt->execute([$sid]);
    $rows = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>
<section class="grid grid-4">
    <?php foreach ($stats as $s): ?>
        <div class="card stat-card">
            <div class="stat-label"><?= e($s[0]) ?></div>
            <div class="stat"><?= (int)$s[1] ?></div>
            <p class="stat-note"><?= e($s[2]) ?></p>
        </div>
    <?php endforeach; ?>
</section>

<section class="card" style="margin-top:18px">
    <div class="card-head">
        <div>
            <h2>Workspace</h2>
            <p class="page-intro">Hello, <b><?= e($user['full_name']) ?></b>. Choose one area below to continue your work.</p>
        </div>
    </div>
    <div class="module-grid">
        <?php foreach ($quickLinks as $link): ?>
            <a class="module-card" href="<?= BASE_URL . e($link[0]) ?>">
                <div class="module-icon"><?= e($link[3]) ?></div>
                <strong><?= e($link[1]) ?></strong>
                <span><?= e($link[2]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <?php if ($role === 'admin'): ?>
        <div class="card-head"><div><h2>Recent Classes</h2><p class="page-intro">A quick overview of current class organization.</p></div><a class="btn ghost" href="<?= BASE_URL ?>/admin/classes.php">Open classes</a></div>
        <div class="table-wrap"><table class="table compact"><tr><th>Class</th><th>Course</th><th>Lecturer</th><th>Students</th></tr>
            <?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['code']) ?></td><td><?= e($r['teacher_name']) ?></td><td><?= (int)$r['student_count'] ?></td></tr><?php endforeach; ?>
        </table></div>
    <?php elseif ($role === 'teacher'): ?>
        <div class="card-head"><div><h2>Latest Alerts</h2><p class="page-intro">Students who may need extra explanation in class.</p></div><a class="btn ghost" href="<?= BASE_URL ?>/teacher/alerts.php">Open alerts</a></div>
        <?php if (!$rows): ?><div class="empty-state">No learning alerts yet.</div><?php endif; ?>
        <?php if ($rows): ?><div class="table-wrap"><table class="table compact"><tr><th>Issue</th><th>Class</th><th>Student</th><th>Topic</th><th>Level</th></tr>
            <?php foreach($rows as $r): ?><tr><td><?= e($r['message']) ?></td><td><?= e($r['class_name'] ?? '-') ?></td><td><?= e($r['student_name'] ?? '-') ?></td><td><?= e($r['topic_name'] ?? '-') ?></td><td><?= badge($r['severity'], $r['severity']) ?></td></tr><?php endforeach; ?>
        </table></div><?php endif; ?>
    <?php else: ?>
        <div class="card-head"><div><h2>Upcoming Schedule</h2><p class="page-intro">The next learning sessions from your enrolled classes.</p></div><a class="btn ghost" href="<?= BASE_URL ?>/student/schedules.php">Open schedule</a></div>
        <?php if (!$rows): ?><div class="empty-state">No upcoming schedule found.</div><?php endif; ?>
        <?php if ($rows): ?><div class="table-wrap"><table class="table compact"><tr><th>Lesson</th><th>Class</th><th>Time</th><th>Room</th></tr>
            <?php foreach($rows as $r): ?><tr><td><?= e($r['title']) ?></td><td><?= e($r['course_code'].' · '.$r['class_name']) ?></td><td><?= e($r['start_time'].' → '.$r['end_time']) ?></td><td><?= e($r['room'] ?? '-') ?></td></tr><?php endforeach; ?>
        </table></div><?php endif; ?>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
