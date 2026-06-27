<?php
$pdo = getDB();
$suppliers = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count FROM suppliers s ORDER BY s.name")->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <div style="margin-bottom:16px;text-align:right;">
            <button class="btn btn-success" onclick="openSupplierModal()">+ Tambah Supplier</button>
        </div>
        <table class="table">
            <thead><tr><th>Nama</th><th>Telepon</th><th>Email</th><th>Alamat</th><th>Produk</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><strong><?= escape($s['name']) ?></strong></td>
                    <td><?= escape($s['phone'] ?: '-') ?></td>
                    <td><?= escape($s['email'] ?: '-') ?></td>
                    <td><?= escape(mb_substr($s['address'] ?? '', 0, 50)) ?></td>
                    <td><?= (int)$s['product_count'] ?></td>
                    <td class="table-actions">
                        <button class="btn btn-sm btn-info" onclick="editSupplier(<?= $s['id'] ?>)">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteSupplier(<?= $s['id'] ?>)">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--gray);">Belum ada supplier</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="supplierModal">
    <div class="modal">
        <div class="modal-header"><h3 id="supModalTitle">Tambah Supplier</h3><button class="modal-close" onclick="document.getElementById('supplierModal').classList.remove('active')">&times;</button></div>
        <form onsubmit="return saveSupplier(event)">
        <div class="modal-body">
            <input type="hidden" name="id" id="supId">
            <div class="form-group"><label>Nama *</label><input type="text" name="name" id="supName" required></div>
            <div class="form-row">
                <div class="form-group"><label>Telepon</label><input type="text" name="phone" id="supPhone"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="supEmail"></div>
            </div>
            <div class="form-group"><label>Alamat</label><textarea name="address" id="supAddress"></textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('supplierModal').classList.remove('active')">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        </form>
    </div>
</div>

<script>
function openSupplierModal(data) {
    document.getElementById('supId').value = data ? data.id : '';
    document.getElementById('supName').value = data ? data.name : '';
    document.getElementById('supPhone').value = data ? data.phone : '';
    document.getElementById('supEmail').value = data ? data.email : '';
    document.getElementById('supAddress').value = data ? data.address : '';
    document.getElementById('supModalTitle').textContent = data ? 'Edit Supplier' : 'Tambah Supplier';
    document.getElementById('supplierModal').classList.add('active');
}
async function saveSupplier(e) {
    e.preventDefault();
    var r = await api('api/products.php', {
        action: 'save_supplier',
        id: document.getElementById('supId').value,
        name: document.getElementById('supName').value,
        phone: document.getElementById('supPhone').value,
        email: document.getElementById('supEmail').value,
        address: document.getElementById('supAddress').value
    });
    if (r.success) { showToast('Supplier disimpan!'); document.getElementById('supplierModal').classList.remove('active'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
async function editSupplier(id) {
    var r = await api('api/products.php?action=get_supplier&id=' + id);
    if (r.supplier) openSupplierModal(r.supplier);
}
async function deleteSupplier(id) {
    if (!confirmDelete('Yakin hapus supplier?')) return;
    var r = await api('api/products.php', { action: 'delete_supplier', id: id });
    if (r.success) { showToast('Supplier dihapus!'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
</script>
