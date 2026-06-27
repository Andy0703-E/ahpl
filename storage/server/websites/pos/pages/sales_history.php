<?php
$pdo = getDB();
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
$total = (int)$stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$sales = $pdo->prepare("
    SELECT s.*, c.name as customer_name, u.name as cashier_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN users u ON s.cashier_id = u.id 
    WHERE DATE(s.created_at) BETWEEN :from AND :to 
    ORDER BY s.created_at DESC LIMIT :lim OFFSET :off
");
$sales->bindValue(':from', $dateFrom);
$sales->bindValue(':to', $dateTo);
$sales->bindValue(':lim', $perPage, PDO::PARAM_INT);
$sales->bindValue(':off', $offset, PDO::PARAM_INT);
$sales->execute();
$sales = $sales->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <form method="get" class="search-box" style="margin-bottom:16px;">
            <input type="hidden" name="page" value="sales_history">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                Dari: <input type="date" name="from" value="<?= $dateFrom ?>">
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                Sampai: <input type="date" name="to" value="<?= $dateTo ?>">
            </label>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
        <table class="table">
            <thead><tr><th>Invoice</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th>Total</th><th>Bayar</th><th>Metode</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td><strong><?= escape($s['invoice']) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                    <td><?= escape($s['customer_name'] ?? '-') ?></td>
                    <td><?= escape($s['cashier_name'] ?? '-') ?></td>
                    <td><strong><?= money($s['grand_total']) ?></strong></td>
                    <td><?= money($s['paid_amount']) ?></td>
                    <td><span class="badge badge-info"><?= escape($s['payment_method']) ?></span></td>
                    <td><button class="btn btn-sm btn-info" onclick="viewSale(<?= $s['id'] ?>)">Detail</button></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sales)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray);">Tidak ada penjualan</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=sales_history&p=<?= $i ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="saleDetailModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>Detail Penjualan</h3><button class="modal-close" onclick="document.getElementById('saleDetailModal').classList.remove('active')">&times;</button></div>
        <div class="modal-body" id="saleDetailBody"></div>
    </div>
</div>

<script>
async function viewSale(id) {
    var r = await api('api/sales.php?action=get_sale_detail&id=' + id);
    if (!r.sale) return;
    var s = r.sale;
    var html = '<div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;">';
    html += '<div class="stat-card"><div class="label">Invoice</div><div class="value purple">' + escapeHtml(s.invoice) + '</div></div>';
    html += '<div class="stat-card"><div class="label">Total</div><div class="value green">' + formatMoney(s.grand_total) + '</div></div>';
    html += '<div class="stat-card"><div class="label">Status</div><div class="value">Lunas</div></div>';
    html += '</div>';
    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;font-size:13px;">';
    html += '<div><strong>Pelanggan:</strong> ' + escapeHtml(s.customer_name || '-') + '</div>';
    html += '<div><strong>Kasir:</strong> ' + escapeHtml(s.cashier_name || '-') + '</div>';
    html += '<div><strong>Metode:</strong> ' + escapeHtml(s.payment_method) + '</div>';
    html += '<div><strong>Tanggal:</strong> ' + s.created_at + '</div>';
    html += '<div><strong>Bayar:</strong> ' + formatMoney(s.paid_amount) + '</div>';
    html += '<div><strong>Kembali:</strong> ' + formatMoney(s.change_amount) + '</div>';
    if (s.discount > 0) html += '<div><strong>Diskon:</strong> ' + formatMoney(s.discount) + '</div>';
    html += '</div>';
    html += '<h4 style="margin-bottom:8px;">Item</h4>';
    html += '<table class="table"><thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>';
    r.items.forEach(function(i) {
        html += '<tr><td>' + escapeHtml(i.name) + '</td><td>' + i.qty + '</td><td>' + formatMoney(i.price) + '</td><td>' + formatMoney(i.subtotal) + '</td></tr>';
    });
    html += '</tbody></table>';
    document.getElementById('saleDetailBody').innerHTML = html;
    document.getElementById('saleDetailModal').classList.add('active');
}
</script>
