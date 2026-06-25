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

    // Migration: add password_changed column if missing (v1 -> v1.1)
    if ($dbVersion < 1) {
        $cols = [];
        $res = $db->query("PRAGMA table_info(users)");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $cols[] = $row['name'];
        }
        if (!in_array('password_changed', $cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN password_changed INTEGER DEFAULT 0");
        }
        $db->exec("PRAGMA user_version = 1");
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

function logAction($action, $details = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO logs (action, details) VALUES (:action, :details)");
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':details', $details, SQLITE3_TEXT);
    $stmt->execute();
}
