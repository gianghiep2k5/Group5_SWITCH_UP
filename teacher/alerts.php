<?php
require_once __DIR__ . '/../includes/auth.php';
require_teacher();
$title = 'Learning Alerts';
$user = current_user();

if(is_post()){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='mark_read'){
            $pdo->prepare('UPDATE teacher_alerts SET is_read=1, read_at=NOW() WHERE id=? AND teacher_id=?')->execute([(int)$_POST['id'],$user['id']]);
            flash('success','Alert marked as read.');
        }elseif($action==='mark_unread'){
            $pdo->prepare('UPDATE teacher_alerts SET is_read=0, read_at=NULL WHERE id=? AND teacher_id=?')->execute([(int)$_POST['id'],$user['id']]);
            flash('success','Alert marked as unread.');
        }elseif($action==='delete'){
            $pdo->prepare('DELETE FROM teacher_alerts WHERE id=? AND teacher_id=?')->execute([(int)$_POST['id'],$user['id']]);
            flash('success','Alert deleted.');
        }elseif($action==='create_manual'){
            $class_id=(int)$_POST['class_id'];
            $student_id=$_POST['student_id']!==''?(int)$_POST['student_id']:null;
            $topic_id=$_POST['topic_id']!==''?(int)$_POST['topic_id']:null;
            $message=trim($_POST['message']);
            if(!$class_id||!$message) throw new Exception('Class and message are required.');
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM classes WHERE id=? AND teacher_id=?'); $stmt->execute([$class_id,$user['id']]);
            if((int)$stmt->fetchColumn()===0) throw new Exception('You can only create alerts for your own classes.');
            $pdo->prepare("INSERT INTO teacher_alerts(teacher_id,class_id,student_id,topic_id,alert_type,message,severity) VALUES(?,?,?,?,?,?,?)")
                ->execute([$user['id'],$class_id,$student_id,$topic_id,'other',$message,$_POST['severity']]);
            flash('success','Manual alert created.');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());}
    redirect('/teacher/alerts.php');
}

$stmt=$pdo->prepare('SELECT id,name FROM classes WHERE teacher_id=? ORDER BY name'); $stmt->execute([$user['id']]); $classes=$stmt->fetchAll();
$topics=$pdo->query('SELECT id,name FROM question_topics ORDER BY name')->fetchAll();
$students=$pdo->query("SELECT id,full_name FROM users WHERE role='student' ORDER BY full_name")->fetchAll();
$stmt=$pdo->prepare("SELECT a.*, c.name class_name, s.full_name student_name, t.name topic_name FROM teacher_alerts a LEFT JOIN classes c ON c.id=a.class_id LEFT JOIN users s ON s.id=a.student_id LEFT JOIN question_topics t ON t.id=a.topic_id WHERE a.teacher_id=? ORDER BY a.is_read ASC, a.created_at DESC");
$stmt->execute([$user['id']]); $alerts=$stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="split"><div class="card"><h2>Create Alert</h2><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="create_manual"><div class="field"><label>Class</label><select name="class_id" required><option value="">-- Choose --</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="form-row"><div class="field"><label>Student</label><select name="student_id"><option value="">Whole class</option><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['full_name']) ?></option><?php endforeach; ?></select></div><div class="field"><label>Topic</label><select name="topic_id"><option value="">General</option><?php foreach($topics as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div></div><div class="field"><label>Severity</label><select name="severity"><option value="low">low</option><option value="medium" selected>medium</option><option value="high">high</option></select></div><div class="field"><label>Message</label><textarea name="message" required></textarea></div><button class="btn">Save alert</button></form></div><div class="card"><h2>Alert Rules</h2><ul><li>Alerts belong to one teacher.</li><li>Lecturers can only create alerts for their own classes.</li><li>Repeated questions are highlighted automatically.</li><li>Alerts can be marked as read or unread.</li></ul></div></div>
<div class="card" style="margin-top:16px"><h2>Alert List</h2><table class="table"><tr><th>Status</th><th>Severity</th><th>Type</th><th>Class</th><th>Student</th><th>Topic</th><th>Message</th><th>Action</th></tr><?php foreach($alerts as $a): ?><tr><td><?= $a['is_read']?badge('read','low'):badge('unread','high') ?></td><td><?= badge($a['severity'],$a['severity']) ?></td><td><?= e($a['alert_type']) ?></td><td><?= e($a['class_name']??'-') ?></td><td><?= e($a['student_name']??'-') ?></td><td><?= e($a['topic_name']??'-') ?></td><td><?= e($a['message']) ?><br><small class="muted"><?= e($a['created_at']) ?></small></td><td><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?php if($a['is_read']): ?><button class="btn ghost" name="action" value="mark_unread">Unread</button><?php else: ?><button class="btn ghost" name="action" value="mark_read">Read</button><?php endif; ?><button class="btn danger" name="action" value="delete" onclick="return confirm('Delete alert?')">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
