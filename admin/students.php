<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$title = 'Students';

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='student'");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' || $action === 'update') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $active = isset($_POST['is_active']) ? 1 : 0;
            if (!$fullName || !$email) throw new Exception('Student name and email are required.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email format is invalid.');

            if ($action === 'create') {
                $hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,parent_id,is_active) VALUES(?,?,?,?,?,?)")
                    ->execute([$fullName, $email, $hash, 'student', current_user()['id'], $active]);
                flash('success', 'Student account created. Default password is ' . DEFAULT_PASSWORD . '.');
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, is_active=? WHERE id=? AND role='student'")
                    ->execute([$fullName, $email, $active, (int)$_POST['id']]);
                if (!empty($_POST['reset_password'])) {
                    $hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='student'")->execute([$hash, (int)$_POST['id']]);
                }
                flash('success', 'Student account updated.');
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM class_students WHERE student_id=?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) throw new Exception('Cannot delete student with existing class enrollment. Lock the account instead.');
            $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$id]);
            flash('success', 'Student account deleted.');
        } elseif ($action === 'enroll') {
            $classId = (int)($_POST['class_id'] ?? 0);
            $studentId = (int)($_POST['student_id'] ?? 0);
            if (!$classId || !$studentId) throw new Exception('Please choose class and student.');
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM class_students WHERE class_id=? AND student_id=?');
            $stmt->execute([$classId, $studentId]);
            if ((int)$stmt->fetchColumn() > 0) throw new Exception('This student is already enrolled in the selected class.');
            $pdo->prepare('INSERT INTO class_students(class_id,student_id,status) VALUES(?,?,?)')
                ->execute([$classId, $studentId, 'active']);
            flash('success', 'Student enrolled to class.');
        } elseif ($action === 'update_enrollment') {
            $status = $_POST['status'] ?? 'active';
            if (!in_array($status, ['active','dropped','completed'], true)) throw new Exception('Invalid enrollment status.');
            $pdo->prepare('UPDATE class_students SET status=? WHERE id=?')->execute([$status, (int)$_POST['id']]);
            flash('success', 'Enrollment updated.');
        } elseif ($action === 'delete_enrollment') {
            $pdo->prepare('DELETE FROM class_students WHERE id=?')->execute([(int)$_POST['id']]);
            flash('success', 'Student removed from class.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/students.php');
}

$students = $pdo->query("SELECT u.*, COUNT(cs.id) class_count
                         FROM users u
                         LEFT JOIN class_students cs ON cs.student_id=u.id
                         WHERE u.role='student'
                         GROUP BY u.id
                         ORDER BY u.id DESC")->fetchAll();

$activeStudents = $pdo->query("SELECT id, full_name, email FROM users WHERE role='student' AND is_active=1 ORDER BY full_name")->fetchAll();
$classes = $pdo->query("SELECT c.id, c.name, co.code, u.full_name AS teacher_name
                        FROM classes c
                        JOIN courses co ON co.id=c.course_id
                        JOIN users u ON u.id=c.teacher_id
                        WHERE c.is_active=1
                        ORDER BY co.code, c.name")->fetchAll();

$classFilter = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$enrollments = [];
if ($classFilter) {
    $stmt = $pdo->prepare("SELECT cs.*, u.full_name, u.email, c.name AS class_name, co.code, t.full_name AS teacher_name
                           FROM class_students cs
                           JOIN users u ON u.id=cs.student_id
                           JOIN classes c ON c.id=cs.class_id
                           JOIN courses co ON co.id=c.course_id
                           JOIN users t ON t.id=c.teacher_id
                           WHERE cs.class_id=?
                           ORDER BY u.full_name");
    $stmt->execute([$classFilter]);
    $enrollments = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><div><h2><?= $edit ? 'Update Student' : 'Add Student' ?></h2><p class="page-intro">Admin creates and maintains learner accounts for the system.</p></div></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="field"><label>Full name</label><input class="input" name="full_name" value="<?= e($edit['full_name'] ?? '') ?>" required></div>
            <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required></div>
            <div class="form-row">
                <div class="field"><label>Status</label><label class="check-line"><input type="checkbox" name="is_active" <?= checked($edit['is_active'] ?? 1) ?>> Active account</label></div>
                <?php if ($edit): ?><div class="field"><label>Password</label><label class="check-line"><input type="checkbox" name="reset_password"> Reset to <?= e(DEFAULT_PASSWORD) ?></label></div><?php endif; ?>
            </div>
            <div class="actions"><button class="btn"><?= $edit ? 'Save changes' : 'Add student' ?></button><?php if ($edit): ?><a class="btn secondary" href="students.php">Cancel</a><?php endif; ?></div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><div><h2>Enroll Student</h2><p class="page-intro">Only Admin can add students into classes. A student can be enrolled once in the same class.</p></div></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enroll">
            <div class="field"><label>Class</label><select name="class_id" required><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'].' · '.$c['name'].' · '.$c['teacher_name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Student</label><select name="student_id" required><?php foreach($activeStudents as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['full_name'].' · '.$s['email']) ?></option><?php endforeach; ?></select></div>
            <button class="btn">Enroll student</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div class="card-head"><div><h2>Student Directory</h2><p class="page-intro">All learner accounts available in the system.</p></div></div>
    <div class="table-wrap"><table class="table compact">
        <tr><th>Name</th><th>Email</th><th>Classes</th><th>Status</th><th>Action</th></tr>
        <?php foreach($students as $s): ?>
            <tr>
                <td><b><?= e($s['full_name']) ?></b></td>
                <td><?= e($s['email']) ?></td>
                <td><?= (int)$s['class_count'] ?></td>
                <td><?= $s['is_active'] ? badge('active','low') : badge('locked','medium') ?></td>
                <td class="actions">
                    <a class="btn ghost btn-sm" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this student?')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</div>

<div class="card" style="margin-top:18px">
    <div class="card-head"><div><h2>Class Enrollment</h2><p class="page-intro">Check which students are in each class and update their enrollment status.</p></div></div>
    <form method="get" class="filter-bar">
        <div class="field"><label>Filter class</label><select name="class_id" onchange="this.form.submit()">
            <?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($classFilter,$c['id']) ?>><?= e($c['code'].' · '.$c['name']) ?></option><?php endforeach; ?>
        </select></div>
    </form>
    <div class="table-wrap"><table class="table compact">
        <tr><th>Student</th><th>Email</th><th>Lecturer</th><th>Status</th><th>Joined</th><th>Action</th></tr>
        <?php foreach($enrollments as $row): ?>
            <tr>
                <td><b><?= e($row['full_name']) ?></b></td>
                <td><?= e($row['email']) ?></td>
                <td><?= e($row['teacher_name']) ?></td>
                <td><?= badge($row['status'], $row['status']==='active'?'low':'medium') ?></td>
                <td><?= e(date('d M Y', strtotime($row['joined_at']))) ?></td>
                <td>
                    <form method="post" class="actions compact-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <select name="status" class="status-select">
                            <option value="active" <?= selected($row['status'],'active') ?>>Active</option>
                            <option value="dropped" <?= selected($row['status'],'dropped') ?>>Dropped</option>
                            <option value="completed" <?= selected($row['status'],'completed') ?>>Completed</option>
                        </select>
                        <button class="btn ghost btn-sm" name="action" value="update_enrollment">Save</button>
                        <button class="btn danger btn-sm" name="action" value="delete_enrollment" onclick="return confirm('Remove student from this class?')">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if(!$enrollments): ?><tr><td colspan="6" class="muted">No enrollment found for this class.</td></tr><?php endif; ?>
    </table></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
