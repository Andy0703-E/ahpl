<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$cerebrasKey = getCerebrasKey();
$db->close();
?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-robot"></i> AI Settings (Z-AI GLM-4.7 via Cerebras)</h3></div>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
