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

function retrieve_lessons(PDO $pdo, int $course_id, string $question = '', int $limit = 3, bool $includeZeroScore = true): array {
    $stmt = $pdo->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index');
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
    $tokens = normalize_tokens($question);

    foreach ($lessons as &$lesson) {
        $titleLower = mb_strtolower($lesson['title'], 'UTF-8');
        $contentLower = mb_strtolower($lesson['content'] ?? '', 'UTF-8');
        $score = 0;
        $matched = [];
        foreach ($tokens as $tok) {
            $tokenMatched = false;
            if (mb_strpos($titleLower, $tok) !== false) {
                $score += 3;
                $tokenMatched = true;
            }
            if (mb_strpos($contentLower, $tok) !== false) {
                $score += 1;
                $tokenMatched = true;
            }
            if ($tokenMatched) $matched[] = $tok;
        }
        $lesson['_score'] = $score;
        $lesson['_matched_tokens'] = array_values(array_unique($matched));
    }
    unset($lesson);

    if ($tokens) {
        usort($lessons, fn($a, $b) => ($b['_score'] <=> $a['_score']) ?: ($a['order_index'] <=> $b['order_index']));
        if (!$includeZeroScore) {
            $lessons = array_values(array_filter($lessons, fn($l) => (int)($l['_score'] ?? 0) > 0));
        }
    } elseif (!$includeZeroScore) {
        $lessons = [];
    }
    return array_slice($lessons, 0, $limit);
}

function get_latest_used_lesson_id(PDO $pdo, int $session_id): ?int {
    if (!$session_id) return null;
    $stmt = $pdo->prepare('SELECT lesson_id FROM chat_messages WHERE session_id = ? AND lesson_id IS NOT NULL ORDER BY created_at DESC, id DESC LIMIT 1');
    $stmt->execute([$session_id]);
    $lessonId = $stmt->fetchColumn();
    return $lessonId ? (int)$lessonId : null;
}

function get_lesson_by_id(PDO $pdo, int $lesson_id, int $course_id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM lessons WHERE id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$lesson_id, $course_id]);
    $lesson = $stmt->fetch();
    if (!$lesson) return null;
    $lesson['_score'] = $lesson['_score'] ?? 0;
    $lesson['_used_in_answer'] = true;
    return $lesson;
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


function is_ajax_chat_request(): bool {
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (isset($_POST['ajax']) && $_POST['ajax'] === '1') || strtolower($requestedWith) === 'xmlhttprequest';
}

function json_chat_response(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function build_related_lessons(PDO $pdo, int $course_id, int $session_id = 0, string $lastQuestion = ''): array {
    $relatedLessons = [];
    if (!$course_id) return $relatedLessons;

    $seenLessonIds = [];
    $latestUsedLessonId = $session_id ? get_latest_used_lesson_id($pdo, $session_id) : null;
    if ($latestUsedLessonId) {
        $usedLesson = get_lesson_by_id($pdo, $latestUsedLessonId, $course_id);
        if ($usedLesson) {
            $relatedLessons[] = $usedLesson;
            $seenLessonIds[(int)$usedLesson['id']] = true;
        }
    }

    $scoredLessons = $lastQuestion ? retrieve_lessons($pdo, $course_id, $lastQuestion, 6, false) : [];
    foreach ($scoredLessons as $lessonItem) {
        $lessonId = (int)$lessonItem['id'];
        if (isset($seenLessonIds[$lessonId])) continue;
        $relatedLessons[] = $lessonItem;
        $seenLessonIds[$lessonId] = true;
        if (count($relatedLessons) >= 4) break;
    }

    if (!$lastQuestion && !$relatedLessons) {
        $relatedLessons = retrieve_lessons($pdo, $course_id, '', 4, true);
    }
    return $relatedLessons;
}

function render_related_lessons_html(array $relatedLessons): string {
    ob_start();
    if (!$relatedLessons): ?>
        <div class="related-empty">Chưa có nội dung bài học cho môn này.</div>
    <?php else: ?>
        <div class="related-list">
            <?php foreach ($relatedLessons as $lesson): ?>
                <article class="related-card <?= !empty($lesson['_used_in_answer']) ? 'related-card-active' : '' ?>">
                    <strong><?= e($lesson['title']) ?></strong>
                    <?php if (!empty($lesson['_used_in_answer'])): ?>
                        <small>Đã dùng trong câu trả lời gần nhất</small>
                    <?php else: ?>
                        <small>Khớp câu hỏi: <?= (int)($lesson['_score'] ?? 0) ?></small>
                    <?php endif; ?>
                    <?php $preview = clean_course_text($lesson['content'] ?? '', 130); ?>
                    <?php if ($preview): ?>
                        <p><?= e($preview) ?><?= mb_strlen(strip_tags($lesson['content'] ?? ''), 'UTF-8') > 130 ? '...' : '' ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif;
    return trim(ob_get_clean());
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
    $ajax = is_ajax_chat_request();

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

        $history = load_chat_history($pdo, $session_id);
        $topic = detect_topic($pdo, $question);
        $lessonHits = retrieve_lessons($pdo, $currentCourse, $question, 3);
        $lesson = $lessonHits[0] ?? null;
        $bestLessonId = ($lesson && !is_simple_greeting($question) && (int)($lesson['_score'] ?? 0) > 0) ? (int)$lesson['id'] : null;

        $contextHits = array_values(array_filter($lessonHits, fn($hit) => (int)($hit['_score'] ?? 0) > 0));
        if (!$contextHits && !is_simple_greeting($question) && $lesson) {
            $contextHits = [$lesson];
        }
        $lessonContexts = [];
        foreach ($contextHits as $hit) {
            $lessonContexts[] = $hit['title'] . ': ' . clean_course_text($hit['content'] ?? '', 1200);
        }

        $pdo->prepare('INSERT INTO chat_messages(session_id, sender, content, topic_id, lesson_id) VALUES(?,?,?,?,?)')
            ->execute([$session_id, 'student', $question, $topic['id'] ?? null, $bestLessonId]);
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
            ->execute([$session_id, 'bot', $answer, $topic['id'] ?? null, $bestLessonId, mb_strlen($answer, 'UTF-8'), $responseMs]);
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

        if ($ajax) {
            $related = build_related_lessons($pdo, $currentCourse, $session_id, $question);
            json_chat_response([
                'ok' => true,
                'session_id' => $session_id,
                'question' => $question,
                'answer' => $answer,
                'answer_time' => date('H:i'),
                'related_html' => render_related_lessons_html($related),
            ]);
        }
    } catch (Throwable $e) {
        if ($studentMessageSaved && $session_id) {
            try {
                $fallback = 'Xin lỗi, hệ thống đang gặp lỗi khi tạo câu trả lời AI. Câu hỏi của bạn đã được lưu lại, bạn có thể thử gửi lại hoặc giáo viên sẽ xem được lịch sử chat này.';
                $pdo->prepare('INSERT INTO chat_messages(session_id, sender, content) VALUES(?,?,?)')
                    ->execute([$session_id, 'bot', $fallback]);
                $pdo->prepare('UPDATE chat_sessions SET message_count = message_count + 1 WHERE id = ? AND student_id = ?')
                    ->execute([$session_id, (int)$user['id']]);
                if ($ajax) {
                    json_chat_response([
                        'ok' => true,
                        'session_id' => $session_id,
                        'question' => $question,
                        'answer' => $fallback,
                        'answer_time' => date('H:i'),
                        'related_html' => render_related_lessons_html(build_related_lessons($pdo, $currentCourse, $session_id, $question)),
                    ]);
                }
            } catch (Throwable $ignored) {}
        } else {
            if ($ajax) {
                json_chat_response(['ok' => false, 'error' => $e->getMessage()]);
            }
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

// Build the related-lesson panel from the latest question/answer, not from a static course list.
$relatedLessons = $currentCourse ? build_related_lessons($pdo, $currentCourse, $currentSessionId, $lastQuestion) : [];
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
            <form method="post" class="dss-chat-inputbar" id="chatForm">
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
        <div id="relatedLessonsWrap">
            <?= render_related_lessons_html($relatedLessons) ?>
        </div>
    </aside>
</div>
<script>
const chatWindow = document.getElementById('chatWindow');
const chatForm = document.getElementById('chatForm');
const relatedLessonsWrap = document.getElementById('relatedLessonsWrap');

function scrollChatToBottom() {
    if (chatWindow) chatWindow.scrollTop = chatWindow.scrollHeight;
}
function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
function appendStudentMessage(text) {
    const row = document.createElement('div');
    row.className = 'dss-message-row student-row';
    row.innerHTML = `<div class="dss-message student-message">${escapeHtml(text)}</div>`;
    chatWindow.appendChild(row);
}
function appendBotMessage(text, timeText, extraClass = '') {
    const row = document.createElement('div');
    row.className = `dss-message-row bot-row ${extraClass}`.trim();
    row.innerHTML = `
        <div class="message-avatar">AI</div>
        <div class="dss-message bot-message">
            ${escapeHtml(text).replace(/\n/g, '<br>')}
            <div class="message-time">${escapeHtml(timeText || '')}</div>
        </div>`;
    chatWindow.appendChild(row);
    return row;
}
scrollChatToBottom();

if (chatForm && chatWindow) {
    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = chatForm.querySelector('input[name="question"]');
        const button = chatForm.querySelector('button[type="submit"]');
        const question = (input.value || '').trim();
        if (!question) return;

        appendStudentMessage(question);
        input.value = '';
        input.focus();
        const typingRow = appendBotMessage('Đang tạo câu trả lời...', '', 'typing-row');
        scrollChatToBottom();

        button.disabled = true;
        button.classList.add('send-btn-loading');
        try {
            const formData = new FormData(chatForm);
            formData.set('question', question);
            formData.set('ajax', '1');
            const response = await fetch(chatForm.action || window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            typingRow.remove();
            if (!data.ok) {
                appendBotMessage(data.error || 'Có lỗi xảy ra, vui lòng thử lại.', new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
            } else {
                appendBotMessage(data.answer || '', data.answer_time || '');
                if (relatedLessonsWrap && data.related_html) {
                    relatedLessonsWrap.innerHTML = data.related_html;
                }
            }
        } catch (error) {
            typingRow.remove();
            appendBotMessage('Không gửi được câu hỏi. Hãy kiểm tra lại kết nối hoặc thử tải lại trang.', new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
        } finally {
            button.disabled = false;
            button.classList.remove('send-btn-loading');
            scrollChatToBottom();
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
