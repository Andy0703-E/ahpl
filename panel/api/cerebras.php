<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();

ob_clean();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$db = getDB();
$apiKey = $db->querySingle("SELECT value FROM settings WHERE key = 'cerebras_key'");
$db->close();

if (!$apiKey) jsonResponse(['error' => 'API Key Cerebras belum diatur. Masukkan di Settings > API Key'], 400);

$input = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');

if (empty($prompt)) jsonResponse(['error' => 'Deskripsi wajib diisi'], 400);

$systemPrompt = 'Kamu adalah web developer. Buat satu halaman website HTML lengkap dengan CSS internal (style tag di head) berdasarkan deskripsi berikut. Hasil harus HTML lengkap (bukan markdown, bukan partial). Gunakan desain modern, responsive, dan menarik. Jangan gunakan framework eksternal. Semua CSS inline di style tag. Hanya kirimkan kode HTML, tanpa komentar tambahan.';

$payload = json_encode([
    'model' => 'zai-glm-4.7',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 8192,
    'temperature' => 0.7,
]);

$ch = curl_init('https://api.cerebras.ai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_TIMEOUT => 120,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) jsonResponse(['error' => 'Curl error: ' . $error], 500);
if ($httpCode !== 200) {
    jsonResponse(['error' => 'API HTTP ' . $httpCode . ': ' . substr($response, 0, 500)], 500);
}

$data = json_decode($response, true);
if (!$data || !isset($data['choices'][0]['message']['content'])) {
    jsonResponse(['error' => 'AI response invalid: ' . substr($response, 0, 500)], 500);
}

$html = $data['choices'][0]['message']['content'];
$html = preg_replace('/^```html?\s*/i', '', $html);
$html = preg_replace('/```\s*$/', '', $html);
$html = trim($html);

jsonResponse(['success' => true, 'html' => $html]);
