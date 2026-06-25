<?php
$pageTitle = 'Services';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:22px;">
    <div class="card" id="nginxCard">
        <div class="card-header"><h3><i class="fas fa-server"></i> Nginx</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="nginxStatus" class="service-status" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">Web Server</p>
            <p id="nginxLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:18px;">Checking...</p>
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
            <div id="phpStatus" class="service-status" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">PHP Processor</p>
            <p id="phpLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:18px;">Checking...</p>
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
            <div id="tunnelStatus" class="service-status" style="font-size:56px;margin:18px 0;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:var(--text-muted);margin-bottom:6px;font-size:13px;font-weight:500;">Tunnel Internet</p>
            <p id="tunnelLabel" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:18px;">Checking...</p>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('cloudflared','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('cloudflared','stop')"><i class="fas fa-stop"></i> Stop</button>
            </div>
            <div style="margin-top:14px;text-align:left;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;display:block;">Local URL</label>
                <input type="text" class="form-control" id="tunnelUrl" placeholder="http://localhost:8080" style="font-size:12px;">
                <button class="btn btn-sm btn-outline" style="margin-top:6px;width:100%;" onclick="saveTunnelUrl()"><i class="fas fa-save"></i> Simpan URL</button>
            </div>
        </div>
    </div>
</div>

<script>
function updateServiceStatus(s, running) {
    const id = s.replace('-', '');
    const el = document.getElementById(id + 'Status');
    const label = document.getElementById(id + 'Label');
    if (!el || !label) return;
    el.className = 'service-status ' + (running ? 'running' : 'stopped');
    el.title = running ? 'Running' : 'Stopped';
    label.textContent = running ? 'Running' : 'Stopped';
    label.style.color = running ? 'var(--success)' : 'var(--text-muted)';
}

function updateAllStatuses(services) {
    ['nginx','php-fpm','cloudflared'].forEach(s => {
        updateServiceStatus(s, services[s] === 'running');
    });
}

async function checkAllServices() {
    try {
        const res = await fetch('/panel/api/services.php?action=status');
        if (!res.ok) { updateAllStatuses({ nginx: false, 'php-fpm': false, cloudflared: false }); return; }
        const data = await res.json();
        if (data.services) updateAllStatuses(data.services);
    } catch(e) {
        updateAllStatuses({ nginx: false, 'php-fpm': false, cloudflared: false });
    }
}

async function serviceAction(service, action) {
    try {
        const res = await AHPL.api('/panel/api/services.php', {
            method: 'POST',
            body: JSON.stringify({ service, action })
        });
        if (res.success) {
            AHPL.toast(service + ' ' + action + ' berhasil!');
            if (service === 'cloudflared' && action === 'start') {
                setTimeout(fetchTunnelUrl, 3000);
            }
            checkAllServices();
        } else {
            AHPL.toast(res.error || 'Gagal', 'error');
        }
    } catch(e) {
        AHPL.toast(e.message || 'Error', 'error');
    }
}

async function fetchTunnelUrl() {
    try {
        const res = await fetch('/panel/api/tunnel-url.php');
        if (!res.ok) return;
        const data = await res.json();
        if (data.url) {
            document.getElementById('tunnelUrl').value = data.url;
            await fetch('/panel/api/settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
                body: JSON.stringify({ action: 'save_tunnel_url', url: data.url })
            });
            AHPL.toast('URL tunnel otomatis tersimpan!');
        }
    } catch(e) { /* silent */ }
}

async function saveTunnelUrl() {
    const url = document.getElementById('tunnelUrl').value.trim();
    if (!url) return AHPL.toast('Masukkan URL', 'error');
    const res = await AHPL.api('/panel/api/settings.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'save_tunnel_url', url })
    });
    if (res.success) AHPL.toast('URL tunnel disimpan!');
}

document.addEventListener('DOMContentLoaded', function() {
    checkAllServices();
    setInterval(checkAllServices, 10000);

    // Load saved tunnel URL
    fetch('/panel/api/settings.php?action=get_tunnel_url')
        .then(r => r.json())
        .then(d => { if (d.url) document.getElementById('tunnelUrl').value = d.url; })
        .catch(() => {});
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
