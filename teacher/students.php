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
// v13: make chat history visible immediately. If no student is selected,
// automatically select the first student in the chosen class.
if (!$selected_student_id && $students) {
    $selected_student_id = (int)$students[0]['student_id'];
}
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
        $selected_student_id = $students ? (int)$students[0]['student_id'] : 0;
        if ($selected_student_id) {
            $stmt->execute([$class_id, $user['id'], $selected_student_id]);
            $selected_student = $stmt->fetch();
        }
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
<div class="teacher-history-page">
    <section class="card teacher-member-card">
        <div class="card-head compact-head">
            <div>
                <h2>Class Members</h2>
                <p class="page-intro">Select a student to review their learning conversation.</p>
            </div>
        </div>

        <?php if (!$classes): ?>
            <p class="muted">No class has been assigned to you yet.</p>
        <?php else: ?>
            <form method="get" class="filter-bar teacher-class-filter">
                <div class="field">
                    <label>Class</label>
                    <select name="class_id" onchange="this.form.submit()">
                        <?php foreach($classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= selected($class_id, $c['id']) ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="student-list clean-student-list">
                <?php foreach($students as $row): ?>
                    <a class="student-list-item <?= $selected_student_id === (int)$row['student_id'] ? 'active' : '' ?>" href="?class_id=<?= (int)$class_id ?>&student_id=<?= (int)$row['student_id'] ?>">
                        <div class="student-avatar-small"><?= e(strtoupper(substr($row['full_name'], 0, 1))) ?></div>
                        <div class="student-info">
                            <strong><?= e($row['full_name']) ?></strong>
                            <span><?= e($row['email']) ?></span>
                        </div>
                        <div class="student-meta">
                            <?= badge($row['status'], $row['status'] === 'active' ? 'low' : 'medium') ?>
                            <small><?= e(date('d M Y', strtotime($row['joined_at']))) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$students): ?>
                    <div class="empty-state mini-empty">
                        <div class="empty-icon">◇</div>
                        <p>No students in this class.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card teacher-chat-card">
        <div class="card-head compact-head">
            <div>
                <h2>Chat History</h2>
                <p class="page-intro">Read the selected student's chatbot conversation.</p>
            </div>
        </div>

        <?php if (!$selected_student): ?>
            <div class="empty-state teacher-empty-chat">
                <div class="empty-icon">◇</div>
                <p>Choose a student from the class list to view their chat history.</p>
            </div>
        <?php else: ?>
            <div class="chat-history-top">
                <div class="selected-student-block">
                    <div class="student-avatar-large"><?= e(strtoupper(substr($selected_student['full_name'], 0, 1))) ?></div>
                    <div>
                        <strong><?= e($selected_student['full_name']) ?></strong>
                        <span><?= e($selected_student['email']) ?></span>
                    </div>
                </div>
                <?= badge($selected_student['status'], $selected_student['status'] === 'active' ? 'low' : 'medium') ?>
            </div>

            <?php if (!$sessions): ?>
                <div class="empty-state teacher-empty-chat">
                    <div class="empty-icon">◇</div>
                    <p>This student has no chatbot session yet.</p>
                </div>
            <?php else: ?>
                <form method="get" class="session-select-bar">
                    <input type="hidden" name="class_id" value="<?= (int)$class_id ?>">
                    <input type="hidden" name="student_id" value="<?= (int)$selected_student_id ?>">
                    <label>Conversation</label>
                    <select name="session_id" onchange="this.form.submit()">
                        <?php foreach($sessions as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($view_session_id, $s['id']) ?>>
                                <?= e(($s['title'] ?: 'Untitled session') . ' · ' . (int)$s['message_count'] . ' messages · ' . date('d M H:i', strtotime($s['started_at']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="teacher-conversation-box">
                    <?php foreach($messages as $m): ?>
                        <?php $isStudent = $m['sender'] === 'student'; ?>
                        <div class="teacher-chat-row <?= $isStudent ? 'from-student' : 'from-ai' ?>">
                            <div class="chat-speaker"><?= $isStudent ? 'Student' : 'AI' ?></div>
                            <div class="teacher-bubble">
                                <div class="bubble-content"><?= nl2br(e($m['content'])) ?></div>
                                <div class="bubble-meta">
                                    <?= e(date('d M Y H:i', strtotime($m['created_at']))) ?>
                                    <?php if (!empty($m['topic_name']) || !empty($m['lesson_title'])): ?>
                                        · Topic: <?= e($m['topic_name'] ?? '-') ?>
                                        · Lesson: <?= e($m['lesson_title'] ?? '-') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?>
                        <p class="muted">No messages in this conversation.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
