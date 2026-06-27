<?php

if (PHP_SAPI !== 'cli') {
    die('Script ini hanya bisa dijalankan via CLI.');
}

require_once __DIR__ . '/config.php';

$webhookUrl = $argv[1] ?? '';

if (empty($webhookUrl)) {
    echo "Usage: php bot/setup.php <webhook_url>\n";
    echo "Example: php bot/setup.php https://example.com/bot/webhook.php\n";
    exit(1);
}

$url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook?url=" . urlencode($webhookUrl);
$response = @file_get_contents($url);
$result = json_decode($response, true);

if (($result['ok'] ?? false)) {
    echo "Webhook berhasil diatur ke: $webhookUrl\n";
} else {
    echo "Gagal: " . ($result['description'] ?? 'unknown') . "\n";
}
