<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$dbType = $_GET['db_type'] ?? ($_POST['db_type'] ?? 'sqlite');
$dbType = in_array($dbType, ['sqlite', 'mariadb']) ? $dbType : 'sqlite';
$dbName = $_GET['db_name'] ?? ($_POST['db_name'] ?? MARIADB_NAME);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list_databases') {
        try {
            $pdo = getMariaDBWithDB('information_schema');
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM SCHEMATA WHERE SCHEMA_NAME NOT IN ('information_schema','performance_schema','mysql','sys') ORDER BY SCHEMA_NAME");
            $dbs = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dbs[] = $r['SCHEMA_NAME'];
            }
            jsonResponse(['success' => true, 'databases' => $dbs, 'current' => $dbName]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    if ($action === 'list_tables') {
        try {
            if ($dbType === 'mariadb') {
                $pdo = getMariaDBWithDB($dbName);
                $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " ORDER BY TABLE_NAME");
                $tables = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $name = $row['TABLE_NAME'];
                    $cnt = $pdo->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
                    $tables[] = ['name' => $name, 'row_count' => (int)$cnt];
                }
            } else {
                $tables = dbListTables('sqlite');
            }
            jsonResponse(['success' => true, 'tables' => $tables]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal terhubung ke MariaDB: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'get_table') {
        $table = $_GET['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            jsonResponse(['error' => 'Invalid table name'], 400);
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        try {
            if ($dbType === 'mariadb') {
                $pdo = getMariaDBWithDB($dbName);
                $q = '`' . str_replace('`', '``', $table) . '`';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $q");
                $stmt->execute();
                $total = (int)$stmt->fetchColumn();
                $stmt = $pdo->prepare("SHOW COLUMNS FROM $q");
                $stmt->execute();
                $schema = [];
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $schema[] = [
                        'cid' => count($schema),
                        'name' => $r['Field'],
                        'type' => $r['Type'],
                        'notnull' => $r['Null'] === 'NO' ? 1 : 0,
                        'dflt_value' => $r['Default'],
                        'pk' => $r['Key'] === 'PRI' ? 1 : 0,
                    ];
                }
                $cols = array_column($schema, 'name');
                $stmt = $pdo->prepare("SELECT * FROM $q LIMIT :lim OFFSET :off");
                $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            } else {
                $total = dbGetRowCount('sqlite', $table);
                $schema = dbGetSchema('sqlite', $table);
                $cols = array_column($schema, 'name');
                $q = dbQuote('sqlite', $table);
                $rowRes = getDB()->query("SELECT * FROM $q LIMIT $perPage OFFSET $offset");
                $rows = [];
                while ($r = $rowRes->fetchArray(SQLITE3_NUM)) { $rows[] = $r; }
            }

            jsonResponse(['success' => true, 'columns' => $cols, 'schema' => $schema, 'rows' => $rows, 'total' => $total, 'page' => $page]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengambil data: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'get_schema') {
        $table = $_GET['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            jsonResponse(['error' => 'Invalid table name'], 400);
        }
        try {
            if ($dbType === 'mariadb') {
                $pdo = getMariaDBWithDB($dbName);
                $q = '`' . str_replace('`', '``', $table) . '`';
                $stmt = $pdo->prepare("SHOW COLUMNS FROM $q");
                $stmt->execute();
                $schema = [];
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $schema[] = [
                        'cid' => count($schema),
                        'name' => $r['Field'],
                        'type' => $r['Type'],
                        'notnull' => $r['Null'] === 'NO' ? 1 : 0,
                        'dflt_value' => $r['Default'],
                        'pk' => $r['Key'] === 'PRI' ? 1 : 0,
                    ];
                }
            } else {
                $schema = dbGetSchema('sqlite', $table);
            }
            jsonResponse(['success' => true, 'schema' => $schema]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengambil schema: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'db_info') {
        $info = ['type' => $dbType, 'name' => $dbName];
        if ($dbType === 'mariadb') {
            try {
                $pdo = getMariaDBWithDB($dbName);
                $stmt = $pdo->query("SELECT VERSION() AS ver");
                $info['version'] = $stmt->fetchColumn();
                $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA = " . $pdo->quote($dbName));
                $info['table_count'] = (int)$stmt->fetchColumn();
                $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024, 1) FROM information_schema.tables WHERE TABLE_SCHEMA = " . $pdo->quote($dbName));
                $info['size_kb'] = (float)$stmt->fetchColumn();
                $stmt = $pdo->query("SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Uptime'");
                $uptime = $stmt->fetchColumn();
                $info['uptime'] = $uptime ? (int)$uptime : 0;
            } catch (Exception $e) {
                $info['error'] = $e->getMessage();
            }
        } else {
            $db = getDB();
            $info['version'] = SQLite3::version()['versionString'];
            $res = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $info['table_count'] = (int)$res->fetchArray(SQLITE3_NUM)[0];
            $size = @filesize(dirname(__DIR__, 2) . '/storage/database/ahpl.db');
            $info['size_kb'] = $size ? round($size / 1024, 1) : 0;
            $info['uptime'] = 0;
        }
        jsonResponse(['success' => true, 'info' => $info]);
    }

    if ($action === 'export_csv') {
        $table = $_GET['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            jsonResponse(['error' => 'Invalid table name'], 400);
        }

        $cols = dbGetColumns($dbType, $table);
        $q = dbQuote($dbType, $table);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $table . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $cols);

        if ($dbType === 'mariadb') {
            $pdo = getMariaDBWithDB($dbName);
            $stmt = $pdo->query("SELECT * FROM `$table`");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row = [];
                foreach ($cols as $c) {
                    $row[] = $r[$c];
                }
                fputcsv($output, $row);
            }
        } else {
            $rowRes = getDB()->query("SELECT * FROM $q");
            while ($r = $rowRes->fetchArray(SQLITE3_ASSOC)) {
                $row = [];
                foreach ($cols as $c) {
                    $row[] = $r[$c];
                }
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

if ($method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $dbType = $input['db_type'] ?? 'sqlite';
    $dbType = in_array($dbType, ['sqlite', 'mariadb']) ? $dbType : 'sqlite';
    $action = $input['action'] ?? '';

    if ($action === 'create_database') {
        $name = $input['name'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) jsonResponse(['error' => 'Invalid database name'], 400);
        try {
            $pdo = getMariaDBWithDB('information_schema');
            $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            logAction('db_create_db', "CREATE DATABASE $name");
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    if ($action === 'drop_database') {
        $name = $input['name'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) jsonResponse(['error' => 'Invalid database name'], 400);
        try {
            $pdo = getMariaDBWithDB('information_schema');
            $pdo->exec("DROP DATABASE `$name`");
            logAction('db_drop_db', "DROP DATABASE $name");
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    if ($action === 'insert_row') {
        $table = $input['table'] ?? '';
        $data = $input['data'] ?? [];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (empty($data)) jsonResponse(['error' => 'Data kosong'], 400);

        if ($dbType === 'mariadb') {
            $pdo = getMariaDBWithDB($dbName);
            $cols = []; $vals = [];
            foreach ($data as $k => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
                $cols[] = "`$k`"; $vals[] = ":$k";
            }
            if (empty($cols)) jsonResponse(['error' => 'Data kosong'], 400);
            $stmt = $pdo->prepare("INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
            $params = []; foreach ($data as $k => $v) { if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) $params[":$k"] = $v; }
            $stmt->execute($params);
            $id = $pdo->lastInsertId();
        } else {
            $cols = []; $vals = []; $q = dbQuote($dbType, '');
            foreach ($data as $k => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
                $cols[] = $q . $k . $q; $vals[] = ":$k";
            }
            $tq = dbQuote($dbType, $table);
            $params = []; $paramTypes = [];
            foreach ($data as $k => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
                $params[":$k"] = $v;
                if (is_numeric($v)) $paramTypes[":$k"] = SQLITE3_INTEGER;
            }
            dbPrepareExecute($dbType, "INSERT INTO $tq (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")", $params, $paramTypes);
            $id = dbLastInsertId($dbType);
        }

        logAction('db_insert', "INSERT INTO $table");
        jsonResponse(['success' => true, 'id' => $id]);
    }

    if ($action === 'update_row') {
        $table = $input['table'] ?? '';
        $idColumn = $input['id_column'] ?? '';
        $idValue = $input['id_value'] ?? '';
        $data = $input['data'] ?? [];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $idColumn)) jsonResponse(['error' => 'Invalid id column'], 400);
        if (empty($data)) jsonResponse(['error' => 'Data kosong'], 400);

        if ($dbType === 'mariadb') {
            $pdo = getMariaDBWithDB($dbName);
            $sets = [];
            foreach ($data as $k => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
                $sets[] = "`$k` = :$k";
            }
            $idQ = "`$idColumn`";
            $stmt = $pdo->prepare("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $idQ = :__id__");
            $params = [':__id__' => $idValue];
            foreach ($data as $k => $v) { if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) $params[":$k"] = $v; }
            $stmt->execute($params);
        } else {
            $sets = []; $q = dbQuote($dbType, '');
            foreach ($data as $k => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
                $sets[] = $q . $k . $q . " = :$k";
            }
            $tq = dbQuote($dbType, $table);
            $idQ = $q . $idColumn . $q;
            $params = [':__id__' => $idValue];
            $paramTypes = [':__id__' => is_numeric($idValue) ? SQLITE3_INTEGER : SQLITE3_TEXT];
            foreach ($data as $k => $v) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
                $params[":$k"] = $v;
                $paramTypes[":$k"] = is_numeric($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            }
            dbPrepareExecute($dbType, "UPDATE $tq SET " . implode(', ', $sets) . " WHERE $idQ = :__id__", $params, $paramTypes);
        }

        logAction('db_update', "UPDATE $table WHERE $idColumn = $idValue");
        jsonResponse(['success' => true, 'affected' => 1]);
    }

    if ($action === 'delete_row') {
        $table = $input['table'] ?? '';
        $idColumn = $input['id_column'] ?? '';
        $idValue = $input['id_value'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $idColumn)) jsonResponse(['error' => 'Invalid id column'], 400);

        if ($dbType === 'mariadb') {
            $pdo = getMariaDBWithDB($dbName);
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$idColumn` = :id");
            $stmt->execute([':id' => $idValue]);
        } else {
            $tq = dbQuote($dbType, $table);
            $q = dbQuote($dbType, '');
            $idQ = $q . $idColumn . $q;
            dbPrepareExecute($dbType, "DELETE FROM $tq WHERE $idQ = :id", [':id' => $idValue], [':id' => is_numeric($idValue) ? SQLITE3_INTEGER : SQLITE3_TEXT]);
        }

        logAction('db_delete', "DELETE FROM $table WHERE $idColumn = $idValue");
        jsonResponse(['success' => true, 'affected' => 1]);
    }

    if ($action === 'query') {
        $rawQuery = trim($input['query'] ?? '');
        if (empty($rawQuery)) jsonResponse(['error' => 'Query wajib diisi'], 400);

        $firstWord = strtoupper(explode(' ', $rawQuery, 2)[0]);
        $readOps = ['SELECT', 'PRAGMA', 'EXPLAIN', 'SHOW', 'DESCRIBE'];
        $writeOps = ['INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'REINDEX', 'REPLACE', 'TRUNCATE'];
        $isRead = in_array($firstWord, $readOps);
        $isWrite = in_array($firstWord, $writeOps);

        if (!$isRead && !$isWrite) {
            jsonResponse(['error' => 'Query tidak dikenal'], 400);
        }

        if ($isWrite && empty($input['confirm_write'])) {
            jsonResponse(['error' => 'require_confirm: Konfirmasi diperlukan untuk query write', 'require_confirm' => true], 400);
        }

        // FLUSH PRIVILEGES dulu biar CREATE USER/GRANT bisa jalan walau --skip-grant-tables
        if ($isWrite && $dbType === 'mariadb') {
            try { getMariaDBWithDB('information_schema')->exec("FLUSH PRIVILEGES"); } catch (Exception $e) {}
        }

        $start = microtime(true);
        try {
            if ($isRead) {
                $elapsed = round(microtime(true) - $start, 4);
                $columns = [];
                $rows = [];

                if ($dbType === 'mariadb') {
                    $pdo = getMariaDBWithDB($dbName);
                    $stmt = $pdo->query($rawQuery);
                    $colCount = $stmt->columnCount();
                    if ($colCount > 0) {
                        for ($i = 0; $i < $colCount; $i++) {
                            $meta = $stmt->getColumnMeta($i);
                            $columns[] = $meta['name'];
                        }
                        $all = $stmt->fetchAll(PDO::FETCH_NUM);
                        $rows = count($all) > 1000 ? array_slice($all, 0, 1000) : $all;
                    }
                } else {
                    $result = dbQuery($dbType, $rawQuery);
                    $colCount = dbColumnCount($dbType, $result);
                    if ($colCount > 0) {
                        for ($i = 0; $i < $colCount; $i++) {
                            $columns[] = dbColumnName($dbType, $result, $i);
                        }
                        $all = dbFetchAll($dbType, $result);
                        $rows = count($all) > 1000 ? array_slice($all, 0, 1000) : $all;
                    }
                    if (method_exists($result, 'finalize')) $result->finalize();
                }

                jsonResponse([
                    'success' => true,
                    'columns' => $columns,
                    'rows' => $rows,
                    'affected' => 0,
                    'elapsed' => $elapsed,
                ]);
            } else {
                if ($dbType === 'mariadb') {
                    $affected = getMariaDBWithDB($dbName)->exec($rawQuery);
                } else {
                    getDB()->exec($rawQuery);
                    $affected = getDB()->changes();
                }
                $elapsed = round(microtime(true) - $start, 4);
                logAction('db_query', strtok($rawQuery, "\n") . ' (' . $affected . ' affected)');
                jsonResponse([
                    'success' => true,
                    'affected' => $affected,
                    'elapsed' => $elapsed,
                ]);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    if ($action === 'drop_table') {
        $table = $input['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table name'], 400);
        try {
            if ($dbType === 'mariadb') {
                getMariaDBWithDB($dbName)->exec("DROP TABLE `$table`");
            } else {
                getDB()->exec("DROP TABLE \"$table\"");
            }
            logAction('db_drop_table', "DROP TABLE $table");
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    if ($action === 'truncate_table') {
        $table = $input['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table name'], 400);
        try {
            if ($dbType === 'mariadb') {
                getMariaDBWithDB($dbName)->exec("TRUNCATE TABLE `$table`");
            } else {
                getDB()->exec("DELETE FROM \"$table\"");
            }
            logAction('db_truncate_table', "TRUNCATE TABLE $table");
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    if ($action === 'create_table') {
        $table = $input['table'] ?? '';
        $columns = $input['columns'] ?? [];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table name'], 400);
        if (empty($columns)) jsonResponse(['error' => 'Columns required'], 400);
        try {
            $defs = [];
            foreach ($columns as $col) {
                $name = $col['name'] ?? '';
                $type = $col['type'] ?? 'TEXT';
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) continue;
                $parts = [$name, $type];
                if (!empty($col['pk'])) $parts[] = 'PRIMARY KEY';
                if (!empty($col['auto'])) $parts[] = 'AUTO_INCREMENT';
                if (!empty($col['notnull'])) $parts[] = 'NOT NULL';
                if (isset($col['default'])) $parts[] = 'DEFAULT ' . (is_numeric($col['default']) ? $col['default'] : "'" . str_replace("'", "''", $col['default']) . "'");
                $defs[] = implode(' ', $parts);
            }
            if (empty($defs)) jsonResponse(['error' => 'No valid columns'], 400);
            $sql = "CREATE TABLE `$table` (" . implode(', ', $defs) . ")";
            if ($dbType === 'mariadb') {
                getMariaDBWithDB($dbName)->exec($sql . " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } else {
                getDB()->exec($sql);
            }
            logAction('db_create_table', "CREATE TABLE $table");
            jsonResponse(['success' => true]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

jsonResponse(['error' => 'Invalid request method'], 400);
