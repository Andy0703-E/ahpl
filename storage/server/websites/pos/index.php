<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn() && (!isset($_GET['page']) || $_GET['page'] !== 'login')) {
    redirect('?page=login');
}

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['login','logout','dashboard','products','categories','units','suppliers','customers',
            'sales','sales_history','purchases','stock','reports','settings'];

if (!in_array($page, $allowed)) $page = 'dashboard';

if ($page === 'logout') {
    $_SESSION = [];
    setcookie(session_name(), '', time() - 3600, '/');
    redirect('?page=login');
}

if (isLoggedIn() && $page === 'login') $page = 'dashboard';

$pageTitle = [
    'dashboard' => 'Dashboard',
    'products' => 'Produk',
    'categories' => 'Kategori',
    'units' => 'Satuan',
    'suppliers' => 'Supplier',
    'customers' => 'Pelanggan',
    'sales' => 'POS Kasir',
    'sales_history' => 'Riwayat Penjualan',
    'purchases' => 'Pembelian',
    'stock' => 'Stok',
    'reports' => 'Laporan',
    'settings' => 'Pengaturan',
];

if ($page === 'login') {
    require __DIR__ . '/login.php';
    exit;
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle[$page] ?> - POS AHPL</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="sidebar">
    <div class="sidebar-header">
        <h2>POS AHPL</h2>
        <div class="user-info">
            <span class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></span>
            <span><?= escape($_SESSION['user_name'] ?? 'User') ?></span>
            <small><?= escape($_SESSION['user_role'] ?? '') ?></small>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="?page=dashboard" class="<?= setActive($page,'dashboard') ?>"><i class="icon">&#9681;</i> Dashboard</a>
        <a href="?page=sales" class="<?= setActive($page,'sales') ?>"><i class="icon">&#9741;</i> POS Kasir</a>

        <div class="menu-label">Master Data</div>
        <a href="?page=products" class="<?= setActive($page,'products') ?>"><i class="icon">&#9744;</i> Produk</a>
        <a href="?page=categories" class="<?= setActive($page,'categories') ?>"><i class="icon">&#9776;</i> Kategori</a>
        <a href="?page=units" class="<?= setActive($page,'units') ?>"><i class="icon">&#9881;</i> Satuan</a>
        <a href="?page=suppliers" class="<?= setActive($page,'suppliers') ?>"><i class="icon">&#9782;</i> Supplier</a>
        <a href="?page=customers" class="<?= setActive($page,'customers') ?>"><i class="icon">&#9783;</i> Pelanggan</a>

        <div class="menu-label">Transaksi</div>
        <a href="?page=sales_history" class="<?= setActive($page,'sales_history') ?>"><i class="icon">&#9776;</i> Penjualan</a>
        <a href="?page=purchases" class="<?= setActive($page,'purchases') ?>"><i class="icon">&#9786;</i> Pembelian</a>
        <a href="?page=stock" class="<?= setActive($page,'stock') ?>"><i class="icon">&#9744;</i> Stok</a>

        <div class="menu-label">Laporan</div>
        <a href="?page=reports" class="<?= setActive($page,'reports') ?>"><i class="icon">&#9776;</i> Laporan</a>
        <a href="?page=settings" class="<?= setActive($page,'settings') ?>"><i class="icon">&#9881;</i> Pengaturan</a>
    </div>
    <div class="sidebar-footer">
        <a href="?page=logout"><i class="icon">&#9747;</i> Logout</a>
    </div>
</nav>
<main class="main-content">
    <div class="page-header">
        <h1><?= $pageTitle[$page] ?></h1>
        <div class="header-actions" id="headerActions"></div>
    </div>
    <div class="page-content">
        <?php
        $pageFile = __DIR__ . '/pages/' . $page . '.php';
        if (file_exists($pageFile)) {
            require $pageFile;
        } else {
            echo '<p>Halaman tidak ditemukan.</p>';
        }
        ?>
    </div>
</main>
<script src="assets/script.js"></script>
</body>
</html>
