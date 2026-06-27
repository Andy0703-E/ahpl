<?php
$pdo = getDB();
$tab = $_GET['tab'] ?? 'adjustment';

$products = $pdo->query("SELECT id, name, stock, minimum_stock FROM products WHERE status = 1 ORDER BY name")->fetchAll();
$lowStock = $pdo->query("SELECT p.*, c.name as category_name, u.short_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN units u ON p.unit_id = u.id WHERE p.stock <= p.minimum_stock AND p.minimum_stock > 0 ORDER BY (p.minimum_stock - p.stock) DESC")->fetchAll();

$historyPage = max(1, (int)($_GET['hp'] ?? 1));
$perPage = 30;
$offset = ($historyPage - 1) * $perPage;
$historyTotal = (int)$pdo->query("SELECT COUNT(*) FROM product_stock_history")->fetchColumn();
$historyPages = ceil($historyTotal / $perPage);
$history = $pdo->query("SELECT h.*, p.name as product_name, u.name as user_name FROM product_stock_history h LEFT JOIN products p ON h.product_id = p.id LEFT JOIN users u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
?>
<div class="tabs">
    <div class="tab <?= $tab === 'adjustment' ? 'active' : '' ?>" onclick="location.href='?page=stock&tab=adjustment'">Penyesuaian Stok</div>
    <div class="tab <?= $tab === 'low' ? 'active' : '' ?>" onclick="location.href='?page=stock&tab=low'">Stok Hampir Habis</div>
    <div class="tab <?= $tab === 'history' ? 'active' : '' ?>" onclick="location.href='?page=stock&tab=history'">Riwayat Stok</div>
</div>

<?php if ($tab === 'adjustment'): ?>
<div class="card">
    <div class="card-body">
        <form onsubmit="return saveAdjustment(event)" style="max-width:500px;">
            <div class="form-group">
                <label>Produk *</label>
                <select name="product_id" id="adjProduct" required style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;">
                    <option value="">Pilih Produk</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= escape($p['name']) ?> (Stok: <?= $p['stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Stok Baru *</label><input type="number" name="new_stock" id="adjNewStock" required></div>
                <div class="form-group"><label>Stok Saat Ini</label><input type="text" id="adjCurrentStock" readonly style="background:var(--light);"></div>
            </div>
            <div class="form-group"><label>Alasan *</label><textarea name="reason" id="adjReason" required placeholder="Contoh: stock opname, barang rusak, hilang..."></textarea></div>
            <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
        </form>
    </div>
</div>
<script>
document.getElementById('adjProduct').onchange = function() {
    var sel = this;
    var txt = sel.options[sel.selectedIndex].text;
    var match = txt.match(/Stok: (\d+)/);
    document.getElementById('adjCurrentStock').value = match ? match[1] : '';
};
async function saveAdjustment(e) {
    e.preventDefault();
    var r = await api('api/stock.php', {
        action: 'adjust_stock',
        product_id: document.getElementById('adjProduct').value,
        new_stock: document.getElementById('adjNewStock').value,
        reason: document.getElementById('adjReason').value
    });
    if (r.success) { showToast('Stok disesuaikan!'); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
</script>
<?php elseif ($tab === 'low'): ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead><tr><th>Produk</th><th>Kategori</th><th>Stok</th><th>Min Stok</th><th>Kekurangan</th><th>Satuan</th></tr></thead>
            <tbody>
                <?php foreach ($lowStock as $p): ?>
                <tr>
                    <td><strong><?= escape($p['name']) ?></strong></td>
                    <td><?= escape($p['category_name'] ?? '-') ?></td>
                    <td style="color:var(--danger);font-weight:700;"><?= (int)$p['stock'] ?></td>
                    <td><?= (int)$p['minimum_stock'] ?></td>
                    <td style="color:var(--danger);"><?= max(0, $p['minimum_stock'] - $p['stock']) ?></td>
                    <td><?= escape($p['short_name'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lowStock)): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--gray);">Semua stok aman</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Produk</th><th>Tipe</th><th>Qty</th><th>Stok Awal</th><th>Stok Akhir</th><th>Catatan</th><th>User</th></tr></thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                    <td><?= escape($h['product_name'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= escape($h['type']) ?></span></td>
                    <td><strong><?= $h['qty'] > 0 ? '+' : '' ?><?= (int)$h['qty'] ?></strong></td>
                    <td><?= (int)$h['before_stock'] ?></td>
                    <td><?= (int)$h['after_stock'] ?></td>
                    <td><?= escape($h['note'] ?: '-') ?></td>
                    <td><?= escape($h['user_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray);">Belum ada riwayat</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($historyPages > 1): ?>
        <div class="pagination" style="padding:12px;">
            <?php for ($i = 1; $i <= $historyPages; $i++): ?>
                <a href="?page=stock&tab=history&hp=<?= $i ?>" class="<?= $i === $historyPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
