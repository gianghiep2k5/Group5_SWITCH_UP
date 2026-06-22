<?php
/**
 * Optional client for Python AI service file extraction.
 * Used by Admin -> Learning Content to import lesson text from PDF files.
 */

function file_extract_log(string $message): void {
    $file = __DIR__ . '/../storage/ai_service.log';
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function extract_text_via_python_service(string $absolutePath): ?string {
    if (!is_file($absolutePath)) return null;
    if (!function_exists('curl_init') || !class_exists('CURLFile')) {
        file_extract_log('File extraction skipped: PHP cURL/CURLFile is not available.');
        return null;
    }

    $baseUrl = defined('AI_SERVICE_BASE_URL') ? rtrim((string)AI_SERVICE_BASE_URL, '/') : 'http://127.0.0.1:8010';
    $url = $baseUrl . '/extract';

    $ch = curl_init($url);
    $post = ['file' => new CURLFile($absolutePath)];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        file_extract_log('File extraction curl failed: ' . $curlError);
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        file_extract_log('File extraction HTTP ' . $httpCode . ': ' . mb_substr($raw, 0, 600, 'UTF-8'));
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        file_extract_log('File extraction invalid JSON: ' . mb_substr($raw, 0, 500, 'UTF-8'));
        return null;
    }
    $text = $data['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        file_extract_log('File extraction returned empty text: ' . mb_substr($raw, 0, 500, 'UTF-8'));
        return null;
    }
    return trim($text);
}
