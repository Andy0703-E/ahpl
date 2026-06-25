<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$logFile = TUNNEL_LOG;
$url = '';

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (preg_match('/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/', $content, $m)) {
        $url = $m[0];
    }
}

jsonResponse(['url' => $url]);
