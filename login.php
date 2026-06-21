<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) redirect('/index.php');

if (is_post()) {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        login_user($user);
        redirect('/index.php');
    }
    flash('error', 'Email hoặc mật khẩu chưa đúng. Vui lòng kiểm tra lại thông tin đăng nhập.');
}
$title = 'Sign in';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-shell clean-login">
    <section class="login-hero">
        <div>
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="VNUIIS logo">
            <h1><?= e(APP_NAME) ?></h1>
            <p><?= e(APP_SUBTITLE) ?> for courses, schedules, learning chat history and teacher alerts.</p>
        </div>
        <p class="help" style="color:#d7e0ef">A focused service workspace for administrators, lecturers and students.</p>
    </section>
    <section class="card login-card">
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to continue your learning workspace.</p>
        <?php if ($msg = flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <div class="field"><label>Email</label><input class="input" name="email" type="email" placeholder="Enter your email" required autofocus></div>
            <div class="field"><label>Password</label><input class="input" type="password" name="password" placeholder="Enter your password" required></div>
            <button class="btn navy btn-full" type="submit">Sign in</button>
        </form>
    </section>
</div>
</body>
</html>
