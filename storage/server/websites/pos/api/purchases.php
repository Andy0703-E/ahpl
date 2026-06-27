<?php
require_once __DIR__ . '/../config.php';
if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    $pdo = getDB();

    if ($action === 'save_purchase') {
        $pdo->beginTransaction();
        try {
            $invoice = generateInvoice('PO');
            $items = $input['items'] ?? [];
            $supplierId = $input['supplier_id'] ?? null;
            $paymentStatus = $input['payment_status'] ?? 'unpaid';
            $note = $input['note'] ?? '';

            // Calculate total
            $total = 0;
            foreach ($items as $item) {
                $total += $item['price'] * $item['qty'];
            }

            $stmt = $pdo->prepare("INSERT INTO purchases (invoice, supplier_id, total, grand_total, payment_status, note, user_id, created_at) VALUES (:invoice, :supplier_id, :total, :grand_total, :payment_status, :note, :user_id, NOW())");
            $stmt->execute([
                ':invoice' => $invoice,
                ':supplier_id' => $supplierId,
                ':total' => $total,
                ':grand_total' => $total,
                ':payment_status' => $paymentStatus,
                ':note' => $note,
                ':user_id' => $_SESSION['user_id'],
            ]);
            $purchaseId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, qty, price, subtotal) VALUES (:purchase_id, :product_id, :qty, :price, :subtotal)");
            $stmtStock = $pdo->prepare("UPDATE products SET stock = stock + :qty, purchase_price = :price WHERE id = :id");
            $stmtHistory = $pdo->prepare("INSERT INTO product_stock_history (product_id, type, qty, before_stock, after_stock, note, user_id, created_at) VALUES (:product_id, 'purchase', :qty, :before_stock, :after_stock, :note, :user_id, NOW())");

            foreach ($items as $item) {
                $sub = $item['price'] * $item['qty'];
                $stmtItem->execute([
                    ':purchase_id' => $purchaseId,
                    ':product_id' => $item['product_id'],
                    ':qty' => $item['qty'],
                    ':price' => $item['price'],
                    ':subtotal' => $sub,
                ]);

                $beforeStock = (int)$pdo->query("SELECT stock FROM products WHERE id = " . (int)$item['product_id'])->fetchColumn();
                $afterStock = $beforeStock + $item['qty'];
                $stmtStock->execute([':qty' => $item['qty'], ':price' => $item['price'], ':id' => $item['product_id']]);
                $stmtHistory->execute([
                    ':product_id' => $item['product_id'],
                    ':qty' => $item['qty'],
                    ':before_stock' => $beforeStock,
                    ':after_stock' => $afterStock,
                    ':note' => "Pembelian $invoice",
                    ':user_id' => $_SESSION['user_id'],
                ]);
            }

            // Add payment record if paid
            if ($paymentStatus === 'paid') {
                $stmtPay = $pdo->prepare("INSERT INTO payments (transaction_type, transaction_id, amount, payment_method, paid_at) VALUES ('purchase', :tid, :amount, 'transfer', NOW())");
                $stmtPay->execute([':tid' => $purchaseId, ':amount' => $total]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'purchase_id' => $purchaseId, 'invoice' => $invoice]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
