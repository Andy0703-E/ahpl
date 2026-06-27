<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];
$base = WEBSITES_PATH;

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'read') {
        $path = $_GET['path'] ?? '';
        $full = resolvePath($base, $path);
        if (!isPathSafe($full, $base) || !file_exists($full) || is_dir($full)) {
            jsonResponse(['error' => 'Not found'], 404);
        }
        $content = file_get_contents($full);
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $map = ['html'=>'htmlmixed','htm'=>'htmlmixed','css'=>'css','js'=>'javascript','php'=>'php','json'=>'application/json'];
        $lang = $map[$ext] ?? 'htmlmixed';
        jsonResponse(['success' => true, 'content' => $content, 'lang' => $lang, 'name' => basename($full)]);
    }
}

if ($method === 'DELETE') {
    requireCSRF();
    $path = $_GET['path'] ?? '';
    $full = resolvePath($base, $path);
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
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'rename') {
        $path = $input['path'] ?? '';
        $newName = $input['newName'] ?? '';
        $full = resolvePath($base, $path);
        if (!isPathSafe($full, $base) || !file_exists($full)) jsonResponse(['error' => 'Not found'], 404);
        $newPath = dirname($full) . '/' . basename($newName);
        rename($full, $newPath);
        logAction('rename', "$path -> $newName");
        jsonResponse(['success' => true]);
    }

    if ($action === 'save') {
        $path = $input['path'] ?? '';
        $content = $input['content'] ?? '';
        $full = resolvePath($base, $path);
        if (!isPathSafe($full, $base)) jsonResponse(['error' => 'Invalid path'], 400);
        file_put_contents($full, $content);
        logAction('edit', $path);
        jsonResponse(['success' => true]);
    }
}

if ($method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'mkdir') {
        $dir = $input['dir'] ?? '/';
        $name = $input['name'] ?? '';
        $full = resolvePath($base, $dir);
        if (!isPathSafe($full, $base)) jsonResponse(['error' => 'Invalid'], 400);
        $newDir = resolvePath($full, basename($name));
        if (!isPathSafe($newDir, $base)) jsonResponse(['error' => 'Invalid'], 400);
        mkdir($newDir, 0755, true);
        logAction('mkdir', "$dir/$name");
        jsonResponse(['success' => true]);
    }

    if ($action === 'create_file') {
        $dir = $input['dir'] ?? '/';
        $name = $input['name'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name)) {
            jsonResponse(['error' => 'Nama file tidak valid'], 400);
        }
        $full = resolvePath($base, $dir);
        if (!isPathSafe($full, $base)) jsonResponse(['error' => 'Invalid'], 400);
        $newFile = resolvePath($full, basename($name));
        if (!isPathSafe($newFile, $base)) jsonResponse(['error' => 'Invalid'], 400);
        if (file_exists($newFile)) jsonResponse(['error' => 'File sudah ada'], 400);
        if (file_put_contents($newFile, '') === false) {
            jsonResponse(['error' => 'Gagal membuat file'], 500);
        }
        $rel = ltrim(rtrim($dir, '/') . '/' . $name, '/');
        logAction('create_file', $rel);
        jsonResponse(['success' => true, 'path' => $rel]);
    }
}

jsonResponse(['error' => 'Invalid'], 400);
