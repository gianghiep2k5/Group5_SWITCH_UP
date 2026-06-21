<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$title = 'Lecturers';

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $full = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            if (!$full || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid name or email.');
            $hash = password_hash($_POST['password'] ?: DEFAULT_PASSWORD, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,role,parent_id,is_active) VALUES(?,?,?,?,?,?)");
            $stmt->execute([$full, $email, $hash, 'teacher', current_user()['id'], isset($_POST['is_active']) ? 1 : 0]);
            flash('success', 'Lecturer added.');
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            $full = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            if (!$full || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid name or email.');
            $pdo->prepare("UPDATE users SET full_name=?, email=?, is_active=? WHERE id=? AND role='teacher'")
                ->execute([$full, $email, isset($_POST['is_active']) ? 1 : 0, $id]);
            if (!empty($_POST['password'])) {
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='teacher'")
                    ->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            flash('success', 'Lecturer updated.');
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE teacher_id=?');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) throw new Exception('Cannot delete teacher who is assigned to classes. Lock the account instead.');
            $pdo->prepare("DELETE FROM users WHERE id=? AND role='teacher'")->execute([$id]);
            flash('success', 'Lecturer removed.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/teachers.php');
}

$teachers = $pdo->query("SELECT u.*, COUNT(c.id) AS class_count FROM users u LEFT JOIN classes c ON c.teacher_id=u.id WHERE u.role='teacher' GROUP BY u.id ORDER BY u.id DESC")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="split">
    <div class="card">
        <h2><?= $edit ? 'Update Lecturer' : 'Add Lecturer' ?></h2>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="field"><label>Full name</label><input class="input" name="full_name" value="<?= e($edit['full_name'] ?? '') ?>" required></div>
            <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required></div>
            <div class="field"><label>Password <?= $edit ? '(leave blank to keep old)' : '' ?></label><input class="input" name="password" type="password" placeholder="Default: 123456"></div>
            <label><input type="checkbox" name="is_active" <?= checked($edit['is_active'] ?? 1) ?>> Active</label>
            <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Create lecturer' ?></button>
            <?php if ($edit): ?><a class="btn secondary" href="teachers.php">Cancel</a><?php endif; ?>
        </form>
    </div>
    <div class="card">
        <h2>Account Rules</h2>
        <ul>
            <li>Email must be unique and valid.</li>
            <li>Each lecturer is linked to the current administrator.</li>
            <li>Lecturers assigned to classes cannot be removed.</li>
            <li>Passwords are stored securely.</li>
        </ul>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>Lecturer List</h2>
    <div class="table-wrap"><table class="table">
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Classes</th><th>Action</th></tr>
        <?php foreach ($teachers as $t): ?>
            <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><?= e($t['full_name']) ?></td>
                <td><?= e($t['email']) ?></td>
                <td><?= $t['is_active'] ? badge('active','low') : badge('locked','high') ?></td>
                <td><?= (int)$t['class_count'] ?></td>
                <td class="actions">
                    <a class="btn ghost" href="?edit=<?= (int)$t['id'] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this lecturer?')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button class="btn danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
