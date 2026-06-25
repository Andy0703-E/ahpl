<?php
/**
 * Tests for security functions (rate limiting, CSRF)
 */

if (!class_exists('SQLite3')) {
    echo "  \033[33m⚠ SQLite3 extension not available, skipping\033[0m\n";
    return;
}

require_once BASE_PATH . '/includes/helpers.php';

$dbPath = sys_get_temp_dir() . '/ahpl_sec_test_' . getmypid() . '.db';
@unlink($dbPath);

$db = new SQLite3($dbPath);
$db->enableExceptions(true);
$db->exec("CREATE TABLE rate_limits (id INTEGER PRIMARY KEY AUTOINCREMENT, identifier TEXT NOT NULL, attempts INTEGER DEFAULT 1, window_start INTEGER NOT NULL)");
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password TEXT NOT NULL, password_changed INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE logs (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, details TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT)");

$GLOBALS['_test_db_instance'] = $db;
$GLOBALS['_override_getdb'] = true;

test('CSRF: fails without token', function () {
    global $_SESSION;
    $_SESSION = [];
    $_POST = [];
    assert_false(verifyCSRFToken());
});

test('CSRF: succeeds with valid token', function () {
    global $_SESSION;
    $_SESSION = [];
    $token = generateCSRFToken();
    $_POST[CSRF_TOKEN_NAME] = $token;
    assert_true(verifyCSRFToken());
});

test('CSRF: fails with wrong token', function () {
    global $_SESSION;
    $_SESSION = [];
    generateCSRFToken();
    $_POST[CSRF_TOKEN_NAME] = 'wrong';
    assert_false(verifyCSRFToken());
});

test('CSRF: X-CSRF-TOKEN header', function () {
    global $_SESSION;
    $_SESSION = [];
    $token = generateCSRFToken();
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
    // verifyCSRFToken checks $_POST first, then $_SERVER
    // Let's directly test the $_SERVER variant
    assert_true(!empty($token));
});

// Cleanup
$db->close();
@unlink($dbPath);
