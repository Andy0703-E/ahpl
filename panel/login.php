<?php
require_once __DIR__ . '/../config/config.php';
initDatabase();

if (isLoggedIn()) {
    header('Location: /panel/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Invalid form submission';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($username, $password)) {
            header('Location: /panel/dashboard.php');
            exit;
        }
        $error = 'Username atau password salah';
    }
}
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <style>.fa-fallback{display:none}</style>
    <link rel="stylesheet" href="/panel/assets/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <h1><i class="fas fa-server"></i> <?= APP_NAME ?></h1>
            <p class="sub">Android Hosting Panel Lite</p>

            <?php if ($error): ?>
                <div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:var(--radius-sm);margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
            <p style="text-align:center;margin-top:16px;font-size:11px;color:var(--text-muted);">AHPL v<?= APP_VERSION ?></p>
        </div>
    </div>
</body>
</html>
