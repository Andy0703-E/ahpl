<?php

if (PHP_SAPI !== 'cli' || $argc < 3) {
    exit(1);
}

session_save_path(sys_get_temp_dir());

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

initDatabase();

$service = $argv[1];
$action = $argv[2];

try {
    switch ($action) {
        case 'start':
            startService($service);
            break;
        case 'stop':
            stopService($service);
            break;
        case 'restart':
            stopService($service);
            sleep(1);
            startService($service);
            break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($service === 'mariadb') {
    $sock = '/data/data/com.termux/files/usr/var/run/mysqld.sock';
    $out = @shell_exec("pidof mariadbd mysqld 2>/dev/null");
    if (empty(trim($out ?? ''))) {
        @unlink($sock);
        @unlink($sock . '.lock');
        @unlink('/data/data/com.termux/files/usr/var/run/mysqld.pid');
    }
}
