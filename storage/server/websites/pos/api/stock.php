<?php
require_once __DIR__ . '/../config.php';
if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    $pdo = getDB();

    if ($action === 'adjust_stock') {
        $productId = $input['product_id'];
        $newStock = (int)$input['new_stock'];
        $reason = $input['reason'] ?? '';

        $pdo->beginTransaction();
        try {
            $oldStock = (int)$pdo->query("SELECT stock FROM products WHERE id = " . (int)$productId)->fetchColumn();

            $stmt = $pdo->prepare("UPDATE products SET stock = :stock WHERE id = :id");
            $stmt->execute([':stock' => $newStock, ':id' => $productId]);

            $stmtHistory = $pdo->prepare("INSERT INTO product_stock_history (product_id, type, qty, before_stock, after_stock, note, user_id, created_at) VALUES (:product_id, 'adjustment', :qty, :before_stock, :after_stock, :note, :user_id, NOW())");
            $stmtHistory->execute([
                ':product_id' => $productId,
                ':qty' => $newStock - $oldStock,
                ':before_stock' => $oldStock,
                ':after_stock' => $newStock,
                ':note' => $reason,
                ':user_id' => $_SESSION['user_id'],
            ]);

            $stmtAdj = $pdo->prepare("INSERT INTO stock_adjustments (product_id, old_stock, new_stock, reason, user_id, created_at) VALUES (:product_id, :old_stock, :new_stock, :reason, :user_id, NOW())");
            $stmtAdj->execute([
                ':product_id' => $productId,
                ':old_stock' => $oldStock,
                ':new_stock' => $newStock,
                ':reason' => $reason,
                ':user_id' => $_SESSION['user_id'],
            ]);

            $pdo->commit();
            echo json_encode(['success' => true]);
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
