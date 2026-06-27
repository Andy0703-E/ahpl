<?php
require_once __DIR__ . '/../config.php';
if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getDB();

    // ============ PRODUCTS ============
    if ($action === 'save_product') {
        $id = $input['id'] ?? '';
        $data = [
            'name' => $input['name'],
            'barcode' => $input['barcode'] ?: null,
            'sku' => $input['sku'] ?: null,
            'category_id' => $input['category_id'] ?: null,
            'unit_id' => $input['unit_id'] ?: null,
            'supplier_id' => $input['supplier_id'] ?? null,
            'purchase_price' => $input['purchase_price'] ?? 0,
            'selling_price' => $input['selling_price'] ?? 0,
            'stock' => $input['stock'] ?? 0,
            'minimum_stock' => $input['minimum_stock'] ?? 0,
            'status' => $input['status'] ?? 1,
        ];
        if ($id) {
            $stmt = $pdo->prepare("UPDATE products SET name=:name, barcode=:barcode, sku=:sku, category_id=:category_id, unit_id=:unit_id, supplier_id=:supplier_id, purchase_price=:purchase_price, selling_price=:selling_price, stock=:stock, minimum_stock=:minimum_stock, status=:status, updated_at=NOW() WHERE id=:id");
            $data['id'] = $id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, barcode, sku, category_id, unit_id, supplier_id, purchase_price, selling_price, stock, minimum_stock, status, created_at, updated_at) VALUES (:name, :barcode, :sku, :category_id, :unit_id, :supplier_id, :purchase_price, :selling_price, :stock, :minimum_stock, :status, NOW(), NOW())");
        }
        $stmt->execute($data);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get') {
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'product' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete_product') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============ CATEGORIES ============
    if ($action === 'save_category') {
        $id = $input['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("UPDATE categories SET name=:name, description=:description WHERE id=:id");
            $stmt->execute([':id' => $id, ':name' => $input['name'], ':description' => $input['description'] ?? '']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (:name, :description, NOW())");
            $stmt->execute([':name' => $input['name'], ':description' => $input['description'] ?? '']);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_category') {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        echo json_encode(['success' => true, 'category' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete_category') {
        $stmt = $pdo->prepare("UPDATE products SET category_id = NULL WHERE category_id = :id");
        $stmt->execute([':id' => $input['id']]);
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============ UNITS ============
    if ($action === 'save_unit') {
        $id = $input['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("UPDATE units SET name=:name, short_name=:short_name WHERE id=:id");
            $stmt->execute([':id' => $id, ':name' => $input['name'], ':short_name' => $input['short_name']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO units (name, short_name) VALUES (:name, :short_name)");
            $stmt->execute([':name' => $input['name'], ':short_name' => $input['short_name']]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_unit') {
        $stmt = $pdo->prepare("SELECT * FROM units WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        echo json_encode(['success' => true, 'unit' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete_unit') {
        $stmt = $pdo->prepare("UPDATE products SET unit_id = NULL WHERE unit_id = :id");
        $stmt->execute([':id' => $input['id']]);
        $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============ SUPPLIERS ============
    if ($action === 'save_supplier') {
        $id = $input['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("UPDATE suppliers SET name=:name, phone=:phone, email=:email, address=:address WHERE id=:id");
            $stmt->execute([':id' => $id, ':name' => $input['name'], ':phone' => $input['phone'] ?? '', ':email' => $input['email'] ?? '', ':address' => $input['address'] ?? '']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address, created_at) VALUES (:name, :phone, :email, :address, NOW())");
            $stmt->execute([':name' => $input['name'], ':phone' => $input['phone'] ?? '', ':email' => $input['email'] ?? '', ':address' => $input['address'] ?? '']);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_supplier') {
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        echo json_encode(['success' => true, 'supplier' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete_supplier') {
        $stmt = $pdo->prepare("UPDATE products SET supplier_id = NULL WHERE supplier_id = :id");
        $stmt->execute([':id' => $input['id']]);
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============ CUSTOMERS ============
    if ($action === 'save_customer') {
        $id = $input['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("UPDATE customers SET name=:name, phone=:phone, email=:email, address=:address, point=:point WHERE id=:id");
            $stmt->execute([':id' => $id, ':name' => $input['name'], ':phone' => $input['phone'] ?? '', ':email' => $input['email'] ?? '', ':address' => $input['address'] ?? '', ':point' => $input['point'] ?? 0]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, point, created_at) VALUES (:name, :phone, :email, :address, :point, NOW())");
            $stmt->execute([':name' => $input['name'], ':phone' => $input['phone'] ?? '', ':email' => $input['email'] ?? '', ':address' => $input['address'] ?? '', ':point' => $input['point'] ?? 0]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_customer') {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id");
        $stmt->execute([':id' => $_GET['id']]);
        echo json_encode(['success' => true, 'customer' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete_customer') {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ============ POS: Search Products ============
    if ($action === 'search_products') {
        $q = $_GET['q'] ?? '';
        $stmt = $pdo->prepare("SELECT p.*, u.short_name FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE (p.name LIKE :q OR p.barcode LIKE :q2) AND p.status = 1 ORDER BY p.name LIMIT 50");
        $stmt->execute([':q' => "%$q%", ':q2' => "%$q%"]);
        echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
        exit;
    }

    // ============ POS: Get all active products ============
    if ($action === 'get_products') {
        $stmt = $pdo->query("SELECT p.*, u.short_name FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE p.status = 1 ORDER BY p.name");
        echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
        exit;
    }

    // ============ POS: Search Customers ============
    if ($action === 'search_customers') {
        $q = $_GET['q'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE name LIKE :q OR phone LIKE :q2 LIMIT 20");
        $stmt->execute([':q' => "%$q%", ':q2' => "%$q%"]);
        echo json_encode(['success' => true, 'customers' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
