<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $date = date('Y-m-d_H-i');
    
    switch ($type) {
        case 'websites':
            $zipFile = BACKUPS_PATH . "/websites-{$date}.zip";
            if (!is_dir(WEBSITES_PATH)) jsonResponse(['error' => 'No websites'], 400);
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(WEBSITES_PATH, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($files as $f) {
                    $rel = str_replace(WEBSITES_PATH . '/', '', $f->getPathname());
                    $f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getPathname(), $rel);
                }
                $zip->close();
                logAction('backup', 'Websites backup');
                jsonResponse(['success' => true, 'file' => basename($zipFile)]);
            }
            break;
        
        case 'database':
            $zipFile = BACKUPS_PATH . "/database-{$date}.zip";
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                foreach (scandir(DATABASE_PATH) as $f) {
                    if ($f !== '.' && $f !== '..') $zip->addFile(DATABASE_PATH . '/' . $f, $f);
                }
                $zip->close();
                logAction('backup', 'Database backup');
                jsonResponse(['success' => true, 'file' => basename($zipFile)]);
            }
            break;
        
        case 'full':
            $zipFile = BACKUPS_PATH . "/full-{$date}.zip";
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                foreach ([WEBSITES_PATH, DATABASE_PATH, UPLOADS_PATH] as $dir) {
                    if (!is_dir($dir)) continue;
                    $base = basename($dir);
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($files as $f) {
                        $rel = $base . '/' . str_replace($dir . '/', '', $f->getPathname());
                        $f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getPathname(), $rel);
                    }
                }
                $zip->close();
                logAction('backup', 'Full backup');
                jsonResponse(['success' => true, 'file' => basename($zipFile)]);
            }
            break;
    }
    jsonResponse(['error' => 'Gagal membuat zip'], 500);
}

if ($method === 'DELETE') {
    $file = basename($_GET['file'] ?? '');
    if (empty($file)) jsonResponse(['error' => 'Wajib'], 400);
    $fp = BACKUPS_PATH . '/' . $file;
    if (!file_exists($fp)) jsonResponse(['error' => 'Not found'], 404);
    unlink($fp);
    logAction('delete_backup', $file);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid'], 400);
