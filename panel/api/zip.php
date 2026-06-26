<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

if (!class_exists('ZipArchive')) {
    jsonResponse(['error' => 'ZipArchive tidak tersedia. Install php-zip di Termux: pkg install php-zip'], 500);
}

@set_time_limit(300);

$base = WEBSITES_PATH;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid request method'], 400);
}

// File upload (multipart) — no CSRF (sama seperti upload.php)
if (!empty($_FILES)) {
    $dir = $_POST['dir'] ?? '/';
    $file = $_FILES['file'] ?? null;
    if (!$file) jsonResponse(['error' => 'No file uploaded'], 400);

    $fullDir = resolvePath($base, $dir);
    if (!isPathSafe($fullDir, $base)) jsonResponse(['error' => 'Invalid path'], 400);
    if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['error' => 'Upload error code: ' . $file['error']], 400);

    $name = basename($file['name']);
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
        jsonResponse(['error' => 'Hanya file ZIP yang didukung'], 400);
    }
    if ($file['size'] > MAX_ZIP_EXTRACT_SIZE) {
        jsonResponse(['error' => 'ZIP terlalu besar (max ' . formatSize(MAX_ZIP_EXTRACT_SIZE) . ')'], 400);
    }

    if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
    $dest = $fullDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['error' => 'Gagal menyimpan file'], 500);
    }

    try {
        $zip = new ZipArchive();
        $code = $zip->open($dest);
        if ($code !== true) {
            unlink($dest);
            jsonResponse(['error' => 'Gagal membuka ZIP (code: ' . $code . ')'], 400);
        }
        $totalFiles = $zip->numFiles;
        $extractOk = $zip->extractTo($fullDir);
        $zip->close();
    } catch (Throwable $e) {
        if (file_exists($dest)) unlink($dest);
        jsonResponse(['error' => 'Gagal mengekstrak: ' . $e->getMessage()], 500);
    }

    if (!$extractOk) {
        unlink($dest);
        jsonResponse(['error' => 'Gagal mengekstrak ZIP (mungkin file corrupt)'], 500);
    }

    $registered = [];
    if (rtrim($dir, '/') === '') {
        $db = getDB();
        $folders = scandir($fullDir);
        foreach ($folders as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!is_dir($fullDir . '/' . $f)) continue;
            $q = $db->prepare("SELECT COUNT(*) FROM websites WHERE folder = :f");
            $q->bindValue(':f', $f, SQLITE3_TEXT);
            $r = $q->execute();
            $cnt = (int)($r->fetchArray(SQLITE3_NUM)[0] ?? 0);
            $r->finalize();
            if ($cnt > 0) continue;
            $siteName = ucwords(trim(preg_replace('/[^a-zA-Z0-9\s]/', ' ', str_replace(['-', '_'], ' ', $f))));
            $in = $db->prepare("INSERT INTO websites (name, folder) VALUES (:n, :f)");
            $in->bindValue(':n', $siteName, SQLITE3_TEXT);
            $in->bindValue(':f', $f, SQLITE3_TEXT);
            $in->execute();
            $registered[] = $siteName;
            logAction('auto_register_website', "$siteName ($f)");
        }
    }

    logAction('zip_upload', "$name -> $dir (" . $totalFiles . " files)");
    jsonResponse(['success' => true, 'file' => $name, 'extracted' => $totalFiles, 'registered' => $registered]);
}

// JSON action
requireCSRF();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'extract') {
    $path = $input['path'] ?? '';
    $fullPath = resolvePath($base, $path);

    if (!isPathSafe($fullPath, $base)) jsonResponse(['error' => 'Invalid path'], 400);
    if (!file_exists($fullPath)) jsonResponse(['error' => 'File tidak ditemukan'], 404);
    if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'zip') {
        jsonResponse(['error' => 'Bukan file ZIP'], 400);
    }

    try {
        $zip = new ZipArchive();
        $code = $zip->open($fullPath);
        if ($code !== true) jsonResponse(['error' => 'Gagal membuka ZIP (code: ' . $code . ')'], 400);

        $totalFiles = $zip->numFiles;
        $destDir = dirname($fullPath);

        if (!$zip->extractTo($destDir)) {
            $zip->close();
            jsonResponse(['error' => 'Gagal mengekstrak ZIP'], 500);
        }
        $zip->close();
    } catch (Throwable $e) {
        jsonResponse(['error' => 'Gagal mengekstrak: ' . $e->getMessage()], 500);
    }

    $registered = [];
    $parentDir = dirname($fullPath);
    if (realpath($parentDir) === realpath(WEBSITES_PATH)) {
        $db = getDB();
        $folders = scandir($parentDir);
        foreach ($folders as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!is_dir($parentDir . '/' . $f)) continue;
            $q = $db->prepare("SELECT COUNT(*) FROM websites WHERE folder = :f");
            $q->bindValue(':f', $f, SQLITE3_TEXT);
            $r = $q->execute();
            $cnt = (int)($r->fetchArray(SQLITE3_NUM)[0] ?? 0);
            $r->finalize();
            if ($cnt > 0) continue;
            $siteName = ucwords(trim(preg_replace('/[^a-zA-Z0-9\s]/', ' ', str_replace(['-', '_'], ' ', $f))));
            $in = $db->prepare("INSERT INTO websites (name, folder) VALUES (:n, :f)");
            $in->bindValue(':n', $siteName, SQLITE3_TEXT);
            $in->bindValue(':f', $f, SQLITE3_TEXT);
            $in->execute();
            $registered[] = $siteName;
            logAction('auto_register_website', "$siteName ($f)");
        }
    }

    logAction('zip_extract', basename($fullPath) . ' -> ' . dirname($path));
    jsonResponse(['success' => true, 'extracted' => $totalFiles, 'registered' => $registered]);
}

jsonResponse(['error' => 'Invalid action'], 400);
