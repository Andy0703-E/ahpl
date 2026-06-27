<?php
$pdo = getDB();
$type = $_GET['type'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

if ($type === 'sales') {
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as tgl, COUNT(*) as transaksi, 
               COALESCE(SUM(grand_total),0) as total,
               COALESCE(SUM(paid_amount),0) as bayar
        FROM sales 
        WHERE DATE(created_at) BETWEEN :from AND :to AND payment_status = 'paid'
        GROUP BY DATE(created_at) ORDER BY tgl DESC
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $rows = $stmt->fetchAll();

    $grandTotal = array_sum(array_column($rows, 'total'));
    $grandTrans = array_sum(array_column($rows, 'transaksi'));

} elseif ($type === 'products') {
    $rows = $pdo->query("
        SELECT p.name, p.sku, p.stock, p.minimum_stock,
               (SELECT COALESCE(SUM(si.qty),0) FROM sale_items si WHERE si.product_id = p.id) as terjual,
               (SELECT COALESCE(SUM(pi.qty),0) FROM purchase_items pi WHERE pi.product_id = p.id) as dibeli
        FROM products p ORDER BY terjual DESC LIMIT 50
    ")->fetchAll();
    $grandTotal = count($rows);

} elseif ($type === 'profit') {
    $rows = $pdo->prepare("
        SELECT DATE(s.created_at) as tgl, 
               COALESCE(SUM(si.qty * (si.price - p.purchase_price)),0) as laba_kotor,
               COALESCE(SUM(s.grand_total),0) as total_penjualan
        FROM sales s 
        JOIN sale_items si ON si.sale_id = s.id
        JOIN products p ON p.id = si.product_id
        WHERE DATE(s.created_at) BETWEEN :from AND :to AND s.payment_status = 'paid'
        GROUP BY DATE(s.created_at) ORDER BY tgl DESC
    ");
    $rows->execute([':from' => $from, ':to' => $to]);
    $rows = $rows->fetchAll();
    $grandTotal = array_sum(array_column($rows, 'laba_kotor'));

} else {
    $rows = $pdo->query("
        SELECT e.*, u.name as user_name 
        FROM expenses e 
        LEFT JOIN users u ON e.user_id = u.id 
        ORDER BY e.created_at DESC LIMIT 50
    ")->fetchAll();
    $grandTotal = array_sum(array_column($rows, 'amount'));
}
?>
<div class="card">
    <div class="card-body">
        <form method="get" class="search-box" style="margin-bottom:16px;">
            <input type="hidden" name="page" value="reports">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                Tipe: 
                <select name="type" style="padding:6px;border:1px solid var(--border);border-radius:4px;">
                    <option value="sales" <?= $type === 'sales' ? 'selected' : '' ?>>Penjualan</option>
                    <option value="profit" <?= $type === 'profit' ? 'selected' : '' ?>>Laba Kotor</option>
                    <option value="products" <?= $type === 'products' ? 'selected' : '' ?>>Produk Terlaris</option>
                    <option value="expenses" <?= $type === 'expenses' ? 'selected' : '' ?>>Pengeluaran</option>
                </select>
            </label>
            <?php if ($type !== 'products' && $type !== 'expenses'): ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                Dari: <input type="date" name="from" value="<?= $from ?>">
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                Sampai: <input type="date" name="to" value="<?= $to ?>">
            </label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Tampilkan</button>
        </form>

        <?php if ($type === 'sales'): ?>
            <div class="stats-grid" style="grid-template-columns:1fr 1fr;">
                <div class="stat-card"><div class="label">Total Penjualan</div><div class="value green"><?= money($grandTotal) ?></div></div>
                <div class="stat-card"><div class="label">Total Transaksi</div><div class="value blue"><?= $grandTrans ?></div></div>
            </div>
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Transaksi</th><th>Total</th><th>Rata-rata</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                        <td><?= $r['transaksi'] ?></td>
                        <td><strong><?= money($r['total']) ?></strong></td>
                        <td><?= $r['transaksi'] > 0 ? money($r['total'] / $r['transaksi']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--gray);">Tidak ada data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php elseif ($type === 'profit'): ?>
            <div class="stats-grid" style="grid-template-columns:1fr 1fr;">
                <div class="stat-card"><div class="label">Total Laba Kotor</div><div class="value green"><?= money($grandTotal) ?></div></div>
            </div>
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Penjualan</th><th>Laba Kotor</th><th>Margin</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                        <td><?= money($r['total_penjualan']) ?></td>
                        <td><strong><?= money($r['laba_kotor']) ?></strong></td>
                        <td><?= $r['total_penjualan'] > 0 ? number_format($r['laba_kotor'] / $r['total_penjualan'] * 100, 1) . '%' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--gray);">Tidak ada data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php elseif ($type === 'products'): ?>
            <p style="font-size:13px;color:var(--gray);margin-bottom:12px;">Total produk: <?= $grandTotal ?></p>
            <table class="table">
                <thead><tr><th>Produk</th><th>SKU</th><th>Terjual</th><th>Dibeli</th><th>Stok</th><th>Min Stok</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= escape($r['name']) ?></strong></td>
                        <td><?= escape($r['sku'] ?: '-') ?></td>
                        <td><strong><?= (int)$r['terjual'] ?></strong></td>
                        <td><?= (int)$r['dibeli'] ?></td>
                        <td><?= (int)$r['stock'] ?></td>
                        <td><?= (int)$r['minimum_stock'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--gray);">Tidak ada data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php elseif ($type === 'expenses'): ?>
            <div class="stats-grid" style="grid-template-columns:1fr;">
                <div class="stat-card"><div class="label">Total Pengeluaran</div><div class="value red"><?= money($grandTotal) ?></div></div>
            </div>
            <table class="table">
                <thead><tr><th>Kategori</th><th>Jumlah</th><th>Deskripsi</th><th>User</th><th>Tanggal</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= escape($r['category']) ?></strong></td>
                        <td><?= money($r['amount']) ?></td>
                        <td><?= escape($r['description'] ?: '-') ?></td>
                        <td><?= escape($r['user_name'] ?? '-') ?></td>
                        <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gray);">Tidak ada data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
