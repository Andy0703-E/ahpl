<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
initDatabase();

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    header('Location: /panel/login.php');
    exit;
}

header('Content-Type: application/json');
jsonResponse(['error' => 'Invalid'], 400);
