<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) redirect('?page=dashboard');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u AND status = 1 LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_username'] = $user['username'];
            redirect('?page=dashboard');
        } else {
            $error = 'Username atau password salah';
        }
    } catch (Exception $e) {
        $error = 'Koneksi database gagal: ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - POS AHPL</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-container">
    <div class="login-box">
        <h1>POS AHPL</h1>
        <p class="login-subtitle">Point of Sale System</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= escape($error) ?></div>
        <?php endif; ?>
        <form method="post" class="login-form">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
    </div>
</div>
</body>
</html>
