<?php
$pageTitle = 'Services';
require_once __DIR__ . '/includes/header.php';

$svcNginx = checkServiceStatus('nginx');
$svcPhp = checkServiceStatus('php-fpm');
$svcCf = checkServiceStatus('cloudflared');
$svcMaria = checkServiceStatus('mariadb');
?>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:20px;margin-bottom:22px;">
    <div class="card" id="nginxCard">
        <div class="card-header"><h3><i class="fas fa-server"></i> Nginx</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="nginxStatus" class="service-status <?= $svcNginx ?>" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">Web Server</p>
            <p id="nginxLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?= $svcNginx === 'running' ? 'var(--success)' : 'var(--text-muted)' ?>;margin-bottom:18px;"><?= ucfirst($svcNginx) ?></p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('nginx','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('nginx','stop')"><i class="fas fa-stop"></i> Stop</button>
                <button class="btn btn-sm btn-info" onclick="serviceAction('nginx','restart')"><i class="fas fa-sync"></i> Restart</button>
            </div>
        </div>
    </div>

    <div class="card" id="phpCard">
        <div class="card-header"><h3><i class="fas fa-code"></i> PHP-FPM</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="phpStatus" class="service-status <?= $svcPhp ?>" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">PHP Processor</p>
            <p id="phpLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?= $svcPhp === 'running' ? 'var(--success)' : 'var(--text-muted)' ?>;margin-bottom:18px;"><?= ucfirst($svcPhp) ?></p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('php-fpm','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('php-fpm','stop')"><i class="fas fa-stop"></i> Stop</button>
                <button class="btn btn-sm btn-info" onclick="serviceAction('php-fpm','restart')"><i class="fas fa-sync"></i> Restart</button>
            </div>
        </div>
    </div>

    <div class="card" id="cfCard">
        <div class="card-header"><h3><i class="fas fa-cloud"></i> Cloudflared</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="cfStatus" class="service-status <?= $svcCf ?>" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">Tunnel</p>
            <p id="cfLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?= $svcCf === 'running' ? 'var(--success)' : 'var(--text-muted)' ?>;margin-bottom:18px;"><?= ucfirst($svcCf) ?></p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('cloudflared','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('cloudflared','stop')"><i class="fas fa-stop"></i> Stop</button>
                <button class="btn btn-sm btn-info" onclick="serviceAction('cloudflared','restart')"><i class="fas fa-sync"></i> Restart</button>
            </div>
        </div>
    </div>

    <div class="card" id="mariaCard">
        <div class="card-header"><h3><i class="fas fa-database"></i> MariaDB</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="mariaStatus" class="service-status <?= $svcMaria ?>" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">Database Server</p>
            <p id="mariaLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?= $svcMaria === 'running' ? 'var(--success)' : 'var(--text-muted)' ?>;margin-bottom:18px;"><?= ucfirst($svcMaria) ?></p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('mariadb','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('mariadb','stop')"><i class="fas fa-stop"></i> Stop</button>
                <button class="btn btn-sm btn-info" onclick="serviceAction('mariadb','restart')"><i class="fas fa-sync"></i> Restart</button>
            </div>
        </div>
    </div>
</div>

<script>
var SERVICE_IDS = { nginx: 'nginx', 'php-fpm': 'php', cloudflared: 'cf', mariadb: 'maria' };

function updateServiceStatus(s, running) {
    var id = SERVICE_IDS[s];
    var el = document.getElementById(id + 'Status');
    var label = document.getElementById(id + 'Label');
    if (!el || !label) return;
    el.className = 'service-status ' + (running ? 'running' : 'stopped');
    el.title = running ? 'Running' : 'Stopped';
    label.textContent = running ? 'Running' : 'Stopped';
    label.style.color = running ? 'var(--success)' : 'var(--text-muted)';
}

function updateAllStatuses(services) {
    ['nginx','php-fpm','cloudflared','mariadb'].forEach(function(s) {
        updateServiceStatus(s, services[s] === 'running');
    });
}

async function checkAllServices() {
    try {
        var res = await fetch('/panel/api/services.php?action=status');
        if (!res.ok) { updateAllStatuses({ nginx: false, 'php-fpm': false, cloudflared: false, mariadb: false }); return; }
        var data = await res.json();
        if (data.services) {
            updateAllStatuses(data.services);
        }
    } catch(e) {
        updateAllStatuses({ nginx: false, 'php-fpm': false, cloudflared: false, mariadb: false });
    }
}

async function serviceAction(service, action) {
    try {
        var h = { 'Content-Type': 'application/json' };
        if (window.__CSRF_TOKEN__) h['X-CSRF-TOKEN'] = window.__CSRF_TOKEN__;
        var res = await fetch('/panel/api/services.php', {
            method: 'POST', headers: h,
            body: JSON.stringify({ service: service, action: action })
        });
        var data = await res.json();
        if (data.success) {
            checkAllServices();
        } else {
            alert('Gagal: ' + (data.error || 'unknown'));
        }
    } catch(e) {
        alert('Error: ' + (e.message || 'unknown'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    checkAllServices();
    setInterval(checkAllServices, 10000);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
