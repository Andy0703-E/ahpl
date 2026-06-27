<?php
$pdo = getDB();
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$search = $_GET['search'] ?? '';

$where = '';
$params = [];
if ($search) {
    $where = "WHERE p.name LIKE :search OR p.barcode LIKE :search2 OR p.sku LIKE :search3";
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
}

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM products p $where")->execute($params)->fetchColumn();
$products = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.name as unit_name, u.short_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    $where
    ORDER BY p.name ASC LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $products->bindValue($k, $v);
$products->bindValue(':lim', $perPage, PDO::PARAM_INT);
$products->bindValue(':off', $offset, PDO::PARAM_INT);
$products->execute();
$products = $products->fetchAll();

$totalPages = ceil($total / $perPage);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$units = $pdo->query("SELECT * FROM units ORDER BY name")->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <div class="search-box" style="margin-bottom:16px;">
            <form method="get" style="display:flex;gap:8px;width:100%;">
                <input type="hidden" name="page" value="products">
                <input type="text" name="search" placeholder="Cari nama, barcode, atau SKU..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if ($search): ?><a href="?page=products" class="btn btn-outline">Reset</a><?php endif; ?>
                <button type="button" class="btn btn-success" onclick="openProductModal()" style="margin-left:auto;">+ Tambah Produk</button>
            </form>
        </div>
        <table class="table">
            <thead><tr><th>Barcode</th><th>Nama</th><th>Kategori</th><th>Harga Beli</th><th>Harga Jual</th><th>Stok</th><th>Min Stok</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= escape($p['barcode'] ?: '-') ?></td>
                    <td><strong><?= escape($p['name']) ?></strong><br><small style="color:var(--gray);">SKU: <?= escape($p['sku'] ?: '-') ?></small></td>
                    <td><?= escape($p['category_name'] ?? '-') ?></td>
                    <td><?= money($p['purchase_price']) ?></td>
                    <td><?= money($p['selling_price']) ?></td>
                    <td><strong><?= (int)$p['stock'] ?></strong> <small style="color:var(--gray);"><?= escape($p['short_name'] ?? '') ?></small></td>
                    <td><?= (int)$p['minimum_stock'] ?></td>
                    <td><span class="badge badge-<?= $p['status'] ? 'success' : 'danger' ?>"><?= $p['status'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td class="table-actions">
                        <button class="btn btn-sm btn-info" onclick="editProduct(<?= $p['id'] ?>)">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?= $p['id'] ?>)">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr><td colspan="9" style="text-align:center;padding:20px;color:var(--gray);">Tidak ada produk</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=products&p=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="productModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3 id="productModalTitle">Tambah Produk</h3><button class="modal-close" onclick="closeProductModal()">&times;</button></div>
        <form id="productForm" onsubmit="return saveProduct(event)">
        <div class="modal-body">
            <input type="hidden" name="id" id="productId">
            <div class="form-row">
                <div class="form-group"><label>Nama Produk *</label><input type="text" name="name" id="prodName" required></div>
                <div class="form-group"><label>Barcode</label><input type="text" name="barcode" id="prodBarcode"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>SKU</label><input type="text" name="sku" id="prodSku"></div>
                <div class="form-group"><label>Kategori</label>
                    <select name="category_id" id="prodCategory">
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Satuan</label>
                    <select name="unit_id" id="prodUnit">
                        <option value="">Pilih Satuan</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?> (<?= escape($u['short_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Harga Beli *</label><input type="number" name="purchase_price" id="prodPurchasePrice" step="0.01" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Harga Jual *</label><input type="number" name="selling_price" id="prodSellingPrice" step="0.01" required></div>
                <div class="form-group"><label>Stok</label><input type="number" name="stock" id="prodStock" value="0"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Stok Minimal</label><input type="number" name="minimum_stock" id="prodMinStock" value="0"></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" id="prodStatus">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeProductModal()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        </form>
    </div>
</div>

<script>
function openProductModal(data) {
    document.getElementById('productId').value = data ? data.id : '';
    document.getElementById('prodName').value = data ? data.name : '';
    document.getElementById('prodBarcode').value = data ? data.barcode : '';
    document.getElementById('prodSku').value = data ? data.sku : '';
    document.getElementById('prodCategory').value = data ? data.category_id : '';
    document.getElementById('prodUnit').value = data ? data.unit_id : '';
    document.getElementById('prodPurchasePrice').value = data ? data.purchase_price : '';
    document.getElementById('prodSellingPrice').value = data ? data.selling_price : '';
    document.getElementById('prodStock').value = data ? data.stock : 0;
    document.getElementById('prodMinStock').value = data ? data.minimum_stock : 0;
    document.getElementById('prodStatus').value = data ? data.status : 1;
    document.getElementById('productModalTitle').textContent = data ? 'Edit Produk' : 'Tambah Produk';
    document.getElementById('productModal').classList.add('active');
}
function closeProductModal() { document.getElementById('productModal').classList.remove('active'); }

async function saveProduct(e) {
    e.preventDefault();
    var f = document.getElementById('productForm');
    var data = {
        action: 'save_product',
        id: f.id.value,
        name: f.name.value,
        barcode: f.barcode.value,
        sku: f.sku.value,
        category_id: f.category_id.value || null,
        unit_id: f.unit_id.value || null,
        purchase_price: f.purchase_price.value,
        selling_price: f.selling_price.value,
        stock: f.stock.value,
        minimum_stock: f.minimum_stock.value,
        status: f.status.value
    };
    var r = await api('api/products.php', data);
    if (r.success) { showToast('Produk disimpan!'); closeProductModal(); location.reload(); }
    else { showToast('Error: ' + r.error, 'error'); }
}

async function editProduct(id) {
    var r = await api('api/products.php?action=get&id=' + id);
    if (r.product) openProductModal(r.product);
    else showToast('Gagal memuat data', 'error');
}

async function deleteProduct(id) {
    if (!confirmDelete('Yakin hapus produk ini?')) return;
    var r = await api('api/products.php', { action: 'delete_product', id: id });
    if (r.success) { showToast('Produk dihapus!'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
</script>
