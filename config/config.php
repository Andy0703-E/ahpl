<?php
define('APP_NAME', 'AHPL');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// Default paths (bisa di-override oleh local.php untuk Termux)
define('SERVER_PATH', BASE_PATH . '/storage/server');
define('PANEL_PATH', SERVER_PATH . '/panel');
define('WEBSITES_PATH', SERVER_PATH . '/websites');
define('UPLOADS_PATH', SERVER_PATH . '/uploads');
define('BACKUPS_PATH', SERVER_PATH . '/backups');
define('DATABASE_PATH', SERVER_PATH . '/database');
define('LOGS_PATH', SERVER_PATH . '/logs');
define('DB_FILE', DATABASE_PATH . '/ahpl.db');

define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024);

session_start();

// Override path untuk Termux jika ada
if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/helpers.php';
