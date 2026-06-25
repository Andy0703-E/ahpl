<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';

$cerebrasKey = getCerebrasKey();
$tunnelUrl = getSetting(SETTING_CLOUDFLARE_URL);
$loginHistory = getLoginHistory(10);
?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-robot"></i> AI Settings</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>Cerebras API Key</label>
            <input type="password" class="form-control" id="apiKey" placeholder="Masukkan API Key" value="<?= sanitize($cerebrasKey ?? '') ?>">
            <small style="color:#888;">Dapatkan API key di <a href="https://console.cerebras.ai" target="_blank">console.cerebras.ai</a></small>
        </div>
        <button class="btn btn-primary" onclick="saveKey()"><i class="fas fa-save"></i> Simpan</button>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-key"></i> Ganti Password</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>Password Baru</label>
            <input type="password" class="form-control" id="newPassword" placeholder="Minimal 6 karakter">
        </div>
        <div class="form-group">
            <label>Konfirmasi Password</label>
            <input type="password" class="form-control" id="confirmPassword" placeholder="Ulangi password">
        </div>
        <button class="btn btn-primary" onclick="changePassword()"><i class="fas fa-save"></i> Ganti Password</button>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-shield-alt"></i> Cloudflare Tunnel</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>URL Tunnel</label>
            <input type="text" class="form-control" id="tunnelUrl" placeholder="https://abc.trycloudflare.com" value="<?= sanitize($tunnelUrl ?? '') ?>">
        </div>
        <button class="btn btn-primary" onclick="saveTunnelUrl()"><i class="fas fa-save"></i> Simpan</button>
        <button class="btn btn-outline" onclick="showTunnelQR()" style="margin-left:8px;"><i class="fas fa-qrcode"></i> QR Code</button>
        <div id="tunnelQrContainer" style="display:none;margin-top:14px;text-align:center;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-history"></i> Login History</h3></div>
    <div class="card-body" style="max-height:300px;overflow-y:auto;">
        <?php if (empty($loginHistory)): ?>
            <p style="color:#888;font-size:13px;">Belum ada riwayat login</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Waktu</th><th>Detail</th><th>IP</th></tr></thead>
                <tbody>
                    <?php foreach ($loginHistory as $log): ?>
                        <tr>
                            <td style="font-size:12px;"><?= date('d M H:i', strtotime($log['created_at'])) ?></td>
                            <td style="font-size:12px;"><?= sanitize($log['details']) ?></td>
                            <td style="font-size:12px;font-family:monospace;"><?= sanitize($log['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-cloud-upload-alt"></i> GitHub Deploy</h3></div>
    <div class="card-body">
        <p style="font-size:13px;color:#666;margin-bottom:12px;">Clone repository GitHub langsung ke folder website.</p>
        <div class="form-group">
            <label>GitHub Repository URL</label>
            <input type="text" class="form-control" id="ghRepo" placeholder="https://github.com/user/repo">
        </div>
        <div class="form-group">
            <label>Folder Website</label>
            <input type="text" class="form-control" id="ghFolder" placeholder="portfolio">
        </div>
        <button class="btn btn-primary" onclick="deployGithub()"><i class="fab fa-github"></i> Deploy</button>
        <div id="ghStatus" style="margin-top:10px;font-size:13px;color:#888;display:none;">
            <i class="fas fa-spinner fa-spin"></i> Cloning repository...
        </div>
        <div id="ghResult" style="margin-top:10px;display:none;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-archive"></i> Backup Manager</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <button class="btn btn-sm btn-primary" onclick="createBackup('websites')"><i class="fas fa-globe"></i> Backup Websites</button>
            <button class="btn btn-sm btn-info" onclick="createBackup('database')"><i class="fas fa-database"></i> Backup Database</button>
            <button class="btn btn-sm btn-success" onclick="createBackup('full')"><i class="fas fa-archive"></i> Full Backup</button>
        </div>
        <div id="backupList">
            <p style="color:#888;font-size:13px;">Memuat daftar backup...</p>
        </div>
    </div>
</div>

<script>
async function saveKey() {
    const key = document.getElementById('apiKey').value.trim();
    if (!key) return AHPL.toast('Masukkan API Key', 'error');
    const res = await AHPL.api('/panel/api/settings.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'save_key', key })
    });
    if (res.success) AHPL.toast('API Key disimpan!');
}

async function changePassword() {
    const pw = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    if (pw.length < 6) return AHPL.toast('Password minimal 6 karakter', 'error');
    if (pw !== confirm) return AHPL.toast('Password tidak cocok', 'error');
    const res = await AHPL.api('/panel/api/settings.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'change_password', password: pw })
    });
    if (res.success) {
        AHPL.toast('Password berhasil diganti!');
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
    }
}

async function saveTunnelUrl() {
    const url = document.getElementById('tunnelUrl').value.trim();
    if (!url) return AHPL.toast('Masukkan URL', 'error');
    const res = await AHPL.api('/panel/api/settings.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'save_tunnel_url', url })
    });
    if (res.success) AHPL.toast('URL tunnel disimpan!');
}

function showTunnelQR() {
    const url = document.getElementById('tunnelUrl').value.trim();
    if (!url) return AHPL.toast('Simpan URL tunnel dulu', 'error');
    const container = document.getElementById('tunnelQrContainer');
    if (container.style.display !== 'none') { container.style.display = 'none'; return; }
    container.style.display = 'block';
    container.innerHTML = '<div id="qrcode"></div><p style="font-size:12px;color:#888;margin-top:8px;">Scan untuk buka di perangkat lain</p>';
    // Simple QR generation using API
    const img = document.createElement('img');
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);
    img.style.borderRadius = '8px';
    img.alt = 'QR Code';
    container.prepend(img);
}

async function deployGithub() {
    const repo = document.getElementById('ghRepo').value.trim();
    const folder = document.getElementById('ghFolder').value.trim();
    if (!repo || !folder) return AHPL.toast('Repo dan folder wajib diisi', 'error');

    const statusEl = document.getElementById('ghStatus');
    const resultEl = document.getElementById('ghResult');
    statusEl.style.display = 'block';
    resultEl.style.display = 'none';

    try {
        const res = await AHPL.api('/panel/api/deploy.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
            body: JSON.stringify({ action: 'github', repo, folder })
        });
        if (res.success) {
            resultEl.innerHTML = '<div style="padding:10px;background:#d4edda;color:#155724;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle"></i> Deploy berhasil! ' + res.deploy.copied + ' file di-copy.</div>';
        }
    } catch(e) {
        resultEl.innerHTML = '<div style="padding:10px;background:#f8d7da;color:#721c24;border-radius:8px;font-size:13px;">' + AHPL.escapeHtml(e.message || 'Gagal') + '</div>';
    }
    statusEl.style.display = 'none';
    resultEl.style.display = 'block';
}

async function createBackup(type) {
    const res = await AHPL.api('/panel/api/backup.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window.__CSRF_TOKEN__ },
        body: JSON.stringify({ action: 'create', type })
    });
    if (res.success) {
        AHPL.toast('Backup ' + type + ' berhasil!');
        loadBackups();
    } else {
        AHPL.toast(res.error || 'Gagal backup', 'error');
    }
}

async function loadBackups() {
    try {
        const res = await AHPL.api('/panel/api/backup.php', { method: 'GET' });
        const list = document.getElementById('backupList');
        if (!res.backups || res.backups.length === 0) {
            list.innerHTML = '<p style="color:#888;font-size:13px;">Belum ada backup</p>';
            return;
        }
        list.innerHTML = '<table class="table"><thead><tr><th>File</th><th>Size</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>' +
            res.backups.map(b => '<tr><td style="font-size:12px;">' + AHPL.escapeHtml(b.name) + '</td><td style="font-size:12px;">' + AHPL.formatSize(b.size) + '</td><td style="font-size:12px;">' + b.date + '</td><td><button class="btn btn-sm btn-outline" onclick="downloadBackup(\'' + b.name + '\')"><i class="fas fa-download"></i></button></td></tr>').join('') +
            '</tbody></table>';
    } catch(e) {
        document.getElementById('backupList').innerHTML = '<p style="color:var(--danger);font-size:13px;">Gagal memuat backup</p>';
    }
}

function downloadBackup(name) {
    window.location = '/panel/api/backup.php?action=download&file=' + encodeURIComponent(name);
}

document.addEventListener('DOMContentLoaded', loadBackups);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
