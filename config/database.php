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
    if (file_exists(DB_FILE)) return;
    
    $db = getDB();
    
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
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
    
    $count = $db->querySingle("SELECT COUNT(*) FROM users");
    if ($count == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password) VALUES ('admin', '$hash')");
    }
}

function logAction($action, $details = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO logs (action, details) VALUES (:action, :details)");
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':details', $details, SQLITE3_TEXT);
    $stmt->execute();
}
