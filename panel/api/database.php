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
        $colRes = $db->query("PRAGMA table_info(\"$table\")");
        while ($c = $colRes->fetchArray(SQLITE3_ASSOC)) {
            $cols[] = $c['name'];
        }

        $rows = [];
        $rowRes = $db->query("SELECT * FROM \"$table\" LIMIT $perPage OFFSET $offset");
        while ($r = $rowRes->fetchArray(SQLITE3_NUM)) {
            $rows[] = $r;
        }

        jsonResponse(['success' => true, 'columns' => $cols, 'rows' => $rows, 'total' => $total, 'page' => $page]);
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

    if ($action === 'query') {
        $rawQuery = trim($input['query'] ?? '');
        if (empty($rawQuery)) jsonResponse(['error' => 'Query wajib diisi'], 400);

        $firstWord = strtoupper(explode(' ', $rawQuery, 2)[0]);
        $allowed = ['SELECT', 'PRAGMA', 'EXPLAIN'];
        if (!in_array($firstWord, $allowed)) {
            jsonResponse(['error' => 'Hanya query SELECT, PRAGMA, dan EXPLAIN yang diizinkan'], 400);
        }

        $start = microtime(true);
        try {
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
                'truncated' => $colCount > 0 && $db->querySingle("SELECT COUNT(*) FROM ($rawQuery)") > $limit,
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    jsonResponse(['error' => 'Invalid action'], 400);
}

jsonResponse(['error' => 'Invalid request method'], 400);
