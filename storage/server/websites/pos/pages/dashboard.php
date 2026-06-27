<?php
$pdo = getDB();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// Today's sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE DATE(created_at) = :today AND payment_status = 'paid'");
$stmt->execute([':today' => $today]);
$todaySales = (float)$stmt->fetchColumn();

// Month sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE DATE(created_at) >= :ms AND payment_status = 'paid'");
$stmt->execute([':ms' => $monthStart]);
$monthSales = (float)$stmt->fetchColumn();

// Total products
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Low stock products
$lowStock = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock <= minimum_stock AND minimum_stock > 0");
$lowStock->execute();
$lowStockCount = (int)$lowStock->fetchColumn();

// Recent sales
$recentSales = $pdo->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT 5")->fetchAll();

// Daily sales chart data (last 7 days)
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE DATE(created_at) = :d AND payment_status = 'paid'");
    $stmt->execute([':d' => $d]);
    $chartData[] = ['date' => date('d/m', strtotime($d)), 'total' => (float)$stmt->fetchColumn()];
}
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Penjualan Hari Ini</div>
        <div class="value green"><?= money($todaySales) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Penjualan Bulan Ini</div>
        <div class="value blue"><?= money($monthSales) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Produk</div>
        <div class="value purple"><?= $totalProducts ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Stok Hampir Habis</div>
        <div class="value orange"><?= $lowStockCount ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">Penjualan 7 Hari Terakhir</div>
    <div class="card-body">
        <canvas id="salesChart" height="200"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-header">Transaksi Terakhir</div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Waktu</th></tr></thead>
            <tbody>
                <?php foreach ($recentSales as $s): ?>
                <tr>
                    <td><?= escape($s['invoice']) ?></td>
                    <td><?= escape($s['customer_name'] ?? '-') ?></td>
                    <td><?= money($s['grand_total']) ?></td>
                    <td><span class="badge badge-<?= $s['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= $s['payment_status'] ?></span></td>
                    <td><?= date('H:i', strtotime($s['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentSales)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--gray);padding:20px;">Belum ada transaksi</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chartData, 'date')) ?>,
        datasets: [{
            label: 'Penjualan',
            data: <?= json_encode(array_column($chartData, 'total')) ?>,
            backgroundColor: '#4f46e5',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: function(v) { return 'Rp ' + v.toLocaleString('id-ID'); } } }
        }
    }
});
</script>
