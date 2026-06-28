<?php
$sessPath = __DIR__ . '/sessions';
if (!is_dir($sessPath)) @mkdir($sessPath, 0755, true);
session_save_path($sessPath);
session_name('POS_SESSION');
session_set_cookie_params(['path' => '/pos', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', 'ahpl123');
define('DB_NAME', 'db_pos');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function escape($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function flash($key = null, $val = null) {
    if ($val !== null) {
        $_SESSION['flash'][$key] = $val;
        return;
    }
    if ($key !== null && isset($_SESSION['flash'][$key])) {
        $v = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    return null;
}

function money($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function generateInvoice($prefix = 'INV') {
    return $prefix . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function setActive($page, $val) {
    return $page === $val ? 'active' : '';
}
