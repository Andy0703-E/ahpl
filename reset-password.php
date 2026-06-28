<?php
if (PHP_SAPI !== 'cli') die('CLI only');

session_save_path(sys_get_temp_dir());

require_once __DIR__ . '/config/config.php';
initDatabase();

$db = getDB();

$res = $db->query("SELECT id, username FROM users");
$users = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

if (empty($users)) {
    echo "Tidak ada user. Jalankan panel dulu lewat web untuk seeding default.\n";
    exit;
}

echo "Users:\n";
foreach ($users as $i => $u) {
    echo "  [$i] {$u['username']} (id: {$u['id']})\n";
}

$idx = $argv[1] ?? 0;
$newPass = $argv[2] ?? 'admin';

if (!isset($users[$idx])) {
    echo "Usage: php reset-password.php [index] [password]\n";
    echo "Default: index=0 password=admin\n";
    exit;
}

$user = $users[$idx];
$hash = password_hash($newPass, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE users SET password = :p, password_changed = 0 WHERE id = :id");
$stmt->bindValue(':p', $hash, SQLITE3_TEXT);
$stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
$stmt->execute();

echo "Password untuk '{$user['username']}' direset ke: $newPass\n";
echo "Login di panel, nanti akan diminta ganti password.\n";
