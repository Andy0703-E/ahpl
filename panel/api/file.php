<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];
$base = WEBSITES_PATH;

if ($method === 'DELETE') {
    $path = $_GET['path'] ?? '';
    $full = rtrim($base . '/' . ltrim($path, '/'), '/');
    if (!isPathSafe($full, $base) || !file_exists($full)) jsonResponse(['error' => 'Not found'], 404);
    
    if (is_dir($full)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
        rmdir($full);
    } else {
        unlink($full);
    }
    logAction('delete', $path);
    jsonResponse(['success' => true]);
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'rename') {
        $path = $input['path'] ?? '';
        $newName = $input['newName'] ?? '';
        $full = rtrim($base . '/' . ltrim($path, '/'), '/');
        if (!isPathSafe($full, $base) || !file_exists($full)) jsonResponse(['error' => 'Not found'], 404);
        $newPath = dirname($full) . '/' . basename($newName);
        rename($full, $newPath);
        logAction('rename', "$path -> $newName");
        jsonResponse(['success' => true]);
    }
    
    if ($action === 'save') {
        $path = $input['path'] ?? '';
        $content = $input['content'] ?? '';
        $full = rtrim($base . '/' . ltrim($path, '/'), '/');
        if (!isPathSafe($full, $base)) jsonResponse(['error' => 'Invalid path'], 400);
        file_put_contents($full, $content);
        logAction('edit', $path);
        jsonResponse(['success' => true]);
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'mkdir') {
        $dir = $input['dir'] ?? '/';
        $name = $input['name'] ?? '';
        $full = rtrim($base . '/' . ltrim($dir, '/'), '/');
        if (!isPathSafe($full, $base)) jsonResponse(['error' => 'Invalid'], 400);
        mkdir($full . '/' . basename($name), 0755, true);
        logAction('mkdir', "$dir/$name");
        jsonResponse(['success' => true]);
    }
}

jsonResponse(['error' => 'Invalid'], 400);
