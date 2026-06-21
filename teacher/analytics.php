<?php
require_once __DIR__ . '/../includes/auth.php';
require_teacher();
$title = 'Learning Insights';
$user=current_user();

$stmt=$pdo->prepare("SELECT la.*, u.full_name student_name, c.name class_name, qt.name topic_name FROM learning_analytics la JOIN users u ON u.id=la.student_id LEFT JOIN classes c ON c.id=la.class_id LEFT JOIN question_topics qt ON qt.id=la.top_topic_id WHERE c.teacher_id=? OR c.id IS NULL ORDER BY la.date DESC, la.repeat_score DESC");
$stmt->execute([$user['id']]); $rows=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT qt.name topic, COUNT(*) n FROM chat_messages m JOIN question_topics qt ON qt.id=m.topic_id JOIN chat_sessions cs ON cs.id=m.session_id JOIN class_students cls ON cls.student_id=cs.student_id JOIN classes c ON c.id=cls.class_id WHERE m.sender='student' AND c.teacher_id=? GROUP BY qt.id ORDER BY n DESC LIMIT 5");
$stmt->execute([$user['id']]); $topTopics=$stmt->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2"><div class="card"><h2>Top Asked Topics</h2><div class="table-wrap"><table class="table"><tr><th>Topic</th><th>Questions</th></tr><?php foreach($topTopics as $r): ?><tr><td><?= e($r['topic']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table></div></div><div class="card"><h2>How to Use Insights</h2><p class="page-intro">Use this page to identify topics that students ask about repeatedly. High repeat scores suggest that the topic should be reviewed again in class.</p><ul class="soft-list"><li>Check the most asked topics before planning the next lesson.</li><li>Review students with high repeat scores.</li><li>Open alerts when a topic needs immediate attention.</li></ul></div></div>
<div class="card" style="margin-top:16px"><h2>Student Learning Frequency</h2><div class="table-wrap"><table class="table"><tr><th>Date</th><th>Class</th><th>Student</th><th>Sessions</th><th>Questions</th><th>Top Topic</th><th>Repeat Score</th></tr><?php foreach($rows as $r): ?><tr><td><?= e($r['date']) ?></td><td><?= e($r['class_name']??'-') ?></td><td><?= e($r['student_name']) ?></td><td><?= (int)$r['session_count'] ?></td><td><?= (int)$r['question_count'] ?></td><td><?= e($r['topic_name']??'-') ?></td><td><?= ((float)$r['repeat_score']>=0.7)?badge($r['repeat_score'],'high'):badge($r['repeat_score'],'low') ?></td></tr><?php endforeach; ?></table></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
