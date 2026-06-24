<?php
$pageTitle = 'Cloudflare Tunnel';
require_once __DIR__ . '/includes/header.php';

$status = @file_get_contents(SERVER_PATH . '/tunnel.status') ?: 'stopped';
$domain = @file_get_contents(SERVER_PATH . '/tunnel.domain') ?: '';
$pid = @file_get_contents(SERVER_PATH . '/tunnel.pid') ?: '';

$db = getDB();
$websites = $db->query("SELECT * FROM websites ORDER BY id DESC");
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon <?= $status === 'running' ? 'green' : 'red' ?>"><i class="fas fa-cloud"></i></div>
        <div class="stat-info"><h4><?= ucfirst($status) ?></h4><p>Tunnel Status</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-globe"></i></div>
        <div class="stat-info"><h4 style="font-size:13px;"><?= $domain ?: 'Not set' ?></h4><p>Domain</p></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-cog"></i> Controls</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
            <button class="btn btn-success" onclick="tunnelAction('start')"><i class="fas fa-play"></i> Start</button>
            <button class="btn btn-danger" onclick="tunnelAction('stop')"><i class="fas fa-stop"></i> Stop</button>
            <button class="btn btn-info" onclick="genDomain()"><i class="fas fa-random"></i> Random Domain</button>
        </div>
        <div class="form-group">
            <label>Domain</label>
            <div style="display:flex;gap:8px;">
                <input type="text" class="form-control" id="tunnelDomain" placeholder="abc123.trycloudflare.com" value="<?= sanitize($domain) ?>" style="flex:1;">
                <button class="btn btn-primary" onclick="setDomain()">Set</button>
            </div>
        </div>
        <?php if ($domain && $status === 'running'): ?>
            <div style="background:#f8f9fa;padding:14px;border-radius:8px;margin-top:14px;">
                <strong>URL:</strong>
                <a href="http://<?= sanitize($domain) ?>" target="_blank" style="color:var(--primary);margin-left:6px;">http://<?= sanitize($domain) ?> <i class="fas fa-external-link-alt" style="font-size:10px;"></i></a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-list"></i> Websites</h3></div>
    <div class="card-body">
        <?php while ($site = $websites->fetchArray(SQLITE3_ASSOC)): ?>
            <div style="padding:8px 0;border-bottom:1px solid #f5f5f5;font-size:13px;">
                <strong><?= sanitize($site['name']) ?></strong>
                <span style="color:#888;margin-left:8px;">/websites/<?= sanitize($site['folder']) ?></span>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
async function tunnelAction(action) {
    const res = await AHPL.api('/panel/api/tunnel.php', {
        method: 'POST',
        body: JSON.stringify({ action })
    });
    if (res.success) { AHPL.toast(res.message); setTimeout(() => location.reload(), 800); }
}

function genDomain() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let d = '';
    for (let i = 0; i < 12; i++) d += chars[Math.floor(Math.random() * chars.length)];
    d += '.trycloudflare.com';
    document.getElementById('tunnelDomain').value = d;
    AHPL.toast('Domain generated', 'info');
}

async function setDomain() {
    const domain = document.getElementById('tunnelDomain').value.trim();
    if (!domain) return AHPL.toast('Masukkan domain', 'error');
    const res = await AHPL.api('/panel/api/tunnel.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'set_domain', domain })
    });
    if (res.success) { AHPL.toast('Domain disimpan'); setTimeout(() => location.reload(), 500); }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
