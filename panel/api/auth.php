<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    logAction('logout', $_SESSION['username'] ?? '');
    session_destroy();
    header('Location: /panel/login.php');
    exit;
}

header('Content-Type: application/json');
jsonResponse(['error' => 'Invalid'], 400);
