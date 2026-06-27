<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$dbType = $_GET['db_type'] ?? ($_POST['db_type'] ?? 'sqlite');
$dbType = in_array($dbType, ['sqlite', 'mariadb']) ? $dbType : 'sqlite';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list_tables') {
        try {
            $tables = dbListTables($dbType);
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
            $total = dbGetRowCount($dbType, $table);
            $schema = dbGetSchema($dbType, $table);
            $cols = array_column($schema, 'name');
            $q = dbQuote($dbType, $table);

            $rows = [];
            if ($dbType === 'mariadb') {
                $pdo = getMariaDB();
                $stmt = $pdo->prepare("SELECT * FROM $q LIMIT :lim OFFSET :off");
                $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            } else {
                $rowRes = getDB()->query("SELECT * FROM $q LIMIT $perPage OFFSET $offset");
                while ($r = $rowRes->fetchArray(SQLITE3_NUM)) {
                    $rows[] = $r;
                }
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
            $schema = dbGetSchema($dbType, $table);
            jsonResponse(['success' => true, 'schema' => $schema]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengambil schema: ' . $e->getMessage()], 500);
        }
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
            $pdo = getMariaDB();
            $stmt = $pdo->query("SELECT * FROM $q");
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

    if ($action === 'insert_row') {
        $table = $input['table'] ?? '';
        $data = $input['data'] ?? [];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (empty($data)) jsonResponse(['error' => 'Data kosong'], 400);

        $cols = [];
        $vals = [];
        $q = dbQuote($dbType, '');
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $cols[] = $q . $k . $q;
            $vals[] = ":$k";
        }
        if (empty($cols)) jsonResponse(['error' => 'Data kosong'], 400);

        $colList = implode(', ', $cols);
        $valList = implode(', ', $vals);
        $tq = dbQuote($dbType, $table);
        $paramTypes = [];
        $params = [];
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $params[":$k"] = $v;
            if (is_numeric($v)) $paramTypes[":$k"] = SQLITE3_INTEGER;
        }

        dbPrepareExecute($dbType, "INSERT INTO $tq ($colList) VALUES ($valList)", $params, $paramTypes);

        logAction('db_insert', "INSERT INTO $table");
        jsonResponse(['success' => true, 'id' => dbLastInsertId($dbType)]);
    }

    if ($action === 'update_row') {
        $table = $input['table'] ?? '';
        $idColumn = $input['id_column'] ?? '';
        $idValue = $input['id_value'] ?? '';
        $data = $input['data'] ?? [];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $idColumn)) jsonResponse(['error' => 'Invalid id column'], 400);
        if (empty($data)) jsonResponse(['error' => 'Data kosong'], 400);

        $sets = [];
        $q = dbQuote($dbType, '');
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $sets[] = $q . $k . $q . " = :$k";
        }
        if (empty($sets)) jsonResponse(['error' => 'Data kosong'], 400);

        $setStr = implode(', ', $sets);
        $tq = dbQuote($dbType, $table);
        $idQ = $q . $idColumn . $q;
        $params = [':__id__' => $idValue];
        $paramTypes = [':__id__' => is_numeric($idValue) ? SQLITE3_INTEGER : SQLITE3_TEXT];
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $params[":$k"] = $v;
            $paramTypes[":$k"] = is_numeric($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
        }

        dbPrepareExecute($dbType, "UPDATE $tq SET $setStr WHERE $idQ = :__id__", $params, $paramTypes);

        logAction('db_update', "UPDATE $table WHERE $idColumn = $idValue");
        jsonResponse(['success' => true, 'affected' => 1]);
    }

    if ($action === 'delete_row') {
        $table = $input['table'] ?? '';
        $idColumn = $input['id_column'] ?? '';
        $idValue = $input['id_value'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $idColumn)) jsonResponse(['error' => 'Invalid id column'], 400);

        $tq = dbQuote($dbType, $table);
        $q = dbQuote($dbType, '');
        $idQ = $q . $idColumn . $q;
        dbPrepareExecute($dbType, "DELETE FROM $tq WHERE $idQ = :id", [':id' => $idValue], [':id' => is_numeric($idValue) ? SQLITE3_INTEGER : SQLITE3_TEXT]);

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
            jsonResponse(['error' => 'Konfirmasi diperlukan untuk query write', 'require_confirm' => true], 400);
        }

        $start = microtime(true);
        try {
            if ($isRead) {
                $result = dbQuery($dbType, $rawQuery);
                $elapsed = round(microtime(true) - $start, 4);

                if ($result === false) {
                    jsonResponse(['error' => 'Query execution failed'], 400);
                }

                $columns = [];
                $rows = [];
                $colCount = dbColumnCount($dbType, $result);
                if ($colCount > 0) {
                    for ($i = 0; $i < $colCount; $i++) {
                        $columns[] = dbColumnName($dbType, $result, $i);
                    }
                    $rows = dbFetchAll($dbType, $result);
                    if (count($rows) > 1000) {
                        $rows = array_slice($rows, 0, 1000);
                    }
                }
                if ($dbType === 'sqlite' && method_exists($result, 'finalize')) {
                    $result->finalize();
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
                    $affected = getMariaDB()->exec($rawQuery);
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

    jsonResponse(['error' => 'Invalid action'], 400);
}

jsonResponse(['error' => 'Invalid request method'], 400);
