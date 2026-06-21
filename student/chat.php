<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/ai_client.php';
require_student();
$title = 'Learning Chat';
$user = current_user();

function normalize_tokens(string $text): array {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $parts = preg_split('/\s+/u', trim($text));
    $stop = ['là','và','của','cho','em','anh','chị','ạ','the','a','an','is','are','what','how','to','in','of','với','như','thế','nào','please','can','could'];
    return array_values(array_filter($parts, fn($w) => mb_strlen($w, 'UTF-8') >= 3 && !in_array($w, $stop, true)));
}

function is_simple_greeting(string $text): bool {
    $q = mb_strtolower(trim($text), 'UTF-8');
    $q = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q);
    $q = preg_replace('/\s+/u', ' ', trim($q));
    $greetings = ['hi','hello','hey','xin chao','xin chào','chào','chao','alo','alo bạn','hello bot'];
    return in_array($q, $greetings, true) || (mb_strlen($q, 'UTF-8') <= 12 && preg_match('/^(hi|hello|hey|chào|chao|alo)/u', $q));
}

function clean_course_text(string $text, int $limit = 800): string {
    $text = strip_tags($text);
    $text = str_replace(["\xc2\xa0", "\t"], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return mb_substr(trim($text ?? ''), 0, $limit, 'UTF-8');
}

function detect_topic(PDO $pdo, string $question): ?array {
    $topics = $pdo->query('SELECT * FROM question_topics ORDER BY id')->fetchAll();
    $q = mb_strtolower($question, 'UTF-8');
    $map = [
        'python' => ['python','list','dict','loop','comprehension','function','class'],
        'pandas' => ['pandas','dataframe','series','csv','groupby','merge','pivot','data frame'],
        'visualization' => ['plot','chart','histogram','matplotlib','visualization','biểu đồ','trực quan'],
        'linear-regression' => ['linear','regression','hồi quy','mse','sklearn','r2'],
        'classification' => ['classification','classify','logistic','confusion','phân loại','decision tree','tree'],
    ];
    foreach ($topics as $topic) {
        $keywords = $map[$topic['slug']] ?? [$topic['slug'], mb_strtolower($topic['name'], 'UTF-8')];
        foreach ($keywords as $kw) {
            if (mb_strpos($q, mb_strtolower($kw, 'UTF-8')) !== false) return $topic;
        }
    }
    return $topics[0] ?? null;
}

function get_student_courses(PDO $pdo, int $student_id): array {
    $stmt = $pdo->prepare('SELECT DISTINCT co.id, co.code, co.name
        FROM courses co
        JOIN classes c ON c.course_id = co.id
        JOIN class_students cs ON cs.class_id = c.id
        WHERE cs.student_id = ? AND cs.status = "active"
        ORDER BY co.code');
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

function course_from_session(PDO $pdo, int $session_id, int $student_id): ?int {
    if (!$session_id) return null;
    $stmt = $pdo->prepare('SELECT course_id FROM chat_sessions WHERE id = ? AND student_id = ? LIMIT 1');
    $stmt->execute([$session_id, $student_id]);
    $courseId = $stmt->fetchColumn();
    return $courseId ? (int)$courseId : null;
}

function ensure_student_course(PDO $pdo, int $student_id, int $course_id): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*)
        FROM class_students cs
        JOIN classes c ON c.id = cs.class_id
        WHERE cs.student_id = ? AND c.course_id = ? AND cs.status = "active"');
    $stmt->execute([$student_id, $course_id]);
    return (int)$stmt->fetchColumn() > 0;
}

function retrieve_lessons(PDO $pdo, int $course_id, string $question = '', int $limit = 3): array {
    $stmt = $pdo->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index');
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
    $tokens = normalize_tokens($question);

    foreach ($lessons as &$lesson) {
        $titleLower = mb_strtolower($lesson['title'], 'UTF-8');
        $contentLower = mb_strtolower($lesson['content'] ?? '', 'UTF-8');
        $score = 0;
        foreach ($tokens as $tok) {
            if (mb_strpos($titleLower, $tok) !== false) $score += 3;
            if (mb_strpos($contentLower, $tok) !== false) $score += 1;
        }
        $lesson['_score'] = $score;
    }
    unset($lesson);

    if ($tokens) {
        usort($lessons, fn($a, $b) => ($b['_score'] <=> $a['_score']) ?: ($a['order_index'] <=> $b['order_index']));
    }
    return array_slice($lessons, 0, $limit);
}

function generate_local_answer(?array $lesson, ?array $topic, string $question): string {
    if (is_simple_greeting($question)) {
        return "Xin chào! Tôi là trợ giảng AI của môn Data Science. Bạn có thể hỏi về bài học, thuật toán, code Python, Pandas, Machine Learning hoặc phần nào bạn chưa hiểu.";
    }
    $topicName = $topic['name'] ?? 'Data Science';
    if (!$lesson) {
        return "Câu hỏi của bạn thuộc chủ đề {$topicName}. Hiện hệ thống chưa có bài học phù hợp trong database, giáo viên sẽ cần bổ sung nội dung.";
    }
    $excerpt = clean_course_text($lesson['content'] ?? '', 620);
    return "Chủ đề: {$topicName}\nBài liên quan: {$lesson['title']}\n\nGiải thích ngắn gọn:\n{$excerpt}\n\nGợi ý học: hãy xem lại bài '{$lesson['title']}', sau đó thử đặt một ví dụ nhỏ hoặc đoạn code đơn giản để kiểm tra hiểu bài.";
}

function load_chat_history(PDO $pdo, int $session_id): array {
    if (!$session_id) return [];
    $stmt = $pdo->prepare('SELECT sender, content FROM chat_messages WHERE session_id = ? ORDER BY created_at DESC, id DESC LIMIT 6');
    $stmt->execute([$session_id]);
    $rows = array_reverse($stmt->fetchAll());
    $history = [];
    foreach ($rows as $m) {
        $history[] = [
            'role' => $m['sender'] === 'student' ? 'user' : 'assistant',
            'content' => $m['content'],
        ];
    }
    return $history;
}

function get_student_class(PDO $pdo, int $student_id, int $course_id): ?array {
    $stmt = $pdo->prepare('SELECT c.* FROM class_students cs JOIN classes c ON c.id = cs.class_id WHERE cs.student_id = ? AND c.course_id = ? AND cs.status = "active" LIMIT 1');
    $stmt->execute([$student_id, $course_id]);
    $class = $stmt->fetch();
    return $class ?: null;
}

$courses = get_student_courses($pdo, (int)$user['id']);
$currentSessionId = (int)($_SESSION['current_chat_session_id'] ?? 0);
$sessionCourseId = course_from_session($pdo, $currentSessionId, (int)$user['id']);
$defaultCourseId = (int)($courses[0]['id'] ?? 0);
$currentCourse = $sessionCourseId ?: $defaultCourseId;

if (isset($_GET['new'])) {
    unset($_SESSION['current_chat_session_id']);
    flash('success', 'Started a new chat.');
    redirect('/student/chat.php');
}

if (is_post()) {
    verify_csrf();
    $question = trim($_POST['question'] ?? '');
    $session_id = 0;
    $studentMessageSaved = false;

    try {
        if ($question === '') throw new Exception('Please enter a question.');
        if (!$currentCourse) throw new Exception('You are not enrolled in any active course.');
        if (!ensure_student_course($pdo, (int)$user['id'], $currentCourse)) throw new Exception('You are not enrolled in this course.');

        $session_id = (int)($_SESSION['current_chat_session_id'] ?? 0);
        $sessionCourseCheck = $session_id ? course_from_session($pdo, $session_id, (int)$user['id']) : null;
        if ($session_id && (!$sessionCourseCheck || (int)$sessionCourseCheck !== (int)$currentCourse)) {
            $session_id = 0;
            unset($_SESSION['current_chat_session_id']);
        }

        $isNewSession = false;
        if (!$session_id) {
            $pdo->prepare('INSERT INTO chat_sessions(student_id, course_id, title, message_count) VALUES(?,?,?,0)')
                ->execute([(int)$user['id'], $currentCourse, mb_substr($question, 0, 80, 'UTF-8')]);
            $session_id = (int)$pdo->lastInsertId();
            $_SESSION['current_chat_session_id'] = $session_id;
            $isNewSession = true;
        }

        // Load history BEFORE adding the new student message, same as DSS FastAPI flow.
        $history = load_chat_history($pdo, $session_id);
        $topic = detect_topic($pdo, $question);
        $lessonHits = retrieve_lessons($pdo, $currentCourse, $question, 3);
        $lesson = $lessonHits[0] ?? null;
        $lessonContexts = [];
        foreach ($lessonHits as $hit) {
            $lessonContexts[] = $hit['title'] . ': ' . clean_course_text($hit['content'] ?? '', 1200);
        }

        // Save student message immediately so the UI never loses the user's question.
        $pdo->prepare('INSERT INTO chat_messages(session_id, sender, content, topic_id, lesson_id) VALUES(?,?,?,?,?)')
            ->execute([$session_id, 'student', $question, $topic['id'] ?? null, $lesson['id'] ?? null]);
        $studentMessageSaved = true;
        $pdo->prepare('UPDATE chat_sessions SET message_count = message_count + 1 WHERE id = ? AND student_id = ?')
            ->execute([$session_id, (int)$user['id']]);

        $start = microtime(true);
        $answer = call_python_ai_service($question, $lessonContexts, $history);
        if (!$answer) {
            $answer = generate_local_answer($lesson, $topic, $question);
        }
        $responseMs = (int)((microtime(true) - $start) * 1000);

        $pdo->prepare('INSERT INTO chat_messages(session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES(?,?,?,?,?,?,?)')
            ->execute([$session_id, 'bot', $answer, $topic['id'] ?? null, $lesson['id'] ?? null, mb_strlen($answer, 'UTF-8'), $responseMs]);
        $pdo->prepare('UPDATE chat_sessions SET message_count = message_count + 1 WHERE id = ? AND student_id = ?')
            ->execute([$session_id, (int)$user['id']]);

        $class = get_student_class($pdo, (int)$user['id'], $currentCourse);
        $class_id = $class['id'] ?? null;
        $topic_id = $topic['id'] ?? null;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_messages m JOIN chat_sessions s ON s.id = m.session_id WHERE s.student_id = ? AND m.sender = "student" AND m.topic_id = ? AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([(int)$user['id'], $topic_id]);
        $repeatCount = (int)$stmt->fetchColumn();
        $repeatScore = min(1.0, $repeatCount / 3);

        $pdo->prepare('INSERT INTO learning_analytics(student_id, class_id, date, session_count, question_count, total_time_sec, top_topic_id, repeat_score) VALUES(?,?,CURDATE(),?,?,?,?,?) ON DUPLICATE KEY UPDATE session_count = session_count + VALUES(session_count), question_count = question_count + 1, total_time_sec = total_time_sec + VALUES(total_time_sec), top_topic_id = VALUES(top_topic_id), repeat_score = VALUES(repeat_score)')
            ->execute([(int)$user['id'], $class_id, $isNewSession ? 1 : 0, 1, 300, $topic_id, $repeatScore]);

        if ($class && $topic && $repeatCount >= 3) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM teacher_alerts WHERE teacher_id = ? AND class_id = ? AND student_id = ? AND topic_id = ? AND alert_type = "repeat_question" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
            $stmt->execute([$class['teacher_id'], $class_id, (int)$user['id'], $topic_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                $message = 'Student ' . $user['full_name'] . ' asked the topic "' . $topic['name'] . '" ' . $repeatCount . ' times in the last 7 days.';
                $pdo->prepare('INSERT INTO teacher_alerts(teacher_id, class_id, student_id, topic_id, alert_type, message, severity) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$class['teacher_id'], $class_id, (int)$user['id'], $topic_id, 'repeat_question', $message, 'high']);
            }
        }
    } catch (Throwable $e) {
        if ($studentMessageSaved && $session_id) {
            try {
                $fallback = 'Xin lỗi, hệ thống đang gặp lỗi khi tạo câu trả lời AI. Câu hỏi của bạn đã được lưu lại, bạn có thể thử gửi lại hoặc giáo viên sẽ xem được lịch sử chat này.';
                $pdo->prepare('INSERT INTO chat_messages(session_id, sender, content) VALUES(?,?,?)')
                    ->execute([$session_id, 'bot', $fallback]);
                $pdo->prepare('UPDATE chat_sessions SET message_count = message_count + 1 WHERE id = ? AND student_id = ?')
                    ->execute([$session_id, (int)$user['id']]);
            } catch (Throwable $ignored) {}
        } else {
            flash('error', $e->getMessage());
        }
    }

    redirect('/student/chat.php');
}

$messages = [];
if ($currentSessionId) {
    $stmt = $pdo->prepare('SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$currentSessionId]);
    $messages = $stmt->fetchAll();
}

$lastQuestion = '';
foreach (array_reverse($messages) as $msg) {
    if (($msg['sender'] ?? '') === 'student') { $lastQuestion = $msg['content']; break; }
}
$relatedLessons = $currentCourse ? retrieve_lessons($pdo, $currentCourse, $lastQuestion, 4) : [];
$currentCourseRow = null;
foreach ($courses as $c) {
    if ((int)$c['id'] === (int)$currentCourse) { $currentCourseRow = $c; break; }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="dss-chat-page">
    <section class="dss-chat-main">
        <div class="dss-chat-header">
            <div class="assistant-mark">✦</div>
            <div>
                <h2>Trợ giảng AI</h2>
                <p><?= e(($currentCourseRow['name'] ?? 'Data Science') . ' · Hỏi đáp dựa trên bài giảng lớp bạn') ?></p>
            </div>
            <a class="chat-new-link" href="?new=1">Cuộc trò chuyện mới</a>
        </div>

        <div class="dss-chat-window" id="chatWindow">
            <?php if (!$messages): ?>
                <div class="dss-message-row bot-row">
                    <div class="message-avatar">AI</div>
                    <div class="dss-message bot-message">
                        Chào bạn, tôi có thể giúp gì cho bạn trong môn <?= e($currentCourseRow['name'] ?? 'Data Science') ?> hôm nay?
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($messages as $m): ?>
                <?php if ($m['sender'] === 'student'): ?>
                    <div class="dss-message-row student-row">
                        <div class="dss-message student-message"><?= e($m['content']) ?></div>
                    </div>
                <?php else: ?>
                    <div class="dss-message-row bot-row">
                        <div class="message-avatar">AI</div>
                        <div class="dss-message bot-message">
                            <?= nl2br(e($m['content'])) ?>
                            <div class="message-time"><?= e(date('H:i', strtotime($m['created_at']))) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (!$courses): ?>
            <div class="empty-state">Bạn chưa được thêm vào lớp học nào nên chưa thể sử dụng chat.</div>
        <?php else: ?>
            <form method="post" class="dss-chat-inputbar">
                <?= csrf_field() ?>
                <input class="chat-input" name="question" placeholder="Nhập câu hỏi của bạn..." autocomplete="off" required>
                <button class="send-btn" type="submit">Gửi</button>
            </form>
        <?php endif; ?>
    </section>

    <aside class="dss-related-panel">
        <div class="related-title">
            <span>▣</span>
            <h3>Bài học liên quan</h3>
        </div>
        <?php if (!$relatedLessons): ?>
            <div class="related-empty">Chưa có nội dung bài học cho môn này.</div>
        <?php else: ?>
            <div class="related-list">
                <?php foreach ($relatedLessons as $lesson): ?>
                    <article class="related-card">
                        <strong><?= e($lesson['title']) ?></strong>
                        <small>Mức độ liên quan: <?= (int)($lesson['_score'] ?? 0) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>
<script>
const chatWindow = document.getElementById('chatWindow');
if (chatWindow) chatWindow.scrollTop = chatWindow.scrollHeight;
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
