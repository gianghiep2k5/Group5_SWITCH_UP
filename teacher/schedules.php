<?php
require_once __DIR__ . '/../includes/auth.php';
require_teacher();
$title = 'Schedule';
$user = current_user();

$edit=null;
if(isset($_GET['edit'])){
    $stmt=$pdo->prepare('SELECT s.* FROM schedules s JOIN classes c ON c.id=s.class_id WHERE s.id=? AND c.teacher_id=?');
    $stmt->execute([(int)$_GET['edit'],$user['id']]);
    $edit=$stmt->fetch();
}

if(is_post()){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='create'||$action==='update'){
            $class_id=(int)$_POST['class_id']; $titleIn=trim($_POST['title']); $start=$_POST['start_time']; $end=$_POST['end_time'];
            if(!$class_id||!$titleIn||!$start||!$end) throw new Exception('Class, title, start time and end time are required.');
            if($start >= $end) throw new Exception('End time must be after start time.');
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM classes WHERE id=? AND teacher_id=?'); $stmt->execute([$class_id,$user['id']]);
            if((int)$stmt->fetchColumn()===0) throw new Exception('You can only manage schedules of your own classes.');
            $ignore = $action==='update' ? ' AND id <> '.(int)$_POST['id'] : '';
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM schedules WHERE class_id=? AND start_time < ? AND end_time > ? {$ignore}");
            $stmt->execute([$class_id,$end,$start]);
            if((int)$stmt->fetchColumn()>0) throw new Exception('Schedule conflict: this class already has another lesson in this time range.');
            if($action==='create'){
                $pdo->prepare('INSERT INTO schedules(class_id,title,start_time,end_time,room,note) VALUES(?,?,?,?,?,?)')
                    ->execute([$class_id,$titleIn,$start,$end,trim($_POST['room']),trim($_POST['note'])]);
                flash('success','Schedule created.');
            }else{
                $pdo->prepare('UPDATE schedules SET class_id=?,title=?,start_time=?,end_time=?,room=?,note=? WHERE id=?')
                    ->execute([$class_id,$titleIn,$start,$end,trim($_POST['room']),trim($_POST['note']),(int)$_POST['id']]);
                flash('success','Schedule updated.');
            }
        }elseif($action==='delete'){
            $id=(int)$_POST['id'];
            $stmt=$pdo->prepare('DELETE s FROM schedules s JOIN classes c ON c.id=s.class_id WHERE s.id=? AND c.teacher_id=?');
            $stmt->execute([$id,$user['id']]);
            flash('success','Schedule deleted.');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());}
    redirect('/teacher/schedules.php');
}

$stmt=$pdo->prepare('SELECT id,name FROM classes WHERE teacher_id=? ORDER BY name'); $stmt->execute([$user['id']]); $classes=$stmt->fetchAll();
$stmt=$pdo->prepare('SELECT s.*, c.name class_name FROM schedules s JOIN classes c ON c.id=s.class_id WHERE c.teacher_id=? ORDER BY s.start_time DESC'); $stmt->execute([$user['id']]); $schedules=$stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="split"><div class="card"><h2><?= $edit?'Update Schedule':'Create Schedule' ?></h2><form method="post" class="form">
<?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit?'update':'create' ?>"><?php if($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
<div class="field"><label>Class</label><select name="class_id" required><option value="">-- Choose --</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= selected($edit['class_id']??'', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Title</label><input class="input" name="title" value="<?= e($edit['title']??'') ?>" required></div>
<div class="form-row"><div class="field"><label>Start time</label><input class="input" type="datetime-local" name="start_time" value="<?= isset($edit['start_time']) ? e(str_replace(' ', 'T', substr($edit['start_time'],0,16))) : '' ?>" required></div><div class="field"><label>End time</label><input class="input" type="datetime-local" name="end_time" value="<?= isset($edit['end_time']) ? e(str_replace(' ', 'T', substr($edit['end_time'],0,16))) : '' ?>" required></div></div>
<div class="field"><label>Room</label><input class="input" name="room" value="<?= e($edit['room']??'') ?>"></div><div class="field"><label>Note</label><textarea name="note"><?= e($edit['note']??'') ?></textarea></div>
<button class="btn"><?= $edit?'Save changes':'Create schedule' ?></button><?php if($edit): ?><a class="btn secondary" href="schedules.php">Cancel</a><?php endif; ?></form></div>
<div class="card"><h2>Schedule Rules</h2><ul><li>Lecturers can only manage their own classes.</li><li>End time must be after start time.</li><li>Conflict detection prevents overlapping schedules in the same class.</li></ul></div></div>
<div class="card" style="margin-top:16px"><h2>Schedule List</h2><table class="table"><tr><th>Class</th><th>Title</th><th>Time</th><th>Room</th><th>Action</th></tr><?php foreach($schedules as $s): ?><tr><td><?= e($s['class_name']) ?></td><td><?= e($s['title']) ?></td><td><?= e($s['start_time'].' → '.$s['end_time']) ?></td><td><?= e($s['room']) ?></td><td class="actions"><a class="btn ghost" href="?edit=<?= (int)$s['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete schedule?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn danger">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
