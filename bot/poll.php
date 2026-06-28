<?php

if (PHP_SAPI !== 'cli') {
    die('Script ini hanya bisa dijalankan via CLI.');
}

$errorLog = __DIR__ . '/bot_error.log';

register_shutdown_function(function() use ($errorLog) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "FATAL: {$error['message']} in {$error['file']}:{$error['line']}";
        @file_put_contents($errorLog, $msg . "\n", FILE_APPEND);
    }
});

set_error_handler(function($severity, $message, $file, $line) use ($errorLog) {
    if (!(error_reporting() & $severity)) return false;
    if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_WARNING, E_NOTICE])) {
        $msg = "WARN: $message in $file:$line";
        @file_put_contents($errorLog, $msg . "\n", FILE_APPEND);
        return true;
    }
    return false;
});

try {

$missing = [];
if (!extension_loaded('curl')) $missing[] = 'curl';
if (!extension_loaded('sqlite3')) $missing[] = 'sqlite3';
if (!empty($missing)) {
    $msg = "ERROR: Extension PHP tidak terinstall: " . implode(', ', $missing) . "\n";
    $msg .= "Install: pkg install php-" . implode(' php-', $missing) . "\n";
    fwrite(STDERR, $msg);
    @file_put_contents($errorLog, $msg);
    exit(1);
}

session_save_path(sys_get_temp_dir());

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/commands.php';

$dbDir = dirname(DB_FILE);
if (!is_dir($dbDir)) {
    @mkdir($dbDir, 0755, true);
}

initDatabase();

$bot = new TelegramBot(BOT_TOKEN);
$offsetFile = __DIR__ . '/offset.dat';

$me = $bot->call('getMe');
if ($me === null) {
    $msg = "Gagal connect ke Telegram API. Cek token dan koneksi internet.\n";
    fwrite(STDERR, $msg);
    @file_put_contents($errorLog, $msg, FILE_APPEND);
    exit(1);
}
fwrite(STDOUT, "Bot @" . ($me['username'] ?? 'unknown') . " connected\n");

if (file_exists($offsetFile)) {
    $offset = (int)trim(file_get_contents($offsetFile));
    $bot->setLastUpdateId($offset);
    fwrite(STDOUT, "Resume from offset: $offset\n");
}

appLog('Bot polling started', 'INFO');
fwrite(STDOUT, "Bot polling started. Press Ctrl+C to stop.\n");

$pollCount = 0;
while (true) {
    try {
        $updates = $bot->getUpdates(BOT_POLL_TIMEOUT);

        if ($updates === null) {
            $pollCount++;
            if ($pollCount % 6 === 0) fwrite(STDOUT, ".");
            sleep(BOT_SLEEP_INTERVAL);
            continue;
        }

        $pollCount = 0;
        $count = count($updates);
        if ($count > 0) {
            fwrite(STDOUT, "\nReceived $count update(s)\n");
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

            fwrite(STDOUT, "Got msg: \"$text\" from $userId\n");

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
        $msg = "Loop error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        fwrite(STDERR, "\n$msg\n");
        @file_put_contents($errorLog, $msg . "\n", FILE_APPEND);
        appLog("Bot error: " . $e->getMessage(), 'ERROR');
        sleep(BOT_SLEEP_INTERVAL);
    }
}

} catch (Throwable $e) {
    $msg = "STARTUP ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    @file_put_contents($errorLog, $msg . "\n", FILE_APPEND);
    fwrite(STDERR, $msg . "\n");
    exit(1);
}
