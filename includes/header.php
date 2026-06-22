<?php
require_once __DIR__ . '/auth.php';
$title = $title ?? APP_NAME;
$user = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

function nav_active(string $path): string {
    global $currentPath;
    return substr($currentPath, -strlen($path)) === $path ? ' active' : '';
}

function role_label(string $role): string {
    $labels = ['admin' => 'Administrator', 'teacher' => 'Lecturer', 'student' => 'Student'];
    return $labels[$role] ?? ucfirst($role);
}

function nav_group(string $label, array $items): void {
    echo '<div class="nav-group"><div class="nav-label">' . e($label) . '</div>';
    foreach ($items as $item) {
        [$href, $text, $icon] = $item;
        echo '<a class="' . nav_active($href) . '" href="' . BASE_URL . e($href) . '"><span class="nav-icon">' . e($icon) . '</span><span>' . e($text) . '</span></a>';
    }
    echo '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= BASE_URL ?>/index.php" aria-label="Home">
            <img class="brand-logo" src="<?= BASE_URL ?>/assets/img/logo.png" alt="VNUIIS logo">
            <div class="brand-text">
                <strong><?= e(APP_NAME) ?></strong>
                <small><?= e(APP_SUBTITLE) ?></small>
            </div>
        </a>
        <?php if ($user): ?>
            <div class="user-card">
                <span class="user-role"><?= e(role_label($user['role'])) ?></span>
                <strong><?= e($user['full_name']) ?></strong>
                <small><?= e($user['email']) ?></small>
            </div>
            <nav class="side-nav" aria-label="Main navigation">
                <?php if ($user['role'] === 'admin'): ?>
                    <?php nav_group('Workspace', [
                        ['/index.php', 'Dashboard', '⌂'],
                    ]); ?>
                    <?php nav_group('Academic Data', [
                        ['/admin/teachers.php', 'Lecturers', '◎'],
                        ['/admin/students.php', 'Students', '◌'],
                        ['/admin/courses.php', 'Courses', '◇'],
                        ['/admin/classes.php', 'Classes', '▣'],
                        ['/admin/lessons.php', 'Learning Content', '✦'],
                    ]); ?>
                <?php elseif ($user['role'] === 'teacher'): ?>
                    <?php nav_group('Teaching', [
                        ['/index.php', 'Dashboard', '⌂'],
                        ['/teacher/classes.php', 'My Classes', '▣'],
                        ['/teacher/students.php', 'Students & Chats', '◌'],
                        ['/teacher/schedules.php', 'Schedule', '◷'],
                        ['/teacher/lessons.php', 'Learning Content', '✦'],
                    ]); ?>
                    <?php nav_group('Student Insight', [
                        ['/teacher/alerts.php', 'Alerts', '◇'],
                        ['/teacher/analytics.php', 'Analytics', '✦'],
                    ]); ?>
                <?php elseif ($user['role'] === 'student'): ?>
                    <?php nav_group('Learning', [
                        ['/index.php', 'Dashboard', '⌂'],
                        ['/student/classes.php', 'My Classes', '▣'],
                        ['/student/schedules.php', 'Schedule', '◷'],
                        ['/student/chat.php', 'Learning Chat', '✦'],
                        ['/student/history.php', 'Chat History', '◇'],
                    ]); ?>
                <?php endif; ?>
                <div class="nav-group nav-bottom">
                    <a class="logout-link" href="<?= BASE_URL ?>/logout.php"><span class="nav-icon">↗</span><span>Sign out</span></a>
                </div>
            </nav>
        <?php else: ?>
            <nav class="side-nav"><a href="<?= BASE_URL ?>/login.php">Sign in</a></nav>
        <?php endif; ?>
    </aside>
    <main class="content">
        <header class="topbar">
            <div>
                <p class="eyebrow"><?= e(APP_SUBTITLE) ?></p>
                <h1><?= e($title) ?></h1>
            </div>
            <div class="topbar-right">
                <?php if ($user): ?><span class="pill"><?= e(role_label($user['role'])) ?></span><?php endif; ?>
                <span class="topbar-date"><?= date('d M Y · H:i') ?></span>
            </div>
        </header>
        <?php if ($msg = flash('success')): ?><div class="alert success"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($msg = flash('error')): ?><div class="alert error"><?= e($msg) ?></div><?php endif; ?>
