<?php
/**
 * PHP client for Python AI microservice.
 * Keeps the Web2 application in PHP/MySQL while delegating only LLM generation to Python.
 */

function ai_client_log(string $message): void {
    $file = __DIR__ . '/../storage/ai_service.log';
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/**
 * @param string $question
 * @param array<int,string> $lessonContexts
 * @param array<int,array{role:string,content:string}> $history
 * @return string|null
 */
function call_python_ai_service(string $question, array $lessonContexts = [], array $history = []): ?string {
    $provider = defined('AI_PROVIDER') ? strtolower(trim((string)AI_PROVIDER)) : 'local';
    if ($provider !== 'python') {
        ai_client_log('AI service skipped: AI_PROVIDER=' . $provider);
        return null;
    }
    if (!function_exists('curl_init')) {
        ai_client_log('AI service skipped: PHP cURL is not enabled. Enable extension=curl in php.ini.');
        return null;
    }

    $url = defined('AI_SERVICE_URL') ? (string)AI_SERVICE_URL : 'http://127.0.0.1:8010/ask';
    $payload = [
        'question' => $question,
        'lesson_contexts' => array_values($lessonContexts),
        'history' => array_values($history),
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => defined('AI_TIMEOUT') ? (int)AI_TIMEOUT : 35,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        ai_client_log('AI service curl failed: ' . $curlError);
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        ai_client_log('AI service HTTP ' . $httpCode . ': ' . mb_substr($raw, 0, 600, 'UTF-8'));
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        ai_client_log('AI service invalid JSON: ' . mb_substr($raw, 0, 500, 'UTF-8'));
        return null;
    }
    $answer = $data['answer'] ?? null;
    if (!is_string($answer) || trim($answer) === '') {
        ai_client_log('AI service returned empty answer. Raw: ' . mb_substr($raw, 0, 500, 'UTF-8'));
        return null;
    }
    return trim($answer);
}
