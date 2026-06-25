<?php
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

    if ($action === 'website') {
        $folder = trim($input['folder'] ?? '');
        if (empty($folder)) jsonResponse(['error' => 'Folder wajib diisi'], 400);

        $siteDir = WEBSITES_PATH . '/' . $folder;
        if (!is_dir($siteDir)) jsonResponse(['error' => 'Folder tidak ditemukan'], 404);

        $zipPath = $input['zipPath'] ?? UPLOADS_PATH . '/' . $folder . '.zip';
        if (!file_exists($zipPath)) jsonResponse(['error' => 'File ZIP tidak ditemukan'], 404);

        $res = deployZip($zipPath, $siteDir);
        if (isset($res['error'])) jsonResponse(['error' => $res['error']], 500);

        logAction('deploy_zip', "Deployed ZIP to $folder ({$res['extracted']} files)");
        jsonResponse(['success' => true, 'deploy' => $res]);
    }

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
