<?php
$pdo = getDB();
$units = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM products WHERE unit_id = u.id) as product_count FROM units u ORDER BY u.name")->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <div style="margin-bottom:16px;text-align:right;">
            <button class="btn btn-success" onclick="openUnitModal()">+ Tambah Satuan</button>
        </div>
        <table class="table">
            <thead><tr><th>Nama</th><th>Singkatan</th><th>Produk</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($units as $u): ?>
                <tr>
                    <td><strong><?= escape($u['name']) ?></strong></td>
                    <td><?= escape($u['short_name']) ?></td>
                    <td><?= (int)$u['product_count'] ?></td>
                    <td class="table-actions">
                        <button class="btn btn-sm btn-info" onclick="editUnit(<?= $u['id'] ?>)">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUnit(<?= $u['id'] ?>)">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($units)): ?>
                <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--gray);">Belum ada satuan</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="unitModal">
    <div class="modal">
        <div class="modal-header"><h3 id="unitModalTitle">Tambah Satuan</h3><button class="modal-close" onclick="document.getElementById('unitModal').classList.remove('active')">&times;</button></div>
        <form onsubmit="return saveUnit(event)">
        <div class="modal-body">
            <input type="hidden" name="id" id="unitId">
            <div class="form-group"><label>Nama *</label><input type="text" name="name" id="unitName" required></div>
            <div class="form-group"><label>Singkatan *</label><input type="text" name="short_name" id="unitShort" required></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('unitModal').classList.remove('active')">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        </form>
    </div>
</div>

<script>
function openUnitModal(data) {
    document.getElementById('unitId').value = data ? data.id : '';
    document.getElementById('unitName').value = data ? data.name : '';
    document.getElementById('unitShort').value = data ? data.short_name : '';
    document.getElementById('unitModalTitle').textContent = data ? 'Edit Satuan' : 'Tambah Satuan';
    document.getElementById('unitModal').classList.add('active');
}
async function saveUnit(e) {
    e.preventDefault();
    var r = await api('api/products.php', {
        action: 'save_unit',
        id: document.getElementById('unitId').value,
        name: document.getElementById('unitName').value,
        short_name: document.getElementById('unitShort').value
    });
    if (r.success) { showToast('Satuan disimpan!'); document.getElementById('unitModal').classList.remove('active'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
async function editUnit(id) {
    var r = await api('api/products.php?action=get_unit&id=' + id);
    if (r.unit) openUnitModal(r.unit);
}
async function deleteUnit(id) {
    if (!confirmDelete('Yakin hapus satuan?')) return;
    var r = await api('api/products.php', { action: 'delete_unit', id: id });
    if (r.success) { showToast('Satuan dihapus!'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
</script>
