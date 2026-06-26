<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list_tables') {
        $tables = [];
        $res = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $count = $db->querySingle("SELECT COUNT(*) FROM \"" . $row['name'] . "\"");
            $tables[] = ['name' => $row['name'], 'row_count' => (int)$count];
        }
        jsonResponse(['success' => true, 'tables' => $tables]);
    }

    if ($action === 'get_table') {
        $table = $_GET['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            jsonResponse(['error' => 'Invalid table name'], 400);
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $total = (int)$db->querySingle("SELECT COUNT(*) FROM \"$table\"");
        $cols = [];
        $schema = [];
        $colRes = $db->query("PRAGMA table_info(\"$table\")");
        while ($c = $colRes->fetchArray(SQLITE3_ASSOC)) {
            $cols[] = $c['name'];
            $schema[] = ['name' => $c['name'], 'type' => $c['type'], 'notnull' => (int)$c['notnull'], 'dflt_value' => $c['dflt_value'], 'pk' => (int)$c['pk']];
        }

        $rows = [];
        $rowRes = $db->query("SELECT * FROM \"$table\" LIMIT $perPage OFFSET $offset");
        while ($r = $rowRes->fetchArray(SQLITE3_NUM)) {
            $rows[] = $r;
        }

        jsonResponse(['success' => true, 'columns' => $cols, 'schema' => $schema, 'rows' => $rows, 'total' => $total, 'page' => $page]);
    }

    if ($action === 'get_schema') {
        $table = $_GET['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            jsonResponse(['error' => 'Invalid table name'], 400);
        }
        $schema = [];
        $res = $db->query("PRAGMA table_info(\"$table\")");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $schema[] = $row;
        }
        jsonResponse(['success' => true, 'schema' => $schema]);
    }

    if ($action === 'export_csv') {
        $table = $_GET['table'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            jsonResponse(['error' => 'Invalid table name'], 400);
        }

        $cols = [];
        $colRes = $db->query("PRAGMA table_info(\"$table\")");
        while ($c = $colRes->fetchArray(SQLITE3_ASSOC)) {
            $cols[] = $c['name'];
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $table . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $cols);

        $rowRes = $db->query("SELECT * FROM \"$table\"");
        while ($r = $rowRes->fetchArray(SQLITE3_ASSOC)) {
            $row = [];
            foreach ($cols as $c) {
                $row[] = $r[$c];
            }
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

if ($method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'insert_row') {
        $table = $input['table'] ?? '';
        $data = $input['data'] ?? [];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (empty($data)) jsonResponse(['error' => 'Data kosong'], 400);

        $cols = [];
        $vals = [];
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $cols[] = "\"$k\"";
            $vals[] = ":$k";
        }
        if (empty($cols)) jsonResponse(['error' => 'Data kosong'], 400);

        $colList = implode(', ', $cols);
        $valList = implode(', ', $vals);
        $stmt = $db->prepare("INSERT INTO \"$table\" ($colList) VALUES ($valList)");
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $stmt->bindValue(":$k", $v, is_numeric($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $stmt->execute();

        logAction('db_insert', "INSERT INTO $table");
        jsonResponse(['success' => true, 'id' => $db->lastInsertRowID()]);
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
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $sets[] = "\"$k\" = :$k";
        }
        if (empty($sets)) jsonResponse(['error' => 'Data kosong'], 400);

        $setStr = implode(', ', $sets);
        $stmt = $db->prepare("UPDATE \"$table\" SET $setStr WHERE \"$idColumn\" = :__id__");
        foreach ($data as $k => $v) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) continue;
            $stmt->bindValue(":$k", $v, is_numeric($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $stmt->bindValue(':__id__', $idValue, is_numeric($idValue) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        $stmt->execute();

        logAction('db_update', "UPDATE $table WHERE $idColumn = $idValue");
        jsonResponse(['success' => true, 'affected' => $db->changes()]);
    }

    if ($action === 'delete_row') {
        $table = $input['table'] ?? '';
        $idColumn = $input['id_column'] ?? '';
        $idValue = $input['id_value'] ?? '';
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) jsonResponse(['error' => 'Invalid table'], 400);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $idColumn)) jsonResponse(['error' => 'Invalid id column'], 400);

        $stmt = $db->prepare("DELETE FROM \"$table\" WHERE \"$idColumn\" = :id");
        $stmt->bindValue(':id', $idValue, is_numeric($idValue) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        $stmt->execute();

        logAction('db_delete', "DELETE FROM $table WHERE $idColumn = $idValue");
        jsonResponse(['success' => true, 'affected' => $db->changes()]);
    }

    if ($action === 'query') {
        $rawQuery = trim($input['query'] ?? '');
        if (empty($rawQuery)) jsonResponse(['error' => 'Query wajib diisi'], 400);

        $firstWord = strtoupper(explode(' ', $rawQuery, 2)[0]);
        $readOps = ['SELECT', 'PRAGMA', 'EXPLAIN'];
        $writeOps = ['INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'REINDEX', 'REPLACE', 'VACUUM'];
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
                $result = $db->query($rawQuery);
                $elapsed = round(microtime(true) - $start, 4);

                if ($result === false) {
                    jsonResponse(['error' => $db->lastErrorMsg()], 400);
                }

                $columns = [];
                $rows = [];
                $colCount = $result->numColumns();
                if ($colCount > 0) {
                    for ($i = 0; $i < $colCount; $i++) {
                        $columns[] = $result->columnName($i);
                    }
                    $limit = 1000;
                    while ($row = $result->fetchArray(SQLITE3_NUM)) {
                        $rows[] = $row;
                        if (count($rows) >= $limit) break;
                    }
                }
                $result->finalize();

                jsonResponse([
                    'success' => true,
                    'columns' => $columns,
                    'rows' => $rows,
                    'affected' => $db->changes(),
                    'elapsed' => $elapsed,
                ]);
            } else {
                $db->exec($rawQuery);
                $elapsed = round(microtime(true) - $start, 4);
                $affected = $db->changes();
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
