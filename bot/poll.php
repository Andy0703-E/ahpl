<?php

if (PHP_SAPI !== 'cli') {
    die('Script ini hanya bisa dijalankan via CLI.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/commands.php';

initDatabase();

$bot = new TelegramBot(BOT_TOKEN);
$offsetFile = __DIR__ . '/offset.dat';

if (file_exists($offsetFile)) {
    $offset = (int)trim(file_get_contents($offsetFile));
    $bot->setLastUpdateId($offset);
}

appLog('Bot polling started', 'INFO');
fwrite(STDOUT, "Bot polling started. Press Ctrl+C to stop.\n");

while (true) {
    try {
        $updates = $bot->getUpdates(BOT_POLL_TIMEOUT);

        if ($updates === null) {
            sleep(BOT_SLEEP_INTERVAL);
            continue;
        }

        foreach ($updates as $update) {
            $updateId = $update['update_id'] ?? 0;
            $bot->setLastUpdateId($updateId);

            $message = $update['message'] ?? null;
            if (!$message) continue;

            $chatId = $message['chat']['id'] ?? 0;
            $text = trim($message['text'] ?? '');
            $userId = $message['from']['id'] ?? 0;

            if (empty($text)) continue;
            if (strpos($text, '/') !== 0) continue;

            $parts = explode(' ', $text, 2);
            $command = strtolower($parts[0]);

            appLog("Bot command: $command from user $userId", 'INFO');
            handleCommand($command, $chatId, $bot);
        }

        $currentOffset = $bot->getLastUpdateId();
        if ($currentOffset > 0) {
            file_put_contents($offsetFile, $currentOffset);
        }

    } catch (Throwable $e) {
        appLog("Bot error: " . $e->getMessage(), 'ERROR');
        sleep(BOT_SLEEP_INTERVAL);
    }
}
