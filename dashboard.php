<?php
require_once 'config.php';
check_login();

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$name = $_SESSION['name'];

// Fetch Statistics
$total_products = 0;
$total_suppliers = 0;
$total_incoming_stock = 0;
$total_outgoing_stock = 0;

// Total Products
if ($result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM items")) {
    $row = mysqli_fetch_assoc($result);
    $total_products = $row['cnt'];
}

// Total Suppliers
if ($result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM suppliers")) {
    $row = mysqli_fetch_assoc($result);
    $total_suppliers = $row['cnt'];
}

// Total Incoming Stock
if ($result = mysqli_query($conn, "SELECT SUM(qty) as sum_qty FROM transactions WHERE tipe = 'Masuk'")) {
    $row = mysqli_fetch_assoc($result);
    $total_incoming_stock = $row['sum_qty'] ?? 0;
}

// Total Outgoing Stock
if ($result = mysqli_query($conn, "SELECT SUM(qty) as sum_qty FROM transactions WHERE tipe = 'Keluar'")) {
    $row = mysqli_fetch_assoc($result);
    $total_outgoing_stock = $row['sum_qty'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="/dashboard.php" class="sidebar-brand">Gudang App</a>
        <ul class="sidebar-menu">
            <li><a href="/dashboard.php" class="active">Dashboard</a></li>
            <li><a href="/categories/index.php">Kategori Barang</a></li>
            <li><a href="/suppliers/index.php">Data Supplier</a></li>
            <li><a href="/items/index.php">Data Barang</a></li>
            <li><a href="/transactions/index.php">Transaksi Barang</a></li>
            <?php if ($role === 'Warehouse Manager'): ?>
                <li><a href="/users/index.php">Kelola User</a></li>
            <?php endif; ?>
            <li><a href="/report/index.php">Laporan</a></li>
            <li><a href="/logout.php" style="color: #e74c3c;">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="navbar">
            <h2>Dashboard Utama</h2>
            <div class="user-info">
                Halo, <strong><?= esc($name) ?></strong> (Role: <span style="text-decoration: underline;"><?= esc($role) ?></span>)
            </div>
        </div>

        <div class="card-grid">
            <div class="card">
                <h3>Total Products</h3>
                <div class="value"><?= esc($total_products) ?></div>
            </div>
            <div class="card">
                <h3>Total Suppliers</h3>
                <div class="value"><?= esc($total_suppliers) ?></div>
            </div>
            <div class="card">
                <h3>Total Incoming Stock</h3>
                <div class="value"><?= esc($total_incoming_stock) ?></div>
            </div>
            <div class="card">
                <h3>Total Outgoing Stock</h3>
                <div class="value"><?= esc($total_outgoing_stock) ?></div>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3>Selamat Datang</h3>
            <p style="line-height: 1.6; margin-top: 10px;">
                Selamat datang di Aplikasi Manajemen Pergudangan UKK RPL. Anda masuk sebagai <strong><?= esc($role) ?></strong>. 
                Gunakan menu di sebelah kiri untuk menavigasi aplikasi, melakukan pengelolaan data barang, mengelola kategori, 
                memproses transaksi masuk/keluar, dan mencetak laporan inventaris.
            </p>
        </div>
    </div>
</body>
</html>
