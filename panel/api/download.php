<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();

if (!isLoggedIn()) { header('Location: /panel/login.php'); exit; }

$path = $_GET['path'] ?? '';
$base = WEBSITES_PATH;
$full = resolvePath($base, $path);

if (!isPathSafe($full, $base) || !file_exists($full) || is_dir($full)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($full) . '"');
header('Content-Length: ' . filesize($full));
readfile($full);
exit;
