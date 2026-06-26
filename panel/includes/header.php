<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
initDatabase();
requireLogin();

$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$csrfToken = generateCSRFToken();

// Check if password needs changing
$forcePasswordChange = needsPasswordChange();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <style>.fa-fallback{display:none}</style>
    <link rel="stylesheet" href="/panel/assets/style.css">
</head>
<body>
    <?php if ($forcePasswordChange): ?>
    <div class="modal-overlay active" id="forcePasswordModal" style="z-index:9999;">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Ganti Password</h3>
            </div>
            <div class="modal-body">
                <p style="margin-bottom:14px;font-size:13px;color:#666;">Anda menggunakan password default. Silakan ganti password terlebih dahulu.</p>
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" class="form-control" id="newPassword" placeholder="Minimal 6 karakter">
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <input type="password" class="form-control" id="confirmPassword" placeholder="Ulangi password">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="changeForcePassword()">Simpan</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <h2><i class="fas fa-server"></i> <?= APP_NAME ?></h2>
                <small>Android Hosting Panel Lite</small>
            </div>
            <ul class="sidebar-menu">
                <li><a href="/panel/dashboard.php" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="/panel/websites.php" class="<?= $currentPage === 'websites' ? 'active' : '' ?>"><i class="fas fa-globe"></i> Websites</a></li>
                <li><a href="/panel/files.php" class="<?= $currentPage === 'files' ? 'active' : '' ?>"><i class="fas fa-folder"></i> File Manager</a></li>
                <li><a href="/panel/services.php" class="<?= $currentPage === 'services' ? 'active' : '' ?>"><i class="fas fa-play-circle"></i> Services</a></li>
                <li><a href="/panel/database.php" class="<?= $currentPage === 'database' ? 'active' : '' ?>"><i class="fas fa-database"></i> Database</a></li>
                <li><a href="/panel/editor.php" class="<?= $currentPage === 'editor' ? 'active' : '' ?>"><i class="fas fa-code"></i> Code Editor</a></li>
                <li><a href="/panel/settings.php" class="<?= $currentPage === 'settings' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings</a></li>
                <div class="divider"></div>
                <li><a href="/panel/api/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h3><?= sanitize($pageTitle ?? 'Dashboard') ?></h3>
                <span class="user-info"><i class="fas fa-user"></i> <?= sanitize($user['username']) ?></span>
            </header>
            <div class="content">
