<?php
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->exec('PRAGMA journal_mode = WAL');
    }
    return $db;
}

function initDatabase() {
    $db = getDB();
    $dbVersion = (int)$db->querySingle("PRAGMA user_version");

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

    $db->exec("CREATE TABLE IF NOT EXISTS tunnel_domains (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: add password_changed column if missing (v1 -> v1.1)
    if ($dbVersion < 1) {
        $cols = getTableColumns($db, 'users');
        if (!in_array('password_changed', $cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_changed INTEGER DEFAULT 0");
        }
        $db->exec("PRAGMA user_version = 1");
    }

    // Migration: add ip_address column to logs (v1.1 -> v1.2)
    if ($dbVersion < 2) {
        $cols = getTableColumns($db, 'logs');
        if (!in_array('ip_address', $cols)) {
            $db->exec("ALTER TABLE logs ADD COLUMN ip_address TEXT DEFAULT ''");
        }
        $db->exec("PRAGMA user_version = 2");
    }

    // Seed default admin
    $count = $db->querySingle("SELECT COUNT(*) FROM users");
    if ($count == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, password_changed) VALUES (:u, :p, 0)");
        $stmt->bindValue(':u', 'admin', SQLITE3_TEXT);
        $stmt->bindValue(':p', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function getTableColumns($db, $table) {
    $cols = [];
    $res = $db->query("PRAGMA table_info($table)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $cols[] = $row['name'];
    }
    return $cols;
}

function logAction($action, $details = '', $ipAddress = '') {
    $db = getDB();
    if (empty($ipAddress)) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    $stmt = $db->prepare("INSERT INTO logs (action, details, ip_address) VALUES (:action, :details, :ip)");
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':details', $details, SQLITE3_TEXT);
    $stmt->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
    $stmt->execute();
}
