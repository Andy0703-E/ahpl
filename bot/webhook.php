<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/commands.php';

initDatabase();

$bot = new TelegramBot(BOT_TOKEN);

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    http_response_code(200);
    exit;
}

$chatId = $message['chat']['id'] ?? 0;
$text = trim($message['text'] ?? '');
$userId = $message['from']['id'] ?? 0;

if (!empty($text) && strpos($text, '/') === 0) {
    $parts = explode(' ', $text, 2);
    $command = strtolower($parts[0]);

    appLog("Bot webhook command: $command from user $userId", 'INFO');
    handleCommand($command, $chatId, $bot);
}

http_response_code(200);
