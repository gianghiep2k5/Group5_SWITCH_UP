<?php
// Cấu hình chung. Nếu folder trong htdocs của bạn khác, sửa BASE_URL cho đúng.
define('APP_NAME', 'International School_VNUIS');
define('APP_SUBTITLE', 'Student Service Hub');
define('BASE_URL', '/learning_management_full');
define('DEFAULT_PASSWORD', '123456');

date_default_timezone_set('Asia/Ho_Chi_Minh');

// -------------------------------------------------------------
// AI microservice configuration
// -------------------------------------------------------------
// PHP là web app chính. Python AI service chỉ dùng cho chatbot.
// Nếu AI service chưa chạy hoặc lỗi, hệ thống tự fallback về local retrieval.
$aiLocalFile = __DIR__ . '/ai.local.php';
if (file_exists($aiLocalFile)) {
    require_once $aiLocalFile;
}

if (!defined('AI_PROVIDER')) {
    define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'python'); // python | local
}
if (!defined('AI_SERVICE_URL')) {
    define('AI_SERVICE_URL', getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:8010/ask');
}
if (!defined('AI_TIMEOUT')) {
    define('AI_TIMEOUT', (int)(getenv('AI_TIMEOUT') ?: 35));
}
