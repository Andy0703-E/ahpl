<?php
$pdo = getDB();
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['store_name','store_phone','store_address','receipt_header','receipt_footer','tax','currency'];
    foreach ($keys as $k) {
        $val = $_POST[$k] ?? '';
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE value = :v2");
        $stmt->execute([':k' => $k, ':v' => $val, ':v2' => $val]);
    }
    $saved = true;
}

$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($r = $stmt->fetch()) $settings[$r['key_name']] = $r['value'];

// User management
$users = $pdo->query("SELECT id, name, username, role, status FROM users ORDER BY name")->fetchAll();
?>
<?php if ($saved): ?><div class="alert alert-success">Pengaturan disimpan!</div><?php endif; ?>

<div class="card">
    <div class="card-header">Pengaturan Toko</div>
    <div class="card-body">
        <form method="post" style="max-width:600px;">
            <div class="form-group"><label>Nama Toko</label><input type="text" name="store_name" value="<?= escape($settings['store_name'] ?? '') ?>"></div>
            <div class="form-row">
                <div class="form-group"><label>Telepon</label><input type="text" name="store_phone" value="<?= escape($settings['store_phone'] ?? '') ?>"></div>
                <div class="form-group"><label>Mata Uang</label>
                    <select name="currency">
                        <option value="IDR" <?= ($settings['currency'] ?? 'IDR') === 'IDR' ? 'selected' : '' ?>>IDR (Rp)</option>
                        <option value="USD" <?= ($settings['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Alamat</label><textarea name="store_address"><?= escape($settings['store_address'] ?? '') ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Header Struk</label><input type="text" name="receipt_header" value="<?= escape($settings['receipt_header'] ?? '') ?>"></div>
                <div class="form-group"><label>Footer Struk</label><input type="text" name="receipt_footer" value="<?= escape($settings['receipt_footer'] ?? 'Terima Kasih') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Pajak (%)</label><input type="number" name="tax" step="0.1" value="<?= escape($settings['tax'] ?? '0') ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">Manajemen User</div>
    <div class="card-body">
        <div style="margin-bottom:16px;text-align:right;">
            <button class="btn btn-success" onclick="openUserModal()">+ Tambah User</button>
        </div>
        <table class="table">
            <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= escape($u['name']) ?></strong></td>
                    <td><?= escape($u['username']) ?></td>
                    <td><span class="badge badge-info"><?= escape($u['role']) ?></span></td>
                    <td><span class="badge badge-<?= $u['status'] ? 'success' : 'danger' ?>"><?= $u['status'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="resetPassword(<?= $u['id'] ?>)">Reset Password</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="userModal">
    <div class="modal">
        <div class="modal-header"><h3>Tambah User</h3><button class="modal-close" onclick="document.getElementById('userModal').classList.remove('active')">&times;</button></div>
        <form onsubmit="return saveUser(event)">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Nama *</label><input type="text" name="name" id="userName" required></div>
                <div class="form-group"><label>Username *</label><input type="text" name="username" id="userUsername" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Password *</label><input type="password" name="password" id="userPassword" required></div>
                <div class="form-group"><label>Role</label>
                    <select name="role" id="userRole">
                        <option value="kasir">Kasir</option>
                        <option value="admin">Admin</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('userModal').classList.remove('active')">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        </form>
    </div>
</div>

<script>
async function saveUser(e) {
    e.preventDefault();
    var r = await api('api/settings.php', {
        action: 'save_user',
        name: document.getElementById('userName').value,
        username: document.getElementById('userUsername').value,
        password: document.getElementById('userPassword').value,
        role: document.getElementById('userRole').value
    });
    if (r.success) { showToast('User ditambahkan!'); document.getElementById('userModal').classList.remove('active'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
function openUserModal() { document.getElementById('userModal').classList.add('active'); }
async function resetPassword(id) {
    var pw = prompt('Password baru:');
    if (!pw) return;
    var r = await api('api/settings.php', { action: 'reset_password', id: id, password: pw });
    if (r.success) showToast('Password direset!');
    else showToast('Error: ' + r.error, 'error');
}
</script>
