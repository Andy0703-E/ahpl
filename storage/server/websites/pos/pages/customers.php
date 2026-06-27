<?php
$pdo = getDB();
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if ($search) {
    $where = "WHERE name LIKE :s OR phone LIKE :s2 OR email LIKE :s3";
    $params[':s'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}
$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) as sale_count, (SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE customer_id = c.id) as total_spent FROM customers c $where ORDER BY c.name");
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <div class="search-box" style="margin-bottom:16px;">
            <form method="get" style="display:flex;gap:8px;width:100%;">
                <input type="hidden" name="page" value="customers">
                <input type="text" name="search" placeholder="Cari nama, telepon, email..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if ($search): ?><a href="?page=customers" class="btn btn-outline">Reset</a><?php endif; ?>
                <button type="button" class="btn btn-success" onclick="openCustomerModal()" style="margin-left:auto;">+ Tambah Pelanggan</button>
            </form>
        </div>
        <table class="table">
            <thead><tr><th>Nama</th><th>Telepon</th><th>Email</th><th>Alamat</th><th>Poin</th><th>Transaksi</th><th>Total Belanja</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= escape($c['name']) ?></strong></td>
                    <td><?= escape($c['phone'] ?: '-') ?></td>
                    <td><?= escape($c['email'] ?: '-') ?></td>
                    <td><?= escape(mb_substr($c['address'] ?? '', 0, 40)) ?></td>
                    <td><?= (int)$c['point'] ?></td>
                    <td><?= (int)$c['sale_count'] ?></td>
                    <td><?= money($c['total_spent']) ?></td>
                    <td class="table-actions">
                        <button class="btn btn-sm btn-info" onclick="editCustomer(<?= $c['id'] ?>)">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCustomer(<?= $c['id'] ?>)">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray);">Belum ada pelanggan</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="customerModal">
    <div class="modal">
        <div class="modal-header"><h3 id="custModalTitle">Tambah Pelanggan</h3><button class="modal-close" onclick="document.getElementById('customerModal').classList.remove('active')">&times;</button></div>
        <form onsubmit="return saveCustomer(event)">
        <div class="modal-body">
            <input type="hidden" name="id" id="custId">
            <div class="form-group"><label>Nama *</label><input type="text" name="name" id="custName" required></div>
            <div class="form-row">
                <div class="form-group"><label>Telepon</label><input type="text" name="phone" id="custPhone"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="custEmail"></div>
            </div>
            <div class="form-group"><label>Alamat</label><textarea name="address" id="custAddress"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Poin</label><input type="number" name="point" id="custPoint" value="0"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('customerModal').classList.remove('active')">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        </form>
    </div>
</div>

<script>
function openCustomerModal(data) {
    document.getElementById('custId').value = data ? data.id : '';
    document.getElementById('custName').value = data ? data.name : '';
    document.getElementById('custPhone').value = data ? data.phone : '';
    document.getElementById('custEmail').value = data ? data.email : '';
    document.getElementById('custAddress').value = data ? data.address : '';
    document.getElementById('custPoint').value = data ? data.point : 0;
    document.getElementById('custModalTitle').textContent = data ? 'Edit Pelanggan' : 'Tambah Pelanggan';
    document.getElementById('customerModal').classList.add('active');
}
async function saveCustomer(e) {
    e.preventDefault();
    var r = await api('api/products.php', {
        action: 'save_customer',
        id: document.getElementById('custId').value,
        name: document.getElementById('custName').value,
        phone: document.getElementById('custPhone').value,
        email: document.getElementById('custEmail').value,
        address: document.getElementById('custAddress').value,
        point: document.getElementById('custPoint').value
    });
    if (r.success) { showToast('Pelanggan disimpan!'); document.getElementById('customerModal').classList.remove('active'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
async function editCustomer(id) {
    var r = await api('api/products.php?action=get_customer&id=' + id);
    if (r.customer) openCustomerModal(r.customer);
}
async function deleteCustomer(id) {
    if (!confirmDelete('Yakin hapus pelanggan?')) return;
    var r = await api('api/products.php', { action: 'delete_customer', id: id });
    if (r.success) { showToast('Pelanggan dihapus!'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
</script>
