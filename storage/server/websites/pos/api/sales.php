<?php
require_once __DIR__ . '/../config.php';
if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getDB();

    if ($action === 'checkout') {
        $pdo->beginTransaction();
        try {
            $invoice = generateInvoice('POS');
            $items = $input['items'] ?? [];
            $subtotal = $input['subtotal'] ?? 0;
            $discount = $input['discount'] ?? 0;
            $grandTotal = $input['grand_total'] ?? 0;
            $paidAmount = $input['paid_amount'] ?? 0;
            $changeAmount = $input['change_amount'] ?? 0;
            $paymentMethod = $input['payment_method'] ?? 'cash';
            $customerId = $input['customer_id'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO sales (invoice, customer_id, subtotal, discount, grand_total, payment_method, payment_status, paid_amount, change_amount, cashier_id, created_at) VALUES (:invoice, :customer_id, :subtotal, :discount, :grand_total, :payment_method, 'paid', :paid_amount, :change_amount, :cashier_id, NOW())");
            $stmt->execute([
                ':invoice' => $invoice,
                ':customer_id' => $customerId,
                ':subtotal' => $subtotal,
                ':discount' => $discount,
                ':grand_total' => $grandTotal,
                ':payment_method' => $paymentMethod,
                ':paid_amount' => $paidAmount,
                ':change_amount' => $changeAmount,
                ':cashier_id' => $_SESSION['user_id'],
            ]);
            $saleId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, subtotal) VALUES (:sale_id, :product_id, :qty, :price, :subtotal)");
            $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
            $stmtHistory = $pdo->prepare("INSERT INTO product_stock_history (product_id, type, qty, before_stock, after_stock, note, user_id, created_at) VALUES (:product_id, 'sale', :qty, :before_stock, :after_stock, :note, :user_id, NOW())");

            foreach ($items as $item) {
                $sub = $item['price'] * $item['qty'];
                $stmtItem->execute([
                    ':sale_id' => $saleId,
                    ':product_id' => $item['id'],
                    ':qty' => $item['qty'],
                    ':price' => $item['price'],
                    ':subtotal' => $sub,
                ]);

                $beforeStock = (int)$pdo->query("SELECT stock FROM products WHERE id = " . (int)$item['id'])->fetchColumn();
                $afterStock = max(0, $beforeStock - $item['qty']);
                $stmtStock->execute([':qty' => $item['qty'], ':id' => $item['id']]);
                $stmtHistory->execute([
                    ':product_id' => $item['id'],
                    ':qty' => $item['qty'],
                    ':before_stock' => $beforeStock,
                    ':after_stock' => $afterStock,
                    ':note' => "Penjualan $invoice",
                    ':user_id' => $_SESSION['user_id'],
                ]);
            }

            // Add payment record
            $stmtPay = $pdo->prepare("INSERT INTO payments (transaction_type, transaction_id, amount, payment_method, paid_at) VALUES ('sale', :transaction_id, :amount, :payment_method, NOW())");
            $stmtPay->execute([':transaction_id' => $saleId, ':amount' => $paidAmount, ':payment_method' => $paymentMethod]);

            $pdo->commit();
            echo json_encode(['success' => true, 'sale_id' => $saleId, 'invoice' => $invoice]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    if ($action === 'get_receipt') {
        $id = (int)($_GET['id'] ?? 0);
        $sale = $pdo->prepare("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = :id");
        $sale->execute([':id' => $id]);
        $sale = $sale->fetch();
        if (!$sale) { echo json_encode(['error' => 'Sale not found']); exit; }

        $items = $pdo->prepare("SELECT si.*, p.name FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = :id");
        $items->execute([':id' => $id]);
        $items = $items->fetchAll();

        $settings = [];
        $stmt = $pdo->query("SELECT * FROM settings");
        while ($r = $stmt->fetch()) $settings[$r['key_name']] = $r['value'];

        $receipt = str_repeat('=', 32) . "\n";
        $receipt .= str_pad($settings['store_name'] ?? 'TOKO AHPL', 32, ' ', STR_PAD_BOTH) . "\n";
        if (!empty($settings['store_address'])) $receipt .= $settings['store_address'] . "\n";
        if (!empty($settings['store_phone'])) $receipt .= 'Telp: ' . $settings['store_phone'] . "\n";
        $receipt .= str_repeat('-', 32) . "\n";
        $receipt .= 'Invoice: ' . $sale['invoice'] . "\n";
        $receipt .= 'Kasir: ' . $_SESSION['user_name'] . "\n";
        $receipt .= 'Tgl: ' . date('d/m/Y H:i', strtotime($sale['created_at'])) . "\n";
        if ($sale['customer_name']) $receipt .= 'Pelanggan: ' . $sale['customer_name'] . "\n";
        $receipt .= str_repeat('-', 32) . "\n";

        foreach ($items as $item) {
            $name = mb_substr($item['name'], 0, 16);
            $line = $name . "\n";
            $line .= '  ' . $item['qty'] . ' x ' . number_format($item['price'], 0, ',', '.') . ' = ' . number_format($item['subtotal'], 0, ',', '.');
            $receipt .= $line . "\n";
        }
        $receipt .= str_repeat('-', 32) . "\n";
        $receipt .= 'Subtotal: ' . number_format($sale['subtotal'], 0, ',', '.') . "\n";
        if ($sale['discount'] > 0) $receipt .= 'Diskon: ' . number_format($sale['discount'], 0, ',', '.') . "\n";
        $receipt .= 'Total: ' . number_format($sale['grand_total'], 0, ',', '.') . "\n";
        $receipt .= 'Bayar: ' . number_format($sale['paid_amount'], 0, ',', '.') . "\n";
        $receipt .= 'Kembali: ' . number_format($sale['change_amount'], 0, ',', '.') . "\n";
        $receipt .= str_repeat('=', 32) . "\n";
        $receipt .= ($settings['receipt_footer'] ?? 'Terima Kasih') . "\n";

        echo json_encode(['success' => true, 'receipt' => $receipt]);
        exit;
    }

    if ($action === 'get_sale_detail') {
        $id = (int)($_GET['id'] ?? 0);
        $sale = $pdo->prepare("SELECT s.*, c.name as customer_name, u.name as cashier_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id LEFT JOIN users u ON s.cashier_id = u.id WHERE s.id = :id");
        $sale->execute([':id' => $id]);
        $sale = $sale->fetch();
        $items = $pdo->prepare("SELECT si.*, p.name FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = :id");
        $items->execute([':id' => $id]);
        $items = $items->fetchAll();
        echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
