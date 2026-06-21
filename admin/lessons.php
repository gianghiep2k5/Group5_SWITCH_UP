<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$title = 'Learning Content';

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM lessons WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' || $action === 'update') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $titleLesson = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $orderIndex = (int)($_POST['order_index'] ?? 0);
            if (!$courseId || !$titleLesson || !$content) throw new Exception('Course, title and content are required.');
            if ($action === 'create') {
                $pdo->prepare('INSERT INTO lessons(course_id,title,content,file_url,order_index) VALUES(?,?,?,?,?)')
                    ->execute([$courseId, $titleLesson, $content, trim($_POST['file_url'] ?? ''), $orderIndex]);
                flash('success', 'Learning content added.');
            } else {
                $pdo->prepare('UPDATE lessons SET course_id=?, title=?, content=?, file_url=?, order_index=? WHERE id=?')
                    ->execute([$courseId, $titleLesson, $content, trim($_POST['file_url'] ?? ''), $orderIndex, (int)$_POST['id']]);
                flash('success', 'Learning content updated.');
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_messages WHERE lesson_id=?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) throw new Exception('Cannot delete content that is already linked to chat history.');
            $pdo->prepare('DELETE FROM lessons WHERE id=?')->execute([$id]);
            flash('success', 'Learning content deleted.');
        }
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/admin/lessons.php');
}

$courses = $pdo->query('SELECT id, code, name FROM courses ORDER BY code')->fetchAll();
$lessons = $pdo->query('SELECT l.*, co.code course_code, co.name course_name, COUNT(m.id) used_count
                        FROM lessons l
                        JOIN courses co ON co.id=l.course_id
                        LEFT JOIN chat_messages m ON m.lesson_id=l.id
                        GROUP BY l.id
                        ORDER BY co.code, l.order_index, l.id DESC')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="split">
    <div class="card">
        <div class="card-head"><div><h2><?= $edit ? 'Update Content' : 'Add Content' ?></h2><p class="page-intro">This content is used by the student chatbot to prepare lesson-based answers.</p></div></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="field"><label>Course</label><select name="course_id" required><option value="">Choose course</option><?php foreach($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($edit['course_id'] ?? '', $c['id']) ?>><?= e($c['code'].' - '.$c['name']) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Order</label><input class="input" type="number" name="order_index" value="<?= e($edit['order_index'] ?? 0) ?>"></div>
            </div>
            <div class="field"><label>Title</label><input class="input" name="title" value="<?= e($edit['title'] ?? '') ?>" required></div>
            <div class="field"><label>Source file</label><input class="input" name="file_url" value="<?= e($edit['file_url'] ?? '') ?>" placeholder="Optional: textbook.pdf"></div>
            <div class="field"><label>Lesson content</label><textarea name="content" required><?= e($edit['content'] ?? '') ?></textarea></div>
            <div class="actions"><button class="btn"><?= $edit ? 'Save changes' : 'Add content' ?></button><?php if ($edit): ?><a class="btn secondary" href="lessons.php">Cancel</a><?php endif; ?></div>
        </form>
    </div>
    <div class="card">
        <h2>Content Rule</h2>
        <ul class="soft-list">
            <li>Each lesson belongs to one course.</li>
            <li>The chatbot searches title and content by keywords.</li>
            <li>Used lessons are kept to protect chat history integrity.</li>
        </ul>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div class="card-head"><div><h2>Content Library</h2><p class="page-intro">Lessons available for retrieval in student chat.</p></div></div>
    <div class="table-wrap"><table class="table">
        <tr><th>Lesson</th><th>Course</th><th>Order</th><th>Used</th><th>Action</th></tr>
        <?php foreach($lessons as $l): ?>
            <tr>
                <td><b><?= e($l['title']) ?></b><br><small class="muted"><?= e(substr(strip_tags($l['content']),0,120)) ?>...</small></td>
                <td><?= e($l['course_code']) ?></td>
                <td><?= (int)$l['order_index'] ?></td>
                <td><?= (int)$l['used_count'] ?></td>
                <td class="actions"><a class="btn ghost" href="?edit=<?= (int)$l['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this content?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn danger">Delete</button></form></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
