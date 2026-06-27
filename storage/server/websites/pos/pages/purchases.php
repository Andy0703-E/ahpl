<?php
$pdo = getDB();
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM purchases")->fetchColumn();
$totalPages = ceil($total / $perPage);

$purchases = $pdo->query("
    SELECT p.*, s.name as supplier_name, u.name as user_name 
    FROM purchases p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset
")->fetchAll();

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, purchase_price FROM products WHERE status = 1 ORDER BY name")->fetchAll();
?>
<div class="card">
    <div class="card-body">
        <div style="margin-bottom:16px;text-align:right;">
            <button class="btn btn-success" onclick="openPurchaseModal()">+ Pembelian Baru</button>
        </div>
        <table class="table">
            <thead><tr><th>Invoice</th><th>Supplier</th><th>Total</th><th>Diskon</th><th>Grand Total</th><th>Status</th><th>Tanggal</th><th>User</th></tr></thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><strong><?= escape($p['invoice']) ?></strong></td>
                    <td><?= escape($p['supplier_name'] ?? '-') ?></td>
                    <td><?= money($p['total']) ?></td>
                    <td><?= money($p['discount']) ?></td>
                    <td><strong><?= money($p['grand_total']) ?></strong></td>
                    <td><span class="badge badge-<?= $p['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= escape($p['payment_status']) ?></span></td>
                    <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                    <td><?= escape($p['user_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($purchases)): ?>
                <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray);">Belum ada pembelian</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=purchases&p=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="purchaseModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>Pembelian Baru</h3><button class="modal-close" onclick="closePurchaseModal()">&times;</button></div>
        <form onsubmit="return savePurchase(event)">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Supplier</label>
                    <select name="supplier_id" id="purSupplier">
                        <option value="">Pilih Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= escape($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Status Pembayaran</label>
                    <select name="payment_status" id="purPaymentStatus">
                        <option value="unpaid">Belum Dibayar</option>
                        <option value="paid">Lunas</option>
                        <option value="partial">Angsuran</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>Catatan</label><textarea name="note" id="purNote"></textarea></div>
            <h4 style="margin-bottom:8px;font-size:14px;">Item Pembelian</h4>
            <div id="purchaseItems">
                <div class="purchase-item" style="display:grid;grid-template-columns:2fr 60px 100px 100px 30px;gap:8px;align-items:center;margin-bottom:8px;">
                    <select class="pur-product" style="padding:6px;border:1px solid var(--border);border-radius:4px;font-size:13px;">
                        <option value="">Pilih Produk</option>
                        <?php foreach ($products as $pr): ?>
                        <option value="<?= $pr['id'] ?>" data-price="<?= $pr['purchase_price'] ?>"><?= escape($pr['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" class="pur-qty" placeholder="Qty" value="1" min="1" style="padding:6px;border:1px solid var(--border);border-radius:4px;text-align:center;font-size:13px;">
                    <input type="number" class="pur-price" placeholder="Harga" style="padding:6px;border:1px solid var(--border);border-radius:4px;text-align:right;font-size:13px;">
                    <span class="pur-subtotal" style="text-align:right;font-size:13px;">Rp 0</span>
                    <span style="text-align:center;color:var(--danger);cursor:pointer;" onclick="this.closest('.purchase-item').remove()">&#10005;</span>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" onclick="addPurchaseItem()">+ Tambah Item</button>
            <div style="margin-top:16px;text-align:right;font-size:16px;font-weight:700;">
                Total: <span id="purTotalDisplay">Rp 0</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closePurchaseModal()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan Pembelian</button>
        </div>
        </form>
    </div>
</div>

<script>
function openPurchaseModal() { document.getElementById('purchaseModal').classList.add('active'); }
function closePurchaseModal() { document.getElementById('purchaseModal').classList.remove('active'); }
function addPurchaseItem() {
    var tpl = document.querySelector('.purchase-item').cloneNode(true);
    tpl.querySelector('.pur-product').value = '';
    tpl.querySelector('.pur-qty').value = 1;
    tpl.querySelector('.pur-price').value = '';
    tpl.querySelector('.pur-subtotal').textContent = 'Rp 0';
    tpl.querySelectorAll('input, select').forEach(function(el) { el.onchange = function() { calcPurchaseTotal(); }; el.onkeyup = function() { calcPurchaseTotal(); }; });
    document.getElementById('purchaseItems').appendChild(tpl);
}
function calcPurchaseTotal() {
    var total = 0;
    document.querySelectorAll('.purchase-item').forEach(function(item) {
        var sel = item.querySelector('.pur-product');
        var price = parseFloat(item.querySelector('.pur-price').value) || parseFloat(sel.options[sel.selectedIndex]?.dataset?.price || 0);
        var qty = parseInt(item.querySelector('.pur-qty').value) || 0;
        var sub = price * qty;
        item.querySelector('.pur-subtotal').textContent = formatMoney(sub);
        total += sub;
    });
    document.getElementById('purTotalDisplay').textContent = formatMoney(total);
}
async function savePurchase(e) {
    e.preventDefault();
    var items = [];
    document.querySelectorAll('.purchase-item').forEach(function(item) {
        var sel = item.querySelector('.pur-product');
        var qty = parseInt(item.querySelector('.pur-qty').value) || 0;
        var price = parseFloat(item.querySelector('.pur-price').value) || parseFloat(sel.options[sel.selectedIndex]?.dataset?.price || 0);
        if (sel.value && qty > 0) {
            items.push({ product_id: sel.value, qty: qty, price: price });
        }
    });
    if (items.length === 0) { showToast('Tambah minimal 1 item', 'warning'); return; }
    var r = await api('api/purchases.php', {
        action: 'save_purchase',
        supplier_id: document.getElementById('purSupplier').value || null,
        payment_status: document.getElementById('purPaymentStatus').value,
        note: document.getElementById('purNote').value,
        items: items
    });
    if (r.success) { showToast('Pembelian disimpan!'); closePurchaseModal(); location.reload(); }
    else showToast('Error: ' + r.error, 'error');
}
document.addEventListener('change', calcPurchaseTotal);
document.addEventListener('keyup', calcPurchaseTotal);
</script>
