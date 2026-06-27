<?php
$pdo = getDB();
$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c ORDER BY c.name")->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <div style="margin-bottom:16px;text-align:right;">
            <button class="btn btn-success" onclick="openCategoryModal()">+ Tambah Kategori</button>
        </div>
        <table class="table">
            <thead><tr><th>Nama</th><th>Deskripsi</th><th>Jumlah Produk</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td><strong><?= escape($c['name']) ?></strong></td>
                    <td><?= escape($c['description'] ?: '-') ?></td>
                    <td><?= (int)$c['product_count'] ?></td>
                    <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                    <td class="table-actions">
                        <button class="btn btn-sm btn-info" onclick="editCategory(<?= $c['id'] ?>)">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?= $c['id'] ?>)">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gray);">Belum ada kategori</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <div class="modal-header"><h3 id="catModalTitle">Tambah Kategori</h3><button class="modal-close" onclick="document.getElementById('categoryModal').classList.remove('active')">&times;</button></div>
        <form onsubmit="return saveCategory(event)">
        <div class="modal-body">
            <input type="hidden" name="id" id="catId">
            <div class="form-group"><label>Nama *</label><input type="text" name="name" id="catName" required></div>
            <div class="form-group"><label>Deskripsi</label><textarea name="description" id="catDesc"></textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('categoryModal').classList.remove('active')">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal(data) {
    document.getElementById('catId').value = data ? data.id : '';
    document.getElementById('catName').value = data ? data.name : '';
    document.getElementById('catDesc').value = data ? data.description : '';
    document.getElementById('catModalTitle').textContent = data ? 'Edit Kategori' : 'Tambah Kategori';
    document.getElementById('categoryModal').classList.add('active');
}
async function saveCategory(e) {
    e.preventDefault();
    var r = await api('api/products.php', {
        action: 'save_category',
        id: document.getElementById('catId').value,
        name: document.getElementById('catName').value,
        description: document.getElementById('catDesc').value
    });
    if (r.success) { showToast('Kategori disimpan!'); document.getElementById('categoryModal').classList.remove('active'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
async function editCategory(id) {
    var r = await api('api/products.php?action=get_category&id=' + id);
    if (r.category) openCategoryModal(r.category);
}
async function deleteCategory(id) {
    if (!confirmDelete('Yakin hapus kategori? Produk dengan kategori ini akan tanpa kategori.')) return;
    var r = await api('api/products.php', { action: 'delete_category', id: id });
    if (r.success) { showToast('Kategori dihapus!'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
</script>
