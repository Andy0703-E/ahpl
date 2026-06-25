<?php
$pageTitle = 'Services';
require_once __DIR__ . '/includes/header.php';

$svcNginx = checkServiceStatus('nginx');
$svcPhp = checkServiceStatus('php-fpm');
$svcTunnel = checkServiceStatus('cloudflared');
$svcTunnelUrl = getSetting(SETTING_CLOUDFLARE_URL);
?>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:22px;">
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

    <div class="card" id="tunnelCard">
        <div class="card-header"><h3><i class="fas fa-shield-alt"></i> Cloudflare Tunnel</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="tunnelStatus" class="service-status <?= $svcTunnel ?>" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">Tunnel Internet</p>
            <p id="tunnelLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:<?= $svcTunnel === 'running' ? 'var(--success)' : 'var(--text-muted)' ?>;margin-bottom:18px;"><?= ucfirst($svcTunnel) ?></p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('cloudflared','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('cloudflared','stop')"><i class="fas fa-stop"></i> Stop</button>
            </div>
            <div style="margin-top:14px;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;display:block;">Tunnel URL</label>
                <input type="text" class="form-control" id="tunnelUrl" readonly value="<?= sanitize($svcTunnelUrl ?? '') ?>" style="font-size:12px;cursor:pointer;" onclick="this.select()" placeholder="Start tunnel untuk mendapat URL">
                <div id="tunnelQRWrap" style="margin-top:8px;text-align:center;"></div>
            </div>
        </div>
    </div>
</div>

<script>
var SERVICE_IDS = { nginx: 'nginx', 'php-fpm': 'php', cloudflared: 'tunnel' };

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
    ['nginx','php-fpm','cloudflared'].forEach(function(s) {
        updateServiceStatus(s, services[s] === 'running');
    });
}

async function checkAllServices() {
    try {
        var res = await fetch('/panel/api/services.php?action=status');
        if (!res.ok) { updateAllStatuses({ nginx: false, 'php-fpm': false, cloudflared: false }); return; }
        var data = await res.json();
        if (data.services) updateAllStatuses(data.services);
    } catch(e) {
        updateAllStatuses({ nginx: false, 'php-fpm': false, cloudflared: false });
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
            if (service === 'cloudflared' && action === 'start') {
                setTimeout(fetchTunnelUrl, 3000);
            }
            checkAllServices();
        } else {
            alert('Gagal: ' + (data.error || 'unknown'));
        }
    } catch(e) {
        alert('Error: ' + (e.message || 'unknown'));
    }
}

async function fetchTunnelUrl() {
    try {
        var res = await fetch('/panel/api/tunnel-url.php');
        if (!res.ok) return;
        var data = await res.json();
        if (data.url) {
            document.getElementById('tunnelUrl').value = data.url;
            var h = { 'Content-Type': 'application/json' };
            if (window.__CSRF_TOKEN__) h['X-CSRF-TOKEN'] = window.__CSRF_TOKEN__;
            await fetch('/panel/api/settings.php', {
                method: 'POST', headers: h,
                body: JSON.stringify({ action: 'save_tunnel_url', url: data.url })
            });
            updateTunnelQR(data.url);
        }
    } catch(e) {}
}

function updateTunnelQR(url) {
    var wrap = document.getElementById('tunnelQRWrap');
    if (!wrap) return;
    wrap.innerHTML = url
        ? '<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(url) + '" alt="QR" style="border-radius:6px;max-width:150px;">'
        : '';
}

document.addEventListener('DOMContentLoaded', function() {
    checkAllServices();
    setInterval(checkAllServices, 10000);
    var tu = document.getElementById('tunnelUrl');
    if (tu && tu.value) updateTunnelQR(tu.value);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
