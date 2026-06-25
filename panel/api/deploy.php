<?php
ob_start();
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PHP Fatal: ' . $err['message']]);
    }
});
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'github') {
        $repo = trim($input['repo'] ?? '');
        $folder = trim($input['folder'] ?? '');
        if (empty($repo) || empty($folder)) jsonResponse(['error' => 'Repo dan folder wajib diisi'], 400);

        $siteDir = WEBSITES_PATH . '/' . $folder;
        if (!is_dir($siteDir)) mkdir($siteDir, 0755, true);

        $res = deployGithub($repo, $siteDir);
        if (isset($res['error'])) jsonResponse(['error' => $res['error']], 500);

        $db = getDB();
        $name = basename($repo, '.git');
        $name = str_replace(['-','_'], ' ', $name);
        $stmt = $db->prepare("INSERT OR IGNORE INTO websites (name, folder) VALUES (:name, :folder)");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':folder', $folder, SQLITE3_TEXT);
        $stmt->execute();

        logAction('deploy_github', "Deployed $repo to $folder ({$res['copied']} files)");
        jsonResponse(['success' => true, 'deploy' => $res]);
    }
}

jsonResponse(['error' => 'Invalid'], 400);
