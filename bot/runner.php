<?php

if (PHP_SAPI !== 'cli' || $argc < 3) {
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

initDatabase();

$service = $argv[1];
$action = $argv[2];

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
