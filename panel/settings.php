<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$cerebrasKey = $db->querySingle("SELECT value FROM settings WHERE key = 'cerebras_key'");
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

<script>
async function saveKey() {
    const key = document.getElementById('apiKey').value.trim();
    if (!key) return AHPL.toast('Masukkan API Key', 'error');
    const res = await AHPL.api('/panel/api/settings.php', {
        method: 'POST',
        body: JSON.stringify({ action: 'save_key', key })
    });
    if (res.success) AHPL.toast('API Key disimpan!');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
