<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && ($_GET['action'] ?? '') === 'status') {
    $services = [
        'nginx' => checkServiceStatus('nginx'),
        'php-fpm' => checkServiceStatus('php-fpm'),
        'cloudflared' => checkServiceStatus('cloudflared'),
    ];
    jsonResponse(['success' => true, 'services' => $services]);
}

if ($method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $service = $input['service'] ?? '';
    $action = $input['action'] ?? '';

    if (!in_array($service, ['nginx', 'php-fpm', 'cloudflared'])) {
        jsonResponse(['error' => 'Service tidak valid'], 400);
    }

    if ($action === 'restart') {
        $res1 = stopService($service);
        sleep(1);
        $res2 = startService($service);
        if (isset($res2['success'])) {
            logAction('service_restart', "$service restarted");
            jsonResponse(['success' => true, 'status' => 'running']);
        }
        jsonResponse(['error' => 'Gagal restart ' . $service], 500);
    }

    if ($action === 'start') {
        $res = startService($service);
        if (isset($res['success'])) {
            logAction('service_start', "$service started");
            jsonResponse(['success' => true, 'status' => 'running']);
        }
        jsonResponse(['error' => $res['error'] ?? "Gagal start $service"], 500);
    }

    if ($action === 'stop') {
        $res = stopService($service);
        if (isset($res['success'])) {
            logAction('service_stop', "$service stopped");
            jsonResponse(['success' => true, 'status' => 'stopped']);
        }
        jsonResponse(['error' => $res['error'] ?? "Gagal stop $service"], 500);
    }

    jsonResponse(['error' => 'Aksi tidak dikenal'], 400);
}

jsonResponse(['error' => 'Invalid'], 400);
