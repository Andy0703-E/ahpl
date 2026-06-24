<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$glmKey = $db->querySingle("SELECT value FROM settings WHERE key = 'glm_key'");
$db->close();
?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-robot"></i> AI Settings (GLM-4)</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>GLM-4 API Key (Zhipu AI)</label>
            <input type="password" class="form-control" id="apiKey" placeholder="Masukkan API Key" value="<?= sanitize($glmKey ?? '') ?>">
            <small style="color:#888;">Dapatkan API key di <a href="https://open.bigmodel.cn" target="_blank">open.bigmodel.cn</a> (Zhipu AI / Z-AI)</small>
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
