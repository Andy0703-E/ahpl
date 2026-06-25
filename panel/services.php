<?php
$pageTitle = 'Services';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:22px;">
    <div class="card" id="nginxCard">
        <div class="card-header"><h3><i class="fas fa-server"></i> Nginx</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="nginxStatus" class="service-status" style="font-size:48px;margin:14px 0;color:#aaa;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:#888;margin-bottom:16px;font-size:13px;">Web Server</p>
            <div style="display:flex;gap:8px;justify-content:center;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('nginx','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('nginx','stop')"><i class="fas fa-stop"></i> Stop</button>
                <button class="btn btn-sm btn-info" onclick="serviceAction('nginx','restart')"><i class="fas fa-sync"></i> Restart</button>
            </div>
        </div>
    </div>

    <div class="card" id="phpCard">
        <div class="card-header"><h3><i class="fas fa-code"></i> PHP-FPM</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="phpStatus" class="service-status" style="font-size:48px;margin:14px 0;color:#aaa;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:#888;margin-bottom:16px;font-size:13px;">PHP Processor</p>
            <div style="display:flex;gap:8px;justify-content:center;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('php-fpm','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('php-fpm','stop')"><i class="fas fa-stop"></i> Stop</button>
                <button class="btn btn-sm btn-info" onclick="serviceAction('php-fpm','restart')"><i class="fas fa-sync"></i> Restart</button>
            </div>
        </div>
    </div>

    <div class="card" id="tunnelCard">
        <div class="card-header"><h3><i class="fas fa-shield-alt"></i> Cloudflare Tunnel</h3></div>
        <div class="card-body" style="text-align:center;">
            <div id="tunnelStatus" class="service-status" style="font-size:48px;margin:14px 0;color:#aaa;">
                <i class="fas fa-circle"></i>
            </div>
            <p style="color:#888;margin-bottom:16px;font-size:13px;">Tunnel Internet</p>
            <div style="display:flex;gap:8px;justify-content:center;">
                <button class="btn btn-sm btn-success" onclick="serviceAction('cloudflared','start')"><i class="fas fa-play"></i> Start</button>
                <button class="btn btn-sm btn-danger" onclick="serviceAction('cloudflared','stop')"><i class="fas fa-stop"></i> Stop</button>
            </div>
            <div style="margin-top:12px;">
                <input type="text" class="form-control" id="tunnelUrl" placeholder="http://localhost:8080" style="font-size:12px;text-align:center;">
                <button class="btn btn-sm btn-outline" style="margin-top:6px;width:100%;" onclick="saveTunnelUrl()"><i class="fas fa-save"></i> Simpan URL</button>
            </div>
        </div>
    </div>
</div>

<script>
async function checkAllServices() {
    try {
        const res = await AHPL.api('/panel/api/services.php?action=status');
        if (res.services) {
            ['nginx','php-fpm','cloudflared'].forEach(s => {
                const el = document.getElementById(s.replace('-','') + 'Status');
                const status = res.services[s] === 'running';
                el.style.color = status ? 'var(--success)' : '#ccc';
                el.title = status ? 'Running' : 'Stopped';
            });
        }
    } catch(e) { /* ignore */ }
}

async function serviceAction(service, action) {
    try {
        const res = await AHPL.api('/panel/api/services.php', {
            method: 'POST',
            body: JSON.stringify({ service, action })
        });
        if (res.success) {
            AHPL.toast(service + ' ' + action + ' berhasil!');
            checkAllServices();
        } else {
            AHPL.toast(res.error || 'Gagal', 'error');
        }
    } catch(e) {
        AHPL.toast(e.message || 'Error', 'error');
    }
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
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
