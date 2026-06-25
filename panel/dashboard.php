<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$server = getServerInfo();

$db = getDB();
$websiteCount = $db->querySingle("SELECT COUNT(*) FROM websites");
$fileCount = countFiles(WEBSITES_PATH);
$recentLogs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 8");

$diskPct = $server['disk_total'] > 0 ? round(($server['disk_used'] / $server['disk_total']) * 100) : 0;
$memPct = $server['mem_total'] > 0 ? round(($server['mem_used'] / $server['mem_total']) * 100) : 0;
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h4>Online</h4>
            <p>Server Status</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-globe"></i></div>
        <div class="stat-info">
            <h4><?= $websiteCount ?></h4>
            <p>Websites</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-file"></i></div>
        <div class="stat-info">
            <h4><?= $fileCount ?></h4>
            <p>Total Files</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-hdd"></i></div>
        <div class="stat-info">
            <h4><?= formatSize($server['disk_used']) ?></h4>
            <p><?= formatSize($server['disk_total']) ?> Storage</p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Server Info</h3></div>
        <div class="card-body">
            <table class="table">
                <tr><td>PHP Version</td><td><strong><?= $server['php_version'] ?></strong></td></tr>
                <tr><td>Storage</td>
                    <td>
                        <?= formatSize($server['disk_used']) ?> / <?= formatSize($server['disk_total']) ?>
                        <div class="progress-bar" style="margin-top:5px;"><div class="progress-fill" style="width:<?= $diskPct ?>%;background:<?= $diskPct > 80 ? 'var(--danger)' : 'var(--primary)' ?>"></div></div>
                    </td>
                </tr>
                <tr><td>RAM</td>
                    <td>
                        <?= formatSize($server['mem_used']) ?> / <?= formatSize($server['mem_total']) ?>
                        <div class="progress-bar" style="margin-top:5px;"><div class="progress-fill" style="width:<?= $memPct ?>%;background:<?= $memPct > 80 ? 'var(--danger)' : 'var(--success)' ?>"></div></div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-history"></i> Activity Log</h3></div>
        <div class="card-body" id="activityLog" style="max-height:280px;overflow-y:auto;">
            <?php while ($log = $recentLogs->fetchArray(SQLITE3_ASSOC)): ?>
                <div style="padding:7px 0;border-bottom:1px solid #f5f5f5;font-size:12px;">
                    <span class="badge badge-info"><?= sanitize($log['action']) ?></span>
                    <?= sanitize($log['details']) ?>
                    <div style="color:#aaa;font-size:11px;margin-top:2px;"><?= date('d M H:i', strtotime($log['created_at'])) ?></div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    fetch('/panel/api/dashboard.php', { headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ } })
        .then(r => r.json())
        .then(data => {
            if (data.error) return;
            document.getElementById('activityLog').innerHTML = data.logsHtml;
        })
        .catch(() => {});
}
setInterval(refreshDashboard, 30000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
