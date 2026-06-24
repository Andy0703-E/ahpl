<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$statusFile = SERVER_PATH . '/tunnel.status';
$domainFile = SERVER_PATH . '/tunnel.domain';
$pidFile = SERVER_PATH . '/tunnel.pid';
$logFile = SERVER_PATH . '/tunnel.log';

switch ($action) {
    case 'start':
        $domain = @file_get_contents($domainFile) ?: '';
        if (empty($domain)) {
            $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $domain = '';
            for ($i = 0; $i < 12; $i++) $domain .= $chars[random_int(0, strlen($chars) - 1)];
            $domain .= '.trycloudflare.com';
            file_put_contents($domainFile, $domain);
        }
        
        if (PHP_OS_FAMILY === 'Linux') {
            $cmd = "nohup cloudflared tunnel --url http://127.0.0.1:8080 > " . escapeshellarg($logFile) . " 2>&1 & echo $! > " . escapeshellarg($pidFile);
            exec($cmd);
            file_put_contents($statusFile, 'running');
            logAction('tunnel_start', $domain);
            jsonResponse(['success' => true, 'domain' => $domain, 'message' => 'Tunnel started']);
        }
        jsonResponse(['error' => 'Hanya support di Termux/Linux'], 400);
        break;
    
    case 'stop':
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if (PHP_OS_FAMILY === 'Linux') exec("kill $pid 2>/dev/null");
            unlink($pidFile);
        }
        exec("pkill -f 'cloudflared tunnel' 2>/dev/null");
        file_put_contents($statusFile, 'stopped');
        logAction('tunnel_stop', '');
        jsonResponse(['success' => true, 'message' => 'Tunnel stopped']);
        break;
    
    case 'set_domain':
        $domain = $input['domain'] ?? '';
        if (empty($domain)) jsonResponse(['error' => 'Wajib diisi'], 400);
        file_put_contents($domainFile, $domain);
        jsonResponse(['success' => true]);
        break;
    
    default:
        jsonResponse(['error' => 'Invalid'], 400);
}
