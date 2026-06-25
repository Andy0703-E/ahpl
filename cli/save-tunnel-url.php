<?php
// CLI script: extract cloudflared tunnel URL from log and save to DB
// Usage: php cli/save-tunnel-url.php

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/includes/helpers.php';
initDatabase();

$paths = [
    TUNNEL_LOG,
    '/data/data/com.termux/files/usr/tmp/cloudflared.log',
    '/tmp/cloudflared.log',
];

foreach ($paths as $lf) {
    if (!file_exists($lf)) continue;
    $content = file_get_contents($lf);
    if (preg_match('/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/', $content, $m)) {
        $url = $m[0];
        setSetting(SETTING_CLOUDFLARE_URL, $url);
        echo "Tunnel URL saved: $url\n";
        exit(0);
    }
}

echo "No tunnel URL found.\n";
echo "Checked:\n";
foreach ($paths as $lf) {
    $exists = file_exists($lf) ? 'exists' : 'not found';
    $size = file_exists($lf) ? ' (' . strlen(file_get_contents($lf)) . ' bytes)' : '';
    echo "  $lf ($exists)$size\n";
}
exit(1);
