<?php
if (PHP_SAPI !== 'cli') die('CLI only');

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = 'ahpl123';
$dbName = 'db_pos';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "Gagal konek ke MariaDB: " . $e->getMessage() . "\n";
    echo "Pastikan MariaDB sudah running.\n";
    exit(1);
}

$stmt = $pdo->query("SELECT id, username, name, role FROM users ORDER BY id");
$users = $stmt->fetchAll();

if (empty($users)) {
    echo "Tidak ada user di database POS.\n";
    exit;
}

echo "Users POS:\n";
foreach ($users as $i => $u) {
    echo "  [$i] {$u['username']} - {$u['name']} ({$u['role']})\n";
}

$idx = isset($argv[1]) ? (int)$argv[1] : 0;
$newPass = $argv[2] ?? 'password';

if (!isset($users[$idx])) {
    echo "Usage: php reset-pos-password.php [index] [password]\n";
    echo "Default: index=0 password=password\n";
    exit;
}

$u = $users[$idx];
$hash = password_hash($newPass, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
$stmt->execute([':p' => $hash, ':id' => $u['id']]);

echo "Password untuk '{$u['username']}' ({$u['name']}) direset ke: $newPass\n";
