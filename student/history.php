<?php
require_once __DIR__ . '/../includes/auth.php';
require_student();
$title = 'My Learning History';
$user=current_user();

if(is_post()){
    verify_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='delete'){
            $pdo->prepare('DELETE FROM chat_sessions WHERE id=? AND student_id=?')->execute([(int)$_POST['id'],$user['id']]);
            if(($_SESSION['current_chat_session_id']??0)==(int)$_POST['id']) unset($_SESSION['current_chat_session_id']);
            flash('success','Chat session deleted.');
        }elseif($action==='continue'){
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM chat_sessions WHERE id=? AND student_id=?'); $stmt->execute([(int)$_POST['id'],$user['id']]);
            if((int)$stmt->fetchColumn()===0) throw new Exception('Invalid session.');
            $_SESSION['current_chat_session_id']=(int)$_POST['id'];
            redirect('/student/chat.php');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());}
    redirect('/student/history.php');
}

$stmt=$pdo->prepare('SELECT s.*, co.code course_code, co.name course_name FROM chat_sessions s LEFT JOIN courses co ON co.id=s.course_id WHERE s.student_id=? ORDER BY s.started_at DESC');
$stmt->execute([$user['id']]); $sessions=$stmt->fetchAll();
$detail=[];
if(isset($_GET['view'])){
    $sid=(int)$_GET['view'];
    $stmt=$pdo->prepare('SELECT m.*, qt.name topic_name, l.title lesson_title FROM chat_messages m LEFT JOIN question_topics qt ON qt.id=m.topic_id LEFT JOIN lessons l ON l.id=m.lesson_id JOIN chat_sessions s ON s.id=m.session_id WHERE m.session_id=? AND s.student_id=? ORDER BY m.created_at ASC, m.id ASC');
    $stmt->execute([$sid,$user['id']]); $detail=$stmt->fetchAll();
}
include __DIR__ . '/../includes/header.php';
?>
<div class="split"><div class="card"><h2>Sessions</h2><table class="table"><tr><th>Title</th><th>Course</th><th>Messages</th><th>Started</th><th>Action</th></tr><?php foreach($sessions as $s): ?><tr><td><?= e($s['title']??'Untitled') ?></td><td><?= e(($s['course_code']??'-').' '.$s['course_name']) ?></td><td><?= (int)$s['message_count'] ?></td><td><?= e($s['started_at']) ?></td><td><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><a class="btn ghost" href="?view=<?= (int)$s['id'] ?>">View</a><button class="btn ghost" name="action" value="continue">Continue</button><button class="btn danger" name="action" value="delete" onclick="return confirm('Delete this chat session?')">Delete</button></form></td></tr><?php endforeach; ?></table></div><div class="card"><h2>Selected Conversation</h2><?php if(!$detail): ?><p class="muted">Choose a session to view detail.</p><?php endif; ?><div class="chat-box"><?php foreach($detail as $m): ?><div class="msg <?= e($m['sender']) ?>"><?= e($m['content']) ?><br><small class="muted">Topic: <?= e($m['topic_name']??'-') ?> · Lesson: <?= e($m['lesson_title']??'-') ?></small></div><?php endforeach; ?></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
