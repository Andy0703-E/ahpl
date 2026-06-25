<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$url = '';
$saved = false;

if (file_exists(TUNNEL_LOG)) {
    $content = file_get_contents(TUNNEL_LOG);
    if (preg_match('/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/', $content, $m)) {
        $url = $m[0];
    }
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

// Auto-save ke database jika URL ditemukan
if ($url) {
    setSetting(SETTING_CLOUDFLARE_URL, $url);
    $saved = true;
}

jsonResponse(['url' => $url, 'saved' => $saved]);