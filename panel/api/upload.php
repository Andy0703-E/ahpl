<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $dir = $_POST['dir'] ?? '/';
    $file = $_FILES['file'];
    $base = WEBSITES_PATH;
    $fullDir = rtrim($base . '/' . ltrim($dir, '/'), '/');
    
    if (!isPathSafe($fullDir, $base)) jsonResponse(['error' => 'Invalid path'], 400);
    if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['error' => 'Upload error'], 400);
    if ($file['size'] > MAX_UPLOAD_SIZE) jsonResponse(['error' => 'Terlalu besar (max 100MB)'], 400);
    
    $name = basename($file['name']);
    $dest = $fullDir . '/' . $name;
    
    if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        logAction('upload', "$name -> $dir");
        
        if (pathinfo($name, PATHINFO_EXTENSION) === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($dest) === true) {
                $extractDir = $fullDir . '/' . pathinfo($name, PATHINFO_FILENAME);
                if (!is_dir($extractDir)) mkdir($extractDir, 0755, true);
                $zip->extractTo($extractDir);
                $zip->close();
                logAction('extract', "Extracted $name");
            }
        }
        
        jsonResponse(['success' => true, 'message' => "'$name' diupload"]);
    }
    jsonResponse(['error' => 'Gagal menyimpan'], 500);
}

jsonResponse(['error' => 'Invalid'], 400);
