<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$title = 'Classes';

$edit = null;
if (isset($_GET['edit'])) { $stmt=$pdo->prepare('SELECT * FROM classes WHERE id=?'); $stmt->execute([(int)$_GET['edit']]); $edit=$stmt->fetch(); }

if (is_post()) {
    verify_csrf();
    $action=$_POST['action']??'';
    try {
        if ($action==='create' || $action==='update') {
            $course_id=(int)$_POST['course_id']; $teacher_id=(int)$_POST['teacher_id']; $name=trim($_POST['name']);
            $start=$_POST['start_date'] ?: null; $end=$_POST['end_date'] ?: null;
            if (!$course_id || !$teacher_id || !$name) throw new Exception('Course, teacher and class name are required.');
            if ($start && $end && $start > $end) throw new Exception('Start date cannot be after end date.');
            if ($action==='create') {
                $pdo->prepare('INSERT INTO classes(course_id,teacher_id,name,semester,start_date,end_date,is_active) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$course_id,$teacher_id,$name,trim($_POST['semester']),$start,$end,isset($_POST['is_active'])?1:0]);
                flash('success','Class created.');
            } else {
                $pdo->prepare('UPDATE classes SET course_id=?,teacher_id=?,name=?,semester=?,start_date=?,end_date=?,is_active=? WHERE id=?')
                    ->execute([$course_id,$teacher_id,$name,trim($_POST['semester']),$start,$end,isset($_POST['is_active'])?1:0,(int)$_POST['id']]);
                flash('success','Class updated.');
            }
        } elseif ($action==='delete') {
            $id=(int)$_POST['id'];
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM class_students WHERE class_id=?'); $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn()>0) throw new Exception('Cannot delete class with enrolled students.');
            $pdo->prepare('DELETE FROM classes WHERE id=?')->execute([$id]);
            flash('success','Class deleted.');
        }
    } catch(Throwable $e){ flash('error',$e->getMessage()); }
    redirect('/admin/classes.php');
}

$courses=$pdo->query('SELECT id,code,name FROM courses ORDER BY code')->fetchAll();
$teachers=$pdo->query("SELECT id,full_name FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
$classes=$pdo->query("SELECT c.*, co.code, u.full_name AS teacher_name, COUNT(cs.id) student_count FROM classes c JOIN courses co ON co.id=c.course_id JOIN users u ON u.id=c.teacher_id LEFT JOIN class_students cs ON cs.class_id=c.id GROUP BY c.id ORDER BY c.id DESC")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="split"><div class="card"><h2><?= $edit?'Update Class':'Create Class' ?></h2><form method="post" class="form">
<?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit?'update':'create' ?>"><?php if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
<div class="form-row"><div class="field"><label>Course</label><select name="course_id" required><option value="">-- Choose --</option><?php foreach($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($edit['course_id']??'', $c['id']) ?>><?= e($c['code'].' - '.$c['name']) ?></option><?php endforeach; ?></select></div><div class="field"><label>Lecturer</label><select name="teacher_id" required><option value="">-- Choose --</option><?php foreach($teachers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= selected($edit['teacher_id']??'', $t['id']) ?>><?= e($t['full_name']) ?></option><?php endforeach; ?></select></div></div>
<div class="field"><label>Class name</label><input class="input" name="name" value="<?= e($edit['name']??'') ?>" required></div>
<div class="form-row"><div class="field"><label>Semester</label><input class="input" name="semester" value="<?= e($edit['semester']??'') ?>"></div><div class="field"><label>Active</label><label><input type="checkbox" name="is_active" <?= checked($edit['is_active']??1) ?>> Active</label></div></div>
<div class="form-row"><div class="field"><label>Start date</label><input class="input" type="date" name="start_date" value="<?= e($edit['start_date']??'') ?>"></div><div class="field"><label>End date</label><input class="input" type="date" name="end_date" value="<?= e($edit['end_date']??'') ?>"></div></div>
<button class="btn"><?= $edit?'Save changes':'Create class' ?></button><?php if($edit): ?><a class="btn secondary" href="classes.php">Cancel</a><?php endif; ?></form></div>
<div class="card"><h2>Class Rules</h2><ul><li>Class must belong to one course.</li><li>Class must be assigned to one teacher.</li><li>Start date must not be after end date.</li><li>Class with students cannot be deleted.</li></ul></div></div>
<div class="card" style="margin-top:16px"><h2>Class List</h2><table class="table"><tr><th>ID</th><th>Class</th><th>Course</th><th>Lecturer</th><th>Students</th><th>Status</th><th>Action</th></tr><?php foreach($classes as $c): ?><tr><td><?= (int)$c['id'] ?></td><td><?= e($c['name']) ?></td><td><?= e($c['code']) ?></td><td><?= e($c['teacher_name']) ?></td><td><?= (int)$c['student_count'] ?></td><td><?= $c['is_active']?badge('active','low'):badge('inactive','medium') ?></td><td class="actions"><a class="btn ghost" href="?edit=<?= (int)$c['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this class?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn danger">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
