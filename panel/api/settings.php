<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(['error' => 'Invalid'], 400);
}

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

jsonResponse(['error' => 'Invalid'], 400);
