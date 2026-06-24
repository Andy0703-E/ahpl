<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $folder = trim($input['folder'] ?? '');
        
        if (empty($name) || empty($folder)) jsonResponse(['error' => 'Wajib diisi'], 400);
        
        $folder = preg_replace('/[^a-z0-9-]/', '-', strtolower($folder));
        $folder = preg_replace('/-+/', '-', $folder);
        $folder = trim($folder, '-');
        
        $exists = $db->querySingle("SELECT COUNT(*) FROM websites WHERE folder = '" . SQLite3::escapeString($folder) . "'");
        if ($exists) jsonResponse(['error' => 'Folder sudah ada'], 400);
        
        $dir = WEBSITES_PATH . '/' . $folder;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $html = $input['html'] ?? '';
        if (!empty($html)) {
            file_put_contents($dir . '/index.html', $html);
        } else {
            file_put_contents($dir . '/index.html', "<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"><title>" . htmlspecialchars($name) . "</title></head>\n<body><h1>" . htmlspecialchars($name) . "</h1><p>Website ini berhasil dibuat!</p></body>\n</html>");
        }
        
        $stmt = $db->prepare("INSERT INTO websites (name, folder) VALUES (:name, :folder)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':folder', $folder, SQLITE3_TEXT);
        $stmt->execute();
        
        logAction('create_website', "$name ($folder)");
        jsonResponse(['success' => true]);
    }
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Invalid'], 400);
    
    $site = $db->querySingle("SELECT * FROM websites WHERE id = $id", true);
    if (!$site) jsonResponse(['error' => 'Not found'], 404);
    
    $dir = WEBSITES_PATH . '/' . $site['folder'];
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
        rmdir($dir);
    }
    
    $db->exec("DELETE FROM websites WHERE id = $id");
    logAction('delete_website', $site['name']);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid'], 400);
