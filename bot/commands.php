<?php

function isAuthorized(int $userId): bool {
    if (empty(ALLOWED_USERS)) return true;
    $users = array_map('trim', explode(',', ALLOWED_USERS));
    return in_array((string)$userId, $users, true);
}

function handleStart(int $chatId, TelegramBot $bot): void {
    $text = "<b>AHPL Bot</b>\n\n"
          . "Selamat datang! Saya adalah bot untuk mengelola server AHPL (Android Hosting Panel Lite).\n\n"
          . "Gunakan /help untuk melihat daftar perintah.";
    $bot->sendMessage($chatId, $text);
}

function handleHelp(int $chatId, TelegramBot $bot): void {
    $text = "<b>Daftar Perintah</b>\n\n"
          . "<b>Status & Informasi:</b>\n"
          . "/status - Cek status semua service\n"
          . "/sites - Daftar website\n"
          . "/disk - Penggunaan disk\n"
          . "/ram - Penggunaan RAM\n"
          . "/info - Informasi server\n"
          . "/tunnel - URL Cloudflare Tunnel\n\n"
          . "<b>Service Management:</b>\n"
          . "/start_nginx - Start Nginx\n"
          . "/stop_nginx - Stop Nginx\n"
          . "/restart_nginx - Restart Nginx\n"
          . "/start_php - Start PHP-FPM\n"
          . "/stop_php - Stop PHP-FPM\n"
          . "/restart_php - Restart PHP-FPM\n"
          . "/start_mariadb - Start MariaDB\n"
          . "/stop_mariadb - Stop MariaDB\n"
          . "/restart_mariadb - Restart MariaDB\n"
          . "/start_tunnel - Start Cloudflare Tunnel\n"
          . "/stop_tunnel - Stop Cloudflare Tunnel";
    $bot->sendMessage($chatId, $text);
}

function handleStatus(int $chatId, TelegramBot $bot): void {
    $bot->sendChatAction($chatId);

    $services = [
        'nginx' => checkServiceStatus('nginx'),
        'php-fpm' => checkServiceStatus('php-fpm'),
        'mariadb' => checkServiceStatus('mariadb'),
        'cloudflared' => checkServiceStatus('cloudflared'),
    ];

    $text = "<b>Status Service</b>\n\n";
    foreach ($services as $name => $status) {
        $icon = $status === 'running' ? "\xF0\x9F\x9F\xA2" : "\xF0\x9F\x94\xB4";
        $label = $status === 'running' ? 'Running' : 'Stopped';
        $text .= "$icon <b>" . ucfirst($name) . "</b>: $label\n";
    }

    $info = getServerInfo();
    $text .= "\n<b>System</b>\n";
    $text .= "Disk: " . formatSize($info['disk_used']) . " / " . formatSize($info['disk_total']) . "\n";
    if ($info['mem_total'] > 0) {
        $text .= "RAM: " . formatSize($info['mem_used']) . " / " . formatSize($info['mem_total']);
    }

    $bot->sendMessage($chatId, $text);
}

function handleSites(int $chatId, TelegramBot $bot): void {
    $db = getDB();
    $res = $db->query("SELECT id, name, folder FROM websites ORDER BY name");
    $sites = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $sites[] = $row;
    }

    if (empty($sites)) {
        $bot->sendMessage($chatId, "Tidak ada website.");
        return;
    }

    $text = "<b>Daftar Website</b>\n\n";
    foreach ($sites as $site) {
        $st = websiteStatus($site['folder']);
        $icon = $st === 'online' ? "\xE2\x9C\x85" : "\xE2\x9A\xAA";
        $name = htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8');
        $folder = htmlspecialchars($site['folder'], ENT_QUOTES, 'UTF-8');
        $text .= "$icon <b>$name</b>\n";
        $text .= "   Folder: <code>$folder</code>\n";
    }

    $bot->sendMessage($chatId, $text);
}

function handleDisk(int $chatId, TelegramBot $bot): void {
    $info = getServerInfo();
    $usedPct = $info['disk_total'] > 0 ? round($info['disk_used'] / $info['disk_total'] * 100, 1) : 0;

    $barLen = 20;
    $filled = round($usedPct / 100 * $barLen);
    $bar = str_repeat("\xE2\x96\x88", $filled) . str_repeat("\xE2\x96\x91", $barLen - $filled);

    $text = "<b>Penggunaan Disk</b>\n\n"
          . "Total: <b>" . formatSize($info['disk_total']) . "</b>\n"
          . "Used:  <b>" . formatSize($info['disk_used']) . "</b>\n"
          . "Free:  <b>" . formatSize($info['disk_free']) . "</b>\n"
          . "Usage: <b>{$usedPct}%</b>\n"
          . "<code>{$bar}</code>";

    $bot->sendMessage($chatId, $text);
}

function handleRam(int $chatId, TelegramBot $bot): void {
    $info = getServerInfo();
    if ($info['mem_total'] == 0) {
        $bot->sendMessage($chatId, "Informasi RAM tidak tersedia.");
        return;
    }

    $usedPct = round($info['mem_used'] / $info['mem_total'] * 100, 1);
    $barLen = 20;
    $filled = round($usedPct / 100 * $barLen);
    $bar = str_repeat("\xE2\x96\x88", $filled) . str_repeat("\xE2\x96\x91", $barLen - $filled);

    $text = "<b>Penggunaan RAM</b>\n\n"
          . "Total: <b>" . formatSize($info['mem_total']) . "</b>\n"
          . "Used:  <b>" . formatSize($info['mem_used']) . "</b>\n"
          . "Free:  <b>" . formatSize($info['mem_free']) . "</b>\n"
          . "Usage: <b>{$usedPct}%</b>\n"
          . "<code>{$bar}</code>";

    $bot->sendMessage($chatId, $text);
}

function handleInfo(int $chatId, TelegramBot $bot): void {
    $text = "<b>Informasi Server</b>\n\n"
          . "PHP Version: <code>" . phpversion() . "</code>\n";

    if (PHP_OS_FAMILY !== 'Windows') {
        $text .= "OS: <code>" . php_uname('s') . ' ' . php_uname('r') . "</code>\n";
        $load = @sys_getloadavg();
        if ($load) {
            $text .= "Load: <code>" . implode(', ', array_map(function($v) { return round($v, 2); }, $load)) . "</code>\n";
        }
    }

    $text .= "Platform: <code>" . PHP_OS_FAMILY . "</code>\n";
    $text .= "SAPI: <code>" . php_sapi_name() . "</code>\n";
    $text .= "APP: <b>" . APP_NAME . "</b> v" . APP_VERSION;

    $bot->sendMessage($chatId, $text);
}

function handleTunnel(int $chatId, TelegramBot $bot): void {
    $url = getTunnelUrl();
    if ($url) {
        $bot->sendMessage($chatId, "Cloudflare Tunnel\n\nURL: <code>$url</code>");
    } else {
        $bot->sendMessage($chatId, "Cloudflare Tunnel tidak aktif. Gunakan /start_tunnel untuk memulai.");
    }
}

function runServiceBg(string $service, string $action): void {
    $runner = __DIR__ . '/runner.php';
    $phpBin = PHP_BINARY;
    @shell_exec("$phpBin $runner $service $action > /dev/null 2>&1 &");
}

function handleServiceCommand(int $chatId, TelegramBot $bot, string $service, string $action): void {
    $serviceNames = [
        'nginx' => 'Nginx',
        'php-fpm' => 'PHP-FPM',
        'mariadb' => 'MariaDB',
        'cloudflared' => 'Cloudflare Tunnel',
    ];

    $serviceLabel = $serviceNames[$service] ?? $service;
    $actionLabel = $action === 'start' ? 'Memulai' : ($action === 'stop' ? 'Menghentikan' : 'Merestart');

    $bot->sendMessage($chatId, "$actionLabel $serviceLabel...");

    runServiceBg($service, $action === 'restart' ? 'restart' : $action);

    $expected = ($action === 'start' || $action === 'restart') ? 'running' : 'stopped';
    $status = '';
    for ($i = 0; $i < 15; $i++) {
        $status = checkServiceStatus($service);
        if ($status === $expected) break;
        sleep(1);
    }

    $icon = $status === 'running' ? "\xE2\x9C\x85" : ($status === 'stopped' ? "\xE2\xAD\x90" : "\xE2\x9D\x8C");
    $label = ucfirst($status);
    $bot->sendMessage($chatId, "$icon <b>$serviceLabel</b> $action.\nStatus: <b>$label</b>");
    logAction("bot_{$action}", "$service $action via Telegram bot");
}

function handleCommand(string $cmd, int $chatId, TelegramBot $bot): void {
    if (!isAuthorized($chatId)) {
        $bot->sendMessage($chatId, "Anda tidak diizinkan menggunakan bot ini.");
        return;
    }

    switch ($cmd) {
        case '/start':
            handleStart($chatId, $bot);
            break;
        case '/help':
            handleHelp($chatId, $bot);
            break;
        case '/status':
            handleStatus($chatId, $bot);
            break;
        case '/sites':
            handleSites($chatId, $bot);
            break;
        case '/disk':
            handleDisk($chatId, $bot);
            break;
        case '/ram':
            handleRam($chatId, $bot);
            break;
        case '/info':
            handleInfo($chatId, $bot);
            break;
        case '/tunnel':
            handleTunnel($chatId, $bot);
            break;
        case '/start_nginx':
            handleServiceCommand($chatId, $bot, 'nginx', 'start');
            break;
        case '/stop_nginx':
            handleServiceCommand($chatId, $bot, 'nginx', 'stop');
            break;
        case '/restart_nginx':
            handleServiceCommand($chatId, $bot, 'nginx', 'restart');
            break;
        case '/start_php':
            handleServiceCommand($chatId, $bot, 'php-fpm', 'start');
            break;
        case '/stop_php':
            handleServiceCommand($chatId, $bot, 'php-fpm', 'stop');
            break;
        case '/restart_php':
            handleServiceCommand($chatId, $bot, 'php-fpm', 'restart');
            break;
        case '/start_mariadb':
        case '/start_mysql':
            handleServiceCommand($chatId, $bot, 'mariadb', 'start');
            break;
        case '/stop_mariadb':
        case '/stop_mysql':
            handleServiceCommand($chatId, $bot, 'mariadb', 'stop');
            break;
        case '/restart_mariadb':
        case '/restart_mysql':
            handleServiceCommand($chatId, $bot, 'mariadb', 'restart');
            break;
        case '/start_tunnel':
        case '/start_cloudflared':
            handleServiceCommand($chatId, $bot, 'cloudflared', 'start');
            break;
        case '/stop_tunnel':
        case '/stop_cloudflared':
            handleServiceCommand($chatId, $bot, 'cloudflared', 'stop');
            break;
        default:
            $bot->sendMessage($chatId, "Perintah tidak dikenal. Gunakan /help.");
    }
}
