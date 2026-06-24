<?php
define('APP_NAME', 'AHPL');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// Load override path untuk Termux dulu (sebelum define default)
$localConfig = __DIR__ . '/local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// Default paths (bisa di-override oleh local.php)
if (!defined('SERVER_PATH')) define('SERVER_PATH', BASE_PATH . '/storage/server');
if (!defined('PANEL_PATH')) define('PANEL_PATH', SERVER_PATH . '/panel');
if (!defined('WEBSITES_PATH')) define('WEBSITES_PATH', SERVER_PATH . '/websites');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', SERVER_PATH . '/uploads');
if (!defined('BACKUPS_PATH')) define('BACKUPS_PATH', SERVER_PATH . '/backups');
if (!defined('DATABASE_PATH')) define('DATABASE_PATH', SERVER_PATH . '/database');
if (!defined('LOGS_PATH')) define('LOGS_PATH', SERVER_PATH . '/logs');
if (!defined('DB_FILE')) define('DB_FILE', DATABASE_PATH . '/ahpl.db');

define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024);

session_start();

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/helpers.php';
