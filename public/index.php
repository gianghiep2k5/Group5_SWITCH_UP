<?php
session_start();

// Simple Autoloader
spl_autoload_register(function ($class) {
    $dirs = [
        __DIR__ . '/../app/controllers/',
        __DIR__ . '/../app/services/',
        __DIR__ . '/../app/repositories/',
        __DIR__ . '/../app/models/',
        __DIR__ . '/../app/middleware/',
        __DIR__ . '/../app/config/',
        __DIR__ . '/../app/events/',
        __DIR__ . '/../app/events/listeners/',
        __DIR__ . '/../app/strategies/',
    ];
    foreach ($dirs as $dir) {
        if (file_exists($dir . $class . '.php')) {
            require_once $dir . $class . '.php';
            return;
        }
    }
});

// Mock login for demo purposes (if no user is logged in)
if (!isset($_SESSION['user_id'])) {
    // Let's pretend Admin is logged in for now, to make testing easy
    $_SESSION['user_id'] = 1; 
    $_SESSION['role'] = 'admin';
}

// Basic router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/student_ambassador_club/public', '', $uri); // Adjust base path as needed
if ($uri == '/index.php') $uri = '/';

// Default Route
if ($uri == '/' || $uri == '') {
    header("Location: ?action=list_clubs");
    exit;
}

$action = $_GET['action'] ?? 'list_clubs';

// Simple routing based on action query param
try {
    switch ($action) {
        case 'list_clubs':
            $controller = new ClubController();
            $controller->index();
            break;
        case 'create_club':
            AuthMiddleware::require('admin', 'department_staff');
            $controller = new ClubController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->store();
            } else {
                $controller->create();
            }
            break;
        case 'show_club':
            $controller = new ClubController();
            $controller->show($_GET['id'] ?? 0);
            break;
        case 'edit_club':
            AuthMiddleware::require('admin', 'department_staff');
            $controller = new ClubController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->update($_GET['id'] ?? 0);
            } else {
                $controller->edit($_GET['id'] ?? 0);
            }
            break;
        case 'deactivate_club':
            AuthMiddleware::require('admin', 'department_staff');
            $controller = new ClubController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->deactivate($_POST['id'] ?? 0);
            }
            break;
        case 'add_member':
            AuthMiddleware::require('admin', 'department_staff', 'club_leader');
            $controller = new ClubController();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->addMember($_POST['club_id'] ?? 0);
            }
            break;
        default:
            echo "404 Not Found";
            break;
    }
} catch (Exception $e) {
    // Global error handler
    echo "An error occurred: " . htmlspecialchars($e->getMessage());
}
