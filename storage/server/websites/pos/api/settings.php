<?php
require_once __DIR__ . '/../config.php';
if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

try {
    $pdo = getDB();

    if ($action === 'save_user') {
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role, status, created_at, updated_at) VALUES (:name, :username, :password, :role, 1, NOW(), NOW())");
        $stmt->execute([
            ':name' => $input['name'],
            ':username' => $input['username'],
            ':password' => password_hash($input['password'], PASSWORD_DEFAULT),
            ':role' => $input['role'] ?? 'kasir',
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'reset_password') {
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->execute([
            ':password' => password_hash($input['password'], PASSWORD_DEFAULT),
            ':id' => $input['id'],
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
