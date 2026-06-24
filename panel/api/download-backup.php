<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();

if (!isLoggedIn()) { header('Location: /panel/login.php'); exit; }

$file = basename($_GET['file'] ?? '');
if (empty($file)) { http_response_code(400); echo 'No file'; exit; }

$fp = BACKUPS_PATH . '/' . $file;
if (!file_exists($fp) || is_dir($fp)) { http_response_code(404); echo 'Not found'; exit; }

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($fp));
readfile($fp);
exit;
