<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$server = getServerInfo();
$db = getDB();
$websiteCount = $db->querySingle("SELECT COUNT(*) FROM websites");
$fileCount = countFiles(WEBSITES_PATH);
$recentLogs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 10");

$diskPct = $server['disk_total'] > 0 ? round(($server['disk_used'] / $server['disk_total']) * 100) : 0;
$memPct = $server['mem_total'] > 0 ? round(($server['mem_used'] / $server['mem_total']) * 100) : 0;

$websites = $db->query("SELECT name, folder FROM websites ORDER BY id DESC");
$allStorage = [];
$ws = $db->query("SELECT folder FROM websites ORDER BY id DESC");
while ($row = $ws->fetchArray(SQLITE3_ASSOC)) {
    $size = dirSize(WEBSITES_PATH . '/' . $row['folder']);
    $allStorage[$row['folder']] = $size;
}
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h4><?= getSetting('server_uptime') ?: 'Online' ?></h4>
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
                <tr>
                    <td>Storage</td>
                    <td>
                        <?= formatSize($server['disk_used']) ?> / <?= formatSize($server['disk_total']) ?>
                        <div class="progress-bar" style="margin-top:5px;">
                            <div class="progress-fill" style="width:<?= $diskPct ?>%;background:<?= $diskPct > 80 ? 'var(--danger)' : ($diskPct > 50 ? 'var(--warning)' : 'var(--primary)') ?>"></div>
                        </div>
                        <span style="font-size:11px;color:var(--text-muted);"><?= $diskPct ?>% used</span>
                    </td>
                </tr>
                <tr>
                    <td>RAM</td>
                    <td>
                        <?= formatSize($server['mem_used']) ?> / <?= formatSize($server['mem_total']) ?>
                        <div class="progress-bar" style="margin-top:5px;">
                            <div class="progress-fill" style="width:<?= $memPct ?>%;background:<?= $memPct > 80 ? 'var(--danger)' : ($memPct > 50 ? 'var(--warning)' : 'var(--success)') ?>"></div>
                        </div>
                        <span style="font-size:11px;color:var(--text-muted);"><?= $memPct ?>% used</span>
                    </td>
                </tr>
            </table>

            <?php if (!empty($allStorage)): ?>
            <h4 style="margin:16px 0 10px;font-size:13px;font-weight:700;"><i class="fas fa-database"></i> Storage per Website</h4>
            <table class="table">
                <?php foreach ($allStorage as $folder => $size): ?>
                <tr>
                    <td><?= sanitize($folder) ?></td>
                    <td style="text-align:right;font-weight:600;"><?= formatSize($size) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-history"></i> Activity Log</h3></div>
        <div class="card-body" id="activityLog" style="max-height:360px;overflow-y:auto;padding:0;">
            <?php while ($log = $recentLogs->fetchArray(SQLITE3_ASSOC)): ?>
                <div style="padding:10px 20px;border-bottom:1px solid var(--border);font-size:13px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;">
                        <span class="badge badge-info"><?= sanitize($log['action']) ?></span>
                        <span style="color:var(--text);font-weight:500;"><?= sanitize(substr($log['details'] ?? '', 0, 60)) ?></span>
                    </div>
                    <div style="color:var(--text-muted);font-size:11px;">
                        <i class="fas fa-clock"></i> <?= date('d M H:i', strtotime($log['created_at'])) ?>
                        <?php if (!empty($log['ip_address'])): ?>
                            <span style="margin-left:10px;"><i class="fas fa-network-wired"></i> <?= sanitize($log['ip_address']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px;">
    <div class="card-header">
        <h3><i class="fas fa-globe"></i> Website Status</h3>
        <a href="/panel/websites.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Manage</a>
    </div>
    <div class="card-body" style="padding:0;">
        <?php $hasAny = false; while ($site = $websites->fetchArray(SQLITE3_ASSOC)): $hasAny = true; ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:16px;">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div>
                        <strong style="font-size:13px;"><?= sanitize($site['name']) ?></strong>
                        <code style="font-size:11px;margin-left:6px;color:var(--text-muted);">/<?= sanitize($site['folder']) ?></code>
                    </div>
                </div>
                <div>
                    <?php $status = websiteStatus($site['folder']); ?>
                    <span class="badge <?= $status === 'online' ? 'badge-success' : 'badge-warning' ?>">
                        <i class="fas fa-circle" style="font-size:8px;margin-right:4px;"></i> <?= ucfirst($status) ?>
                    </span>
                </div>
            </div>
        <?php endwhile; if (!$hasAny): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-globe"></i></div>
                <h3>Belum Ada Website</h3>
                <p>Buat website pertama Anda untuk memulai.</p>
                <a href="/panel/websites.php" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-plus"></i> Buat Website</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.__CSRF_TOKEN__ = '<?= $csrfToken ?>';
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
