<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'download') {
        $file = basename($_GET['file'] ?? '');
        $fp = BACKUPS_PATH . '/' . $file;
        if (!empty($file) && file_exists($fp) && isPathSafe(realpath($fp), realpath(BACKUPS_PATH))) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($fp));
            readfile($fp);
            exit;
        }
        jsonResponse(['error' => 'File tidak ditemukan'], 404);
    }

    $backupDir = BACKUPS_PATH;
    $files = [];
    if (is_dir($backupDir)) {
        foreach (scandir($backupDir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $fp = $backupDir . '/' . $f;
            $files[] = [
                'name' => $f,
                'size' => filesize($fp),
                'date' => date('Y-m-d H:i', filemtime($fp)),
            ];
        }
    }
    rsort($files);
    jsonResponse(['success' => true, 'backups' => $files]);
}

if ($method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        $type = $input['type'] ?? 'websites';
        $res = createBackup($type);
        if (isset($res['error'])) jsonResponse(['error' => $res['error']], 500);
        logAction('backup', "Backup $type: {$res['file']}");
        jsonResponse(['success' => true, 'backup' => $res]);
    }

    if ($action === 'delete') {
        $file = basename($input['file'] ?? '');
        $fp = BACKUPS_PATH . '/' . $file;
        if (isPathSafe(realpath($fp), realpath(BACKUPS_PATH)) && file_exists($fp)) {
            unlink($fp);
            logAction('backup_delete', "Deleted backup: $file");
            jsonResponse(['success' => true]);
        }
        jsonResponse(['error' => 'File tidak ditemukan'], 404);
    }

    if ($action === 'download') {
        $file = basename($input['file'] ?? '');
        $fp = BACKUPS_PATH . '/' . $file;
        if (file_exists($fp)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($fp));
            readfile($fp);
            exit;
        }
        jsonResponse(['error' => 'File tidak ditemukan'], 404);
    }
}

jsonResponse(['error' => 'Invalid'], 400);
