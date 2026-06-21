<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('error', 'Please login first.');
        redirect('/login.php');
    }
}

function require_role(array $roles): void {
    require_login();
    $role = current_user()['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        die('403 Forbidden: You do not have permission to access this page.');
    }
}

function require_admin(): void { require_role(['admin']); }
function require_teacher(): void { require_role(['teacher']); }
function require_student(): void { require_role(['student']); }

function teacher_class_condition_sql(string $alias = 'c'): string {
    $u = current_user();
    if (($u['role'] ?? '') === 'teacher') {
        return " {$alias}.teacher_id = " . (int)$u['id'] . " ";
    }
    return ' 1=1 ';
}
