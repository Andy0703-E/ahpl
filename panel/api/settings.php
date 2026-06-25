<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);
requireCSRF();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'save_key') {
    $key = trim($input['key'] ?? '');
    if (empty($key)) jsonResponse(['error' => 'Key wajib diisi'], 400);

    setCerebrasKey($key);
    jsonResponse(['success' => true]);
}

if ($action === 'change_password') {
    $newPassword = $input['password'] ?? '';
    if (strlen($newPassword) < 6) jsonResponse(['error' => 'Password minimal 6 karakter'], 400);
    changePassword($_SESSION['user_id'], $newPassword);
    logAction('change_password', $_SESSION['username'] ?? '');
    jsonResponse(['success' => true]);
}

if ($action === 'save_tunnel_url') {
    $url = trim($input['url'] ?? '');
    if (empty($url)) jsonResponse(['error' => 'URL wajib diisi'], 400);
    setSetting(SETTING_TUNNEL_URL, $url);
    logAction('tunnel_url', "URL tunnel disimpan: $url");
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid'], 400);
