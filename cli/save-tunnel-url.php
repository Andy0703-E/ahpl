<?php
// CLI script: extract cloudflared tunnel URL from log and save to DB
// Usage: php cli/save-tunnel-url.php

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/includes/helpers.php';
initDatabase();

if (file_exists(TUNNEL_LOG)) {
    $content = file_get_contents(TUNNEL_LOG);
    if (preg_match('/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/', $content, $m)) {
        $url = $m[0];
        setSetting(SETTING_CLOUDFLARE_URL, $url);
        echo "Tunnel URL saved: $url\n";
        exit(0);
    }
}

echo "No tunnel URL found in " . TUNNEL_LOG . "\n";
exit(1);
