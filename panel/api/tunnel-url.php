<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$url = '';
if (file_exists(TUNNEL_LOG)) {
    $content = file_get_contents(TUNNEL_LOG);
    if (preg_match('/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/', $content, $m)) {
        $url = $m[0];
    }
    // Debug: jika ada param debug, kembalikan log mentah
    if (isset($_GET['debug'])) {
        jsonResponse(['url' => $url, 'log_path' => TUNNEL_LOG, 'log_exists' => true, 'log_size' => strlen($content), 'log_snippet' => substr($content, 0, 2000)]);
        exit;
    }
} else {
    if (isset($_GET['debug'])) {
        jsonResponse(['url' => $url, 'log_path' => TUNNEL_LOG, 'log_exists' => false]);
        exit;
    }
}

jsonResponse(['url' => $url]);
