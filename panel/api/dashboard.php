<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
initDatabase();
header('Content-Type: application/json');

if (!isLoggedIn()) jsonResponse(['error' => 'Unauthorized'], 401);

$db = getDB();
$recentLogs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 8");

$logsHtml = '';
while ($log = $recentLogs->fetchArray(SQLITE3_ASSOC)) {
    $logsHtml .= '<div style="padding:7px 0;border-bottom:1px solid #f5f5f5;font-size:12px;">';
    $logsHtml .= '<span class="badge badge-info">' . sanitize($log['action']) . '</span> ';
    $logsHtml .= sanitize($log['details']);
    $logsHtml .= '<div style="color:#aaa;font-size:11px;margin-top:2px;">' . date('d M H:i', strtotime($log['created_at'])) . '</div>';
    $logsHtml .= '</div>';
}

jsonResponse(['success' => true, 'logsHtml' => $logsHtml]);
