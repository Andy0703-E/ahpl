<?php
/**
 * Tests for database functions
 */

if (!class_exists('SQLite3')) {
    echo "  \033[33m⚠ SQLite3 extension not available, skipping\033[0m\n";
    return;
}

$dbPath = sys_get_temp_dir() . '/ahpl_db_test_' . getmypid() . '.db';
@unlink($dbPath);
$GLOBALS['_test_db'] = $dbPath;

// Bootstrap: minimal database setup
$db = new SQLite3($dbPath);
$db->enableExceptions(true);
$db->exec('PRAGMA journal_mode = WAL');

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    password_changed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    folder TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
)");
$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier TEXT NOT NULL,
    attempts INTEGER DEFAULT 1,
    window_start INTEGER NOT NULL
)");

test('tables created', function () use ($db) {
    $tables = [];
    $res = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }
    assert_true(in_array('users', $tables));
    assert_true(in_array('websites', $tables));
    assert_true(in_array('settings', $tables));
    assert_true(in_array('logs', $tables));
    assert_true(in_array('rate_limits', $tables));
});

test('insert and query website', function () use ($db) {
    $stmt = $db->prepare("INSERT INTO websites (name, folder) VALUES (:n, :f)");
    $stmt->bindValue(':n', 'Test Site', SQLITE3_TEXT);
    $stmt->bindValue(':f', 'test-site', SQLITE3_TEXT);
    $stmt->execute();

    $result = $db->querySingle("SELECT name FROM websites WHERE folder = 'test-site'");
    assert_eq('Test Site', $result);
});

test('log action', function () use ($db) {
    $stmt = $db->prepare("INSERT INTO logs (action, details) VALUES (:a, :d)");
    $stmt->bindValue(':a', 'test_action', SQLITE3_TEXT);
    $stmt->bindValue(':d', 'test details', SQLITE3_TEXT);
    $stmt->execute();

    $count = $db->querySingle("SELECT COUNT(*) FROM logs WHERE action = 'test_action'");
    assert_eq(1, $count);
});

test('insert and retrieve setting', function () use ($db) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)");
    $stmt->bindValue(':k', 'test_key', SQLITE3_TEXT);
    $stmt->bindValue(':v', 'test_value', SQLITE3_TEXT);
    $stmt->execute();

    $result = $db->querySingle("SELECT value FROM settings WHERE key = 'test_key'");
    assert_eq('test_value', $result);
});

$db->close();
@unlink($dbPath);
