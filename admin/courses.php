<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$title = 'Courses';

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $code = strtoupper(trim($_POST['code']));
            $name = trim($_POST['name']);
            if (!$code || !$name) throw new Exception('Course code and name are required.');
            $pdo->prepare('INSERT INTO courses(code,name,description,created_by) VALUES(?,?,?,?)')
                ->execute([$code, $name, trim($_POST['description'] ?? ''), current_user()['id']]);
            flash('success', 'Course created.');
        } elseif ($action === 'update') {
            $pdo->prepare('UPDATE courses SET code=?, name=?, description=? WHERE id=?')
                ->execute([strtoupper(trim($_POST['code'])), trim($_POST['name']), trim($_POST['description'] ?? ''), (int)$_POST['id']]);
            flash('success', 'Course updated.');
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE course_id=?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) throw new Exception('Cannot delete course with existing classes.');
            $pdo->prepare('DELETE FROM courses WHERE id=?')->execute([$id]);
            flash('success', 'Course deleted.');
        }
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/admin/courses.php');
}

$courses = $pdo->query('SELECT co.*, COUNT(c.id) AS class_count FROM courses co LEFT JOIN classes c ON c.course_id=co.id GROUP BY co.id ORDER BY co.id DESC')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="split">
    <div class="card">
        <h2><?= $edit ? 'Update Course' : 'Create Course' ?></h2>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="field"><label>Code</label><input class="input" name="code" value="<?= e($edit['code'] ?? '') ?>" required></div>
            <div class="field"><label>Name</label><input class="input" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
            <div class="field"><label>Description</label><textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
            <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Create course' ?></button>
            <?php if ($edit): ?><a class="btn secondary" href="courses.php">Cancel</a><?php endif; ?>
        </form>
    </div>
    <div class="card"><h2>Quality Rules</h2><ul><li>Course code is unique.</li><li>Course with active classes cannot be deleted.</li><li>Course creator is current admin.</li></ul></div>
</div>
<div class="card" style="margin-top:16px">
<h2>Course List</h2>
<table class="table"><tr><th>ID</th><th>Code</th><th>Name</th><th>Classes</th><th>Action</th></tr>
<?php foreach($courses as $c): ?><tr><td><?= (int)$c['id'] ?></td><td><?= e($c['code']) ?></td><td><?= e($c['name']) ?></td><td><?= (int)$c['class_count'] ?></td><td class="actions"><a class="btn ghost" href="?edit=<?= (int)$c['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this course?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn danger">Delete</button></form></td></tr><?php endforeach; ?>
</table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
