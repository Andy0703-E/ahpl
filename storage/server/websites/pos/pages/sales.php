<?php
$pdo = getDB();
$customers = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name")->fetchAll();
?>
<div class="pos-container">
    <div class="pos-products">
        <div class="pos-search">
            <input type="text" id="posSearch" placeholder="Cari produk (nama atau barcode)..." onkeyup="searchProducts(this.value)" autofocus>
        </div>
        <div class="pos-grid" id="posGrid"></div>
    </div>
    <div class="pos-cart">
        <div class="pos-cart-header">
            <h3><i class="icon">&#9741;</i> Keranjang</h3>
            <button class="btn btn-sm btn-danger" onclick="clearCart()">Kosongkan</button>
        </div>
        <div class="cart-items" id="cartItems">
            <p style="color:var(--gray);text-align:center;padding:20px;font-size:13px;">Belum ada item</p>
        </div>
        <div class="cart-total">
            <div class="cart-total-row"><span>Subtotal</span><span id="cartSubtotal">Rp 0</span></div>
            <div class="cart-total-row"><span>Diskon</span>
                <span><input type="number" id="cartDiscount" value="0" style="width:80px;padding:4px 8px;border:1px solid var(--border);border-radius:4px;text-align:right;font-size:13px;" onchange="updateTotal()"></span>
            </div>
            <div class="cart-total-row grand"><span>Total</span><span id="cartTotal">Rp 0</span></div>
        </div>
        <div class="pay-section">
            <div style="display:flex;gap:8px;margin-bottom:8px;">
                <select id="paymentMethod" style="flex:1;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:13px;">
                    <option value="cash">Tunai</option>
                    <option value="transfer">Transfer</option>
                    <option value="qris">QRIS</option>
                    <option value="debit">Debit</option>
                    <option value="credit">Kredit</option>
                </select>
                <select id="posCustomer" style="flex:1;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:13px;">
                    <option value="">Umum</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <input type="number" id="payAmount" placeholder="Jumlah Bayar" onkeyup="calcChange()" onchange="calcChange()">
                <button class="btn btn-success" onclick="checkout()" style="white-space:nowrap;padding:10px 24px;" id="btnCheckout">Bayar</button>
            </div>
            <div id="changeDisplay" style="text-align:right;padding:4px 0;font-size:13px;color:var(--success);font-weight:600;"></div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal" style="max-width:320px;">
        <div class="modal-header"><h3>Struk</h3><button class="modal-close" onclick="document.getElementById('receiptModal').classList.remove('active')">&times;</button></div>
        <div class="modal-body" id="receiptContent" style="font-family:monospace;font-size:12px;line-height:1.5;white-space:pre-wrap;"></div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="printReceipt()">Cetak</button>
            <button class="btn btn-outline" onclick="document.getElementById('receiptModal').classList.remove('active')">Tutup</button>
        </div>
    </div>
</div>

<script>
var cart = [];

async function loadProducts() {
    var r = await api('api/products.php?action=get_products');
    if (r.products) renderProducts(r.products);
}
function renderProducts(products) {
    var el = document.getElementById('posGrid');
    if (!products.length) {
        el.innerHTML = '<p style="color:var(--gray);text-align:center;padding:20px;">Tidak ada produk aktif</p>';
        return;
    }
    el.innerHTML = products.map(function(p) {
        return '<div class="pos-product" onclick="addToCart(' + p.id + ',\'' + escapeHtml(p.name) + '\',' + p.selling_price + ')">' +
            '<div class="pp-name">' + escapeHtml(p.name) + '</div>' +
            '<div class="pp-price">' + formatMoney(p.selling_price) + '</div>' +
            '<div class="pp-stock">Stok: ' + p.stock + ' ' + escapeHtml(p.short_name || '') + '</div>' +
            '</div>';
    }).join('');
}
async function searchProducts(q) {
    var r = await api('api/products.php?action=search_products&q=' + encodeURIComponent(q));
    if (r.products) renderProducts(r.products);
}

function addToCart(id, name, price) {
    var existing = cart.find(function(i) { return i.id === id; });
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id: id, name: name, price: parseFloat(price), qty: 1 });
    }
    renderCart();
    document.getElementById('payAmount').focus();
}
function removeFromCart(id) {
    cart = cart.filter(function(i) { return i.id !== id; });
    renderCart();
}
function clearCart() {
    if (cart.length === 0) return;
    if (!confirm('Kosongkan keranjang?')) return;
    cart = [];
    renderCart();
}
function updateQty(id, qty) {
    var item = cart.find(function(i) { return i.id === id; });
    if (item) {
        qty = parseInt(qty) || 1;
        if (qty < 1) qty = 1;
        item.qty = qty;
        updateTotal();
    }
}
function renderCart() {
    var el = document.getElementById('cartItems');
    if (cart.length === 0) {
        el.innerHTML = '<p style="color:var(--gray);text-align:center;padding:20px;font-size:13px;">Belum ada item</p>';
        updateTotal();
        return;
    }
    el.innerHTML = cart.map(function(i) {
        return '<div class="cart-item">' +
            '<div class="cart-item-name">' + escapeHtml(i.name) + '</div>' +
            '<div class="cart-item-qty"><input type="number" value="' + i.qty + '" min="1" onchange="updateQty(' + i.id + ',this.value)"></div>' +
            '<div class="cart-item-price">' + formatMoney(i.price * i.qty) + '</div>' +
            '<div class="cart-item-remove" onclick="removeFromCart(' + i.id + ')">&#10005;</div>' +
            '</div>';
    }).join('');
    updateTotal();
}
function updateTotal() {
    var subtotal = cart.reduce(function(s, i) { return s + (i.price * i.qty); }, 0);
    var discount = parseFloat(document.getElementById('cartDiscount').value) || 0;
    var total = Math.max(0, subtotal - discount);
    document.getElementById('cartSubtotal').textContent = formatMoney(subtotal);
    document.getElementById('cartTotal').textContent = formatMoney(total);
    calcChange();
}
function calcChange() {
    var total = parseFloat(document.getElementById('cartTotal').textContent.replace(/[^0-9]/g,'')) || 0;
    var pay = parseFloat(document.getElementById('payAmount').value) || 0;
    var change = pay - total;
    var el = document.getElementById('changeDisplay');
    if (pay >= total && total > 0) {
        el.textContent = 'Kembali: ' + formatMoney(change);
    } else {
        el.textContent = '';
    }
}
async function checkout() {
    if (cart.length === 0) { showToast('Keranjang kosong', 'warning'); return; }
    var total = cart.reduce(function(s, i) { return s + (i.price * i.qty); }, 0);
    var discount = parseFloat(document.getElementById('cartDiscount').value) || 0;
    var grandTotal = Math.max(0, total - discount);
    var payAmount = parseFloat(document.getElementById('payAmount').value) || 0;
    if (payAmount < grandTotal) { showToast('Jumlah bayar kurang!', 'error'); document.getElementById('payAmount').focus(); return; }

    document.getElementById('btnCheckout').disabled = true;
    document.getElementById('btnCheckout').textContent = 'Memproses...';

    var r = await api('api/sales.php', {
        action: 'checkout',
        items: cart,
        subtotal: total,
        discount: discount,
        grand_total: grandTotal,
        paid_amount: payAmount,
        change_amount: payAmount - grandTotal,
        payment_method: document.getElementById('paymentMethod').value,
        customer_id: document.getElementById('posCustomer').value || null
    });

    document.getElementById('btnCheckout').disabled = false;
    document.getElementById('btnCheckout').textContent = 'Bayar';

    if (r.success) {
        showReceipt(r.sale_id, r.invoice);
        cart = [];
        renderCart();
        document.getElementById('payAmount').value = '';
        document.getElementById('cartDiscount').value = '0';
        loadProducts();
    } else {
        showToast('Error: ' + r.error, 'error');
    }
}
async function showReceipt(saleId, invoice) {
    var r = await api('api/sales.php?action=get_receipt&id=' + saleId);
    if (r.receipt) {
        document.getElementById('receiptContent').textContent = r.receipt;
        document.getElementById('receiptModal').classList.add('active');
    }
}
function printReceipt() {
    var content = document.getElementById('receiptContent').textContent;
    var win = window.open('', '', 'width=300,height=600');
    win.document.write('<pre style="font-family:monospace;font-size:12px;line-height:1.5;">' + content + '</pre>');
    win.print();
    win.close();
}

loadProducts();
document.addEventListener('keydown', function(e) {
    if (e.key === 'F2') { e.preventDefault(); document.getElementById('posSearch').focus(); }
    if (e.key === 'F8' && cart.length > 0) { e.preventDefault(); document.getElementById('payAmount').focus(); }
    if (e.key === 'Enter' && document.activeElement === document.getElementById('payAmount')) { checkout(); }
});
</script>
