<?php
// Copy file này thành config/ai.local.php nếu bạn muốn đổi URL AI service.
// Không cần đặt OpenAI API key trong PHP. Key để trong ai_service/.env.

define('AI_PROVIDER', 'python'); // python | local
define('AI_SERVICE_URL', 'http://127.0.0.1:8010/ask');
define('AI_TIMEOUT', 35);
