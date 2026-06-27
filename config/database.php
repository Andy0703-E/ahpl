<?php

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_FILE);
        $db->enableExceptions(true);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA busy_timeout = 5000');
    }
    return $db;
}

function getMariaDB() {
    return getMariaDBWithDB(MARIADB_NAME);
}

function getMariaDBNoDB() {
    $dsn = "mysql:host=" . MARIADB_HOST . ";port=" . MARIADB_PORT . ";charset=utf8mb4";
    return new PDO($dsn, MARIADB_USER, MARIADB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function getMariaDBWithDB($dbName) {
    $dsn = "mysql:host=" . MARIADB_HOST . ";port=" . MARIADB_PORT . ";dbname=" . $dbName . ";charset=utf8mb4";
    try {
        return new PDO($dsn, MARIADB_USER, MARIADB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 1049 || $e->getCode() == 1044) {
            $pdo = getMariaDBNoDB();
            $pdo->exec("USE `$dbName`");
            return $pdo;
        }
        throw $e;
    }
}

function getDBConnection($type) {
    if ($type === 'mariadb') {
        return getMariaDB();
    }
    return getDB();
}

function dbListTables($type) {
    if ($type === 'mariadb') {
        $pdo = getMariaDB();
        $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = " . $pdo->quote(MARIADB_NAME) . " ORDER BY TABLE_NAME");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['TABLE_NAME'];
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$name`");
            $count = (int)$countStmt->fetchColumn();
            $tables[] = ['name' => $name, 'row_count' => $count];
        }
        return $tables;
    }

    $db = getDB();
    $tables = [];
    $res = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $count = $db->querySingle("SELECT COUNT(*) FROM \"" . $row['name'] . "\"");
        $tables[] = ['name' => $row['name'], 'row_count' => (int)$count];
    }
    return $tables;
}

function dbGetSchema($type, $table) {
    if ($type === 'mariadb') {
        $pdo = getMariaDB();
        $stmt = $pdo->prepare("SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE, COLUMN_DEFAULT AS dflt_value, COLUMN_KEY, ORDINAL_POSITION AS cid
            FROM information_schema.columns WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION");
        $stmt->execute([':db' => MARIADB_NAME, ':table' => $table]);
        $schema = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schema[] = [
                'cid' => (int)$row['cid'],
                'name' => $row['name'],
                'type' => $row['type'],
                'notnull' => $row['IS_NULLABLE'] === 'NO' ? 1 : 0,
                'dflt_value' => $row['dflt_value'],
                'pk' => $row['COLUMN_KEY'] === 'PRI' ? 1 : 0,
            ];
        }
        return $schema;
    }

    $db = getDB();
    $schema = [];
    $res = $db->query("PRAGMA table_info(\"$table\")");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $schema[] = [
            'cid' => (int)$row['cid'],
            'name' => $row['name'],
            'type' => $row['type'],
            'notnull' => (int)$row['notnull'],
            'dflt_value' => $row['dflt_value'],
            'pk' => (int)$row['pk'],
        ];
    }
    return $schema;
}

function dbGetColumns($type, $table) {
    $schema = dbGetSchema($type, $table);
    return array_column($schema, 'name');
}

function dbGetRowCount($type, $table) {
    if ($type === 'mariadb') {
        $pdo = getMariaDB();
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        return (int)$stmt->fetchColumn();
    }
    $db = getDB();
    return (int)$db->querySingle("SELECT COUNT(*) FROM \"$table\"");
}

function dbPrepareExecute($type, $sql, $params = [], $paramTypes = []) {
    if ($type === 'mariadb') {
        $pdo = getMariaDB();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    $db = getDB();
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $typeConst = SQLITE3_TEXT;
        if (isset($paramTypes[$key])) {
            $typeConst = $paramTypes[$key];
        } elseif (is_int($val)) {
            $typeConst = SQLITE3_INTEGER;
        } elseif (is_float($val)) {
            $typeConst = SQLITE3_FLOAT;
        } elseif (is_null($val)) {
            $typeConst = SQLITE3_NULL;
        }
        $stmt->bindValue($key, $val, $typeConst);
    }
    $stmt->execute();
    return $stmt;
}

function dbQuery($type, $sql) {
    if ($type === 'mariadb') {
        return getMariaDB()->query($sql);
    }
    return getDB()->query($sql);
}

function dbChanges($type) {
    if ($type === 'mariadb') {
        return 0; // PDO rowCount not reliable for all statements
    }
    return getDB()->changes();
}

function dbQuote($type, $name) {
    if ($type === 'mariadb') {
        return "`$name`";
    }
    return "\"$name\"";
}

function dbLastInsertId($type) {
    if ($type === 'mariadb') {
        return (int)getMariaDB()->lastInsertId();
    }
    return getDB()->lastInsertRowID();
}

function dbFetchAll($type, $stmt) {
    if ($type === 'mariadb') {
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }
    $rows = [];
    while ($row = $stmt->fetchArray(SQLITE3_NUM)) {
        $rows[] = $row;
    }
    return $rows;
}

function dbColumnCount($type, $stmt) {
    if ($type === 'mariadb') {
        return $stmt->columnCount();
    }
    return $stmt->numColumns();
}

function dbColumnName($type, $stmt, $i) {
    if ($type === 'mariadb') {
        $meta = $stmt->getColumnMeta($i);
        return $meta['name'];
    }
    return $stmt->columnName($i);
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

    $db->exec("CREATE TABLE IF NOT EXISTS visitor_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT,
        user_agent TEXT,
        page TEXT,
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
