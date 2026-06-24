<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'save_key') {
    $key = trim($input['key'] ?? '');
    if (empty($key)) jsonResponse(['error' => 'Key wajib diisi'], 400);
    
    $db = getDB();
    $existing = $db->querySingle("SELECT COUNT(*) FROM settings WHERE key = 'glm_key'");
    if ($existing) {
        $db->exec("UPDATE settings SET value = '" . SQLite3::escapeString($key) . "' WHERE key = 'glm_key'");
    } else {
        $db->exec("INSERT INTO settings (key, value) VALUES ('glm_key', '" . SQLite3::escapeString($key) . "')");
    }
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid'], 400);
