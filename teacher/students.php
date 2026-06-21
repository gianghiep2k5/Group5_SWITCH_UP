<?php
require_once __DIR__ . '/../includes/auth.php';
require_teacher();
$title = 'Students & Chat History';
$user = current_user();

$stmt = $pdo->prepare('SELECT id, name FROM classes WHERE teacher_id = ? ORDER BY name');
$stmt->execute([$user['id']]);
$classes = $stmt->fetchAll();
$class_id = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));

if ($class_id) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$class_id, $user['id']]);
    if ((int)$stmt->fetchColumn() === 0) {
        $class_id = (int)($classes[0]['id'] ?? 0);
    }
}

$students = [];
if ($class_id) {
    $stmt = $pdo->prepare(
        'SELECT cs.id AS enrollment_id, cs.status, cs.joined_at, u.id AS student_id, u.full_name, u.email, c.name AS class_name
         FROM class_students cs
         JOIN users u ON u.id = cs.student_id
         JOIN classes c ON c.id = cs.class_id
         WHERE cs.class_id = ? AND c.teacher_id = ?
         ORDER BY u.full_name'
    );
    $stmt->execute([$class_id, $user['id']]);
    $students = $stmt->fetchAll();
}

$selected_student_id = (int)($_GET['student_id'] ?? 0);
$selected_student = null;
if ($selected_student_id && $class_id) {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, cs.status, cs.joined_at, c.name AS class_name
         FROM class_students cs
         JOIN users u ON u.id = cs.student_id
         JOIN classes c ON c.id = cs.class_id
         WHERE cs.class_id = ? AND c.teacher_id = ? AND u.id = ?
         LIMIT 1'
    );
    $stmt->execute([$class_id, $user['id'], $selected_student_id]);
    $selected_student = $stmt->fetch();
    if (!$selected_student) {
        $selected_student_id = 0;
    }
}

$sessions = [];
$messages = [];
$view_session_id = (int)($_GET['session_id'] ?? 0);

if ($selected_student_id) {
    $stmt = $pdo->prepare(
        'SELECT s.*, co.code AS course_code, co.name AS course_name
         FROM chat_sessions s
         LEFT JOIN courses co ON co.id = s.course_id
         WHERE s.student_id = ?
         ORDER BY s.started_at DESC'
    );
    $stmt->execute([$selected_student_id]);
    $sessions = $stmt->fetchAll();

    if ($view_session_id) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_sessions WHERE id = ? AND student_id = ?');
        $stmt->execute([$view_session_id, $selected_student_id]);
        if ((int)$stmt->fetchColumn() === 0) {
            $view_session_id = 0;
        }
    }
    if (!$view_session_id && $sessions) {
        $view_session_id = (int)$sessions[0]['id'];
    }

    if ($view_session_id) {
        $stmt = $pdo->prepare(
            'SELECT m.*, qt.name AS topic_name, l.title AS lesson_title
             FROM chat_messages m
             LEFT JOIN question_topics qt ON qt.id = m.topic_id
             LEFT JOIN lessons l ON l.id = m.lesson_id
             WHERE m.session_id = ?
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->execute([$view_session_id]);
        $messages = $stmt->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2 class-student-layout">
    <div class="card">
        <div class="card-head">
            <div>
                <h2>Class Members</h2>
                <p class="page-intro">View students in your assigned classes. Student accounts and enrollment are managed by Admin.</p>
            </div>
        </div>

        <?php if (!$classes): ?>
            <p class="muted">No class has been assigned to you yet.</p>
        <?php else: ?>
            <form method="get" class="filter-bar">
                <div class="field">
                    <label>Class</label>
                    <select name="class_id" onchange="this.form.submit()">
                        <?php foreach($classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= selected($class_id, $c['id']) ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="table-wrap soft-table">
                <table class="table compact">
                    <tr><th>Student</th><th>Email</th><th>Status</th><th>Joined</th><th>View</th></tr>
                    <?php foreach($students as $row): ?>
                        <tr class="<?= $selected_student_id === (int)$row['student_id'] ? 'selected-row' : '' ?>">
                            <td><b><?= e($row['full_name']) ?></b></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= badge($row['status'], $row['status'] === 'active' ? 'low' : 'medium') ?></td>
                            <td><?= e(date('d M Y', strtotime($row['joined_at']))) ?></td>
                            <td>
                                <a class="btn ghost btn-sm" href="?class_id=<?= (int)$class_id ?>&student_id=<?= (int)$row['student_id'] ?>">History</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$students): ?>
                        <tr><td colspan="5" class="muted">No students in this class.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <h2>Learning History</h2>
                <p class="page-intro">Review chatbot sessions to understand what the student is struggling with.</p>
            </div>
        </div>

        <?php if (!$selected_student): ?>
            <div class="empty-state">
                <div class="empty-icon">◇</div>
                <p>Choose a student from the class list to view their chat history.</p>
            </div>
        <?php else: ?>
            <div class="student-summary">
                <div>
                    <strong><?= e($selected_student['full_name']) ?></strong>
                    <span><?= e($selected_student['email']) ?></span>
                </div>
                <?= badge($selected_student['status'], $selected_student['status'] === 'active' ? 'low' : 'medium') ?>
            </div>

            <?php if (!$sessions): ?>
                <p class="muted">This student has no chatbot session yet.</p>
            <?php else: ?>
                <div class="session-tabs">
                    <?php foreach($sessions as $s): ?>
                        <a class="session-pill <?= $view_session_id === (int)$s['id'] ? 'active' : '' ?>" href="?class_id=<?= (int)$class_id ?>&student_id=<?= (int)$selected_student_id ?>&session_id=<?= (int)$s['id'] ?>">
                            <b><?= e($s['title'] ?: 'Untitled session') ?></b>
                            <span><?= (int)$s['message_count'] ?> messages · <?= e(date('d M H:i', strtotime($s['started_at']))) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="chat-box teacher-chat-box">
                    <?php foreach($messages as $m): ?>
                        <div class="msg <?= e($m['sender']) ?>">
                            <?= e($m['content']) ?>
                            <br><small class="muted">Topic: <?= e($m['topic_name'] ?? '-') ?> · Lesson: <?= e($m['lesson_title'] ?? '-') ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
