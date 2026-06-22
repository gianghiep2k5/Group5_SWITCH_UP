<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/file_extract_client.php';
require_teacher();
$title = 'Learning Content';
$user = current_user();
$teacherId = (int)$user['id'];

const TEACHER_LESSON_UPLOAD_DIR = __DIR__ . '/../storage/uploads/lessons';
const TEACHER_LESSON_UPLOAD_URL_PREFIX = 'storage/uploads/lessons';
const TEACHER_LESSON_MAX_UPLOAD_BYTES = 15 * 1024 * 1024; // 15MB

function teacher_normalize_lesson_text(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\t\x{00A0}]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    return trim($text);
}

function teacher_read_text_upload(string $path, string $ext): ?string {
    $ext = strtolower($ext);
    if (in_array($ext, ['txt', 'md', 'csv', 'json', 'py', 'sql', 'html', 'htm'], true)) {
        $raw = @file_get_contents($path);
        if ($raw === false) return null;
        if (in_array($ext, ['html', 'htm'], true)) {
            $raw = strip_tags($raw);
        }
        return teacher_normalize_lesson_text($raw);
    }
    if ($ext === 'pdf') {
        return extract_text_via_python_service($path);
    }
    return null;
}

/**
 * @return array{url:string,text:?string,original:string}|null
 */
function teacher_handle_lesson_upload(): ?array {
    if (empty($_FILES['lesson_file']) || !is_array($_FILES['lesson_file'])) return null;
    $file = $_FILES['lesson_file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed. Please choose another file.');
    }
    if (($file['size'] ?? 0) > TEACHER_LESSON_MAX_UPLOAD_BYTES) {
        throw new Exception('File is too large. Maximum size is 15MB.');
    }

    $original = basename((string)($file['name'] ?? 'lesson_file'));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['txt', 'md', 'csv', 'json', 'py', 'sql', 'html', 'htm', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Unsupported file type. Use TXT, MD, CSV, HTML, code files, SQL or PDF.');
    }
    if (!is_dir(TEACHER_LESSON_UPLOAD_DIR) && !mkdir(TEACHER_LESSON_UPLOAD_DIR, 0775, true)) {
        throw new Exception('Cannot create upload folder.');
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($original, PATHINFO_FILENAME));
    $safeBase = trim((string)$safeBase, '-') ?: 'lesson';
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $ext;
    $target = TEACHER_LESSON_UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new Exception('Cannot save uploaded file.');
    }

    return [
        'url' => TEACHER_LESSON_UPLOAD_URL_PREFIX . '/' . $filename,
        'text' => teacher_read_text_upload($target, $ext),
        'original' => $original,
    ];
}

function teacher_can_manage_course(PDO $pdo, int $teacherId, int $courseId): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE teacher_id = ? AND course_id = ?');
    $stmt->execute([$teacherId, $courseId]);
    return (int)$stmt->fetchColumn() > 0;
}

function teacher_can_manage_lesson(PDO $pdo, int $teacherId, int $lessonId): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*)
                           FROM lessons l
                           JOIN classes c ON c.course_id = l.course_id
                           WHERE l.id = ? AND c.teacher_id = ?');
    $stmt->execute([$lessonId, $teacherId]);
    return (int)$stmt->fetchColumn() > 0;
}

$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if (teacher_can_manage_lesson($pdo, $teacherId, $editId)) {
        $stmt = $pdo->prepare('SELECT * FROM lessons WHERE id = ?');
        $stmt->execute([$editId]);
        $edit = $stmt->fetch();
    } else {
        flash('error', 'You can only manage learning content for courses assigned to your classes.');
        redirect('/teacher/lessons.php');
    }
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' || $action === 'update') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $lessonTitle = trim((string)($_POST['title'] ?? ''));
            $content = teacher_normalize_lesson_text((string)($_POST['content'] ?? ''));
            $orderIndex = (int)($_POST['order_index'] ?? 0);
            $fileUrl = trim((string)($_POST['file_url'] ?? ''));

            if (!$courseId || !teacher_can_manage_course($pdo, $teacherId, $courseId)) {
                throw new Exception('Please choose a course assigned to your classes.');
            }

            if ($action === 'update') {
                $lessonId = (int)($_POST['id'] ?? 0);
                if (!$lessonId || !teacher_can_manage_lesson($pdo, $teacherId, $lessonId)) {
                    throw new Exception('You do not have permission to update this lesson.');
                }
            }

            $upload = teacher_handle_lesson_upload();
            if ($upload) {
                $fileUrl = $upload['url'];
                if ($lessonTitle === '') {
                    $lessonTitle = pathinfo($upload['original'], PATHINFO_FILENAME);
                }
                if ($content === '' && !empty($upload['text'])) {
                    $content = $upload['text'];
                }
            }

            if ($lessonTitle === '') {
                throw new Exception('Lesson title is required.');
            }
            if ($content === '') {
                throw new Exception('Lesson content is required. Paste lesson text, or upload a readable file. For PDF, start the Python AI service before uploading.');
            }

            if ($action === 'create') {
                $pdo->prepare('INSERT INTO lessons(course_id, title, content, file_url, order_index) VALUES(?,?,?,?,?)')
                    ->execute([$courseId, $lessonTitle, $content, $fileUrl, $orderIndex]);
                flash('success', 'Learning content added for your course.');
            } else {
                $pdo->prepare('UPDATE lessons SET course_id = ?, title = ?, content = ?, file_url = ?, order_index = ? WHERE id = ?')
                    ->execute([$courseId, $lessonTitle, $content, $fileUrl, $orderIndex, (int)$_POST['id']]);
                flash('success', 'Learning content updated.');
            }
        } elseif ($action === 'delete') {
            $lessonId = (int)($_POST['id'] ?? 0);
            if (!$lessonId || !teacher_can_manage_lesson($pdo, $teacherId, $lessonId)) {
                throw new Exception('You do not have permission to delete this lesson.');
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_messages WHERE lesson_id = ?');
            $stmt->execute([$lessonId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete content that is already linked to chat history.');
            }
            $pdo->prepare('DELETE FROM lessons WHERE id = ?')->execute([$lessonId]);
            flash('success', 'Learning content deleted.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/teacher/lessons.php');
}

$stmt = $pdo->prepare('SELECT DISTINCT co.id, co.code, co.name
                       FROM courses co
                       JOIN classes c ON c.course_id = co.id
                       WHERE c.teacher_id = ?
                       ORDER BY co.code');
$stmt->execute([$teacherId]);
$courses = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT l.*, co.code AS course_code, co.name AS course_name,
                              COALESCE(u.used_count, 0) AS used_count
                       FROM lessons l
                       JOIN courses co ON co.id = l.course_id
                       JOIN (SELECT DISTINCT course_id FROM classes WHERE teacher_id = ?) tc ON tc.course_id = l.course_id
                       LEFT JOIN (
                           SELECT lesson_id, COUNT(*) used_count
                           FROM chat_messages
                           WHERE lesson_id IS NOT NULL
                           GROUP BY lesson_id
                       ) u ON u.lesson_id = l.id
                       ORDER BY co.code, l.order_index, l.id DESC');
$stmt->execute([$teacherId]);
$lessons = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="split teacher-knowledge-layout">
    <section class="card">
        <div class="card-head">
            <div>
                <h2><?= $edit ? 'Update Lesson Content' : 'Add Lesson Knowledge' ?></h2>
                <p class="page-intro">Add or update learning content for the courses assigned to your classes. The chatbot will use this content when answering student questions.</p>
            </div>
        </div>

        <?php if (!$courses): ?>
            <div class="empty-state">
                <div class="empty-icon">◇</div>
                <p>No course has been assigned to your classes yet.</p>
            </div>
        <?php else: ?>
            <form method="post" class="form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

                <div class="form-row">
                    <div class="field">
                        <label>Course</label>
                        <select name="course_id" required>
                            <option value="">Choose course</option>
                            <?php foreach($courses as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= selected($edit['course_id'] ?? '', $c['id']) ?>><?= e($c['code'] . ' - ' . $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Order</label>
                        <input class="input" type="number" name="order_index" value="<?= e($edit['order_index'] ?? 0) ?>">
                    </div>
                </div>

                <div class="field">
                    <label>Lesson title</label>
                    <input class="input" name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="Example: Chapter 5 — Decision Tree">
                </div>

                <div class="field">
                    <label>Upload lesson file</label>
                    <input class="input" type="file" name="lesson_file" accept=".txt,.md,.csv,.json,.py,.sql,.html,.htm,.pdf">
                    <p class="help">TXT/MD/CSV/HTML/code files are read directly. PDF extraction uses the Python AI service, so start <code>ai_service/start_ai_service.bat</code> before uploading a PDF.</p>
                </div>

                <div class="field">
                    <label>Stored source file</label>
                    <input class="input" name="file_url" value="<?= e($edit['file_url'] ?? '') ?>" placeholder="Optional, auto-filled after upload">
                </div>

                <div class="field">
                    <label>Lesson content for chatbot</label>
                    <textarea name="content" placeholder="Paste lesson text here, or leave empty if uploading a readable file."><?= e($edit['content'] ?? '') ?></textarea>
                    <p class="help">This text is saved in <code>lessons.content</code>. The chatbot searches lesson title and content by keyword.</p>
                </div>

                <div class="actions">
                    <button class="btn"><?= $edit ? 'Save changes' : 'Add knowledge' ?></button>
                    <?php if ($edit): ?><a class="btn secondary" href="lessons.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>For lecturers</h2>
        <ul class="soft-list">
            <li>You can add knowledge only for courses assigned to your classes.</li>
            <li>The content is shared with students when they use Learning Chat.</li>
            <li>Better lesson text gives better chatbot answers.</li>
            <li>Admin still controls course, class and account management.</li>
        </ul>
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="card-head">
        <div>
            <h2>My Knowledge Library</h2>
            <p class="page-intro">Lessons available for chatbot retrieval in your assigned courses.</p>
        </div>
    </div>

    <?php if (!$lessons): ?>
        <div class="empty-state">
            <div class="empty-icon">◇</div>
            <p>No learning content has been added for your assigned courses yet.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table compact">
                <tr><th>Lesson</th><th>Course</th><th>Source</th><th>Order</th><th>Used</th><th>Action</th></tr>
                <?php foreach($lessons as $l): ?>
                    <tr>
                        <td><b><?= e($l['title']) ?></b><br><small class="muted"><?= e(mb_substr(strip_tags($l['content'] ?? ''), 0, 120, 'UTF-8')) ?>...</small></td>
                        <td><?= e($l['course_code']) ?></td>
                        <td><?php if (!empty($l['file_url'])): ?><small class="muted"><?= e($l['file_url']) ?></small><?php else: ?><span class="muted">Manual text</span><?php endif; ?></td>
                        <td><?= (int)$l['order_index'] ?></td>
                        <td><?= (int)$l['used_count'] ?></td>
                        <td class="actions">
                            <a class="btn ghost btn-sm" href="?edit=<?= (int)$l['id'] ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this content?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                <button class="btn danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
