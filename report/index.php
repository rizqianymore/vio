<?php
require_once '../config.php';
check_login();

$role = $_SESSION['role'];

// Search keyword
$search = trim($_GET['search'] ?? '');

// Build SQL query based on search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $query = "SELECT items.*, categories.category_name 
              FROM items 
              JOIN categories ON items.category_id = categories.id 
              WHERE items.nama_barang LIKE ? OR items.kode_barang LIKE ? OR categories.category_name LIKE ?
              ORDER BY items.nama_barang ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $search_param, $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT items.*, categories.category_name 
              FROM items 
              JOIN categories ON items.category_id = categories.id 
              ORDER BY items.nama_barang ASC";
    $result = mysqli_query($conn, $query);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Barang - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="/dashboard.php" class="sidebar-brand">Gudang App</a>
        <ul class="sidebar-menu">
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><a href="/categories/index.php">Kategori Barang</a></li>
            <li><a href="/suppliers/index.php">Data Supplier</a></li>
            <li><a href="/items/index.php">Data Barang</a></li>
            <li><a href="/transactions/index.php">Transaksi Barang</a></li>
            <?php if ($role === 'Warehouse Manager'): ?>
                <li><a href="/users/index.php">Kelola User</a></li>
            <?php endif; ?>
            <li><a href="/report/index.php" class="active">Laporan</a></li>
            <li><a href="/logout.php" style="color: #e74c3c;">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="navbar">
            <h2>Laporan Stok Barang</h2>
            <div class="user-info">
                Halo, <strong><?= esc($_SESSION['name']) ?></strong> (Role: <?= esc($role) ?>)
            </div>
        </div>

        <!-- Filter Form (Hidden on print) -->
        <div class="card no-print" style="margin-bottom: 20px;">
            <h3>Pencarian & Cetak</h3>
            <form action="/report/index.php" method="GET" style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                <input type="text" name="search" class="form-control" style="width: auto; flex-grow: 1; margin-bottom: 0;" placeholder="Cari Kode, Nama, Kategori..." value="<?= esc($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="/report/index.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
                <button type="button" onclick="window.print();" class="btn btn-success">Cetak Laporan</button>
            </form>
        </div>

        <!-- Report Content -->
        <div class="report-header" style="text-align: center; margin-bottom: 30px;">
            <h1 style="font-size: 1.6rem; margin-bottom: 5px;">LAPORAN STOK BARANG</h1>
            <p style="color: #666;">UKK RPL Warehouse Management System</p>
            <p style="font-size: 0.9rem; margin-top: 5px; color: #888;">Dicetak tanggal: <?= date('d-m-Y H:i') ?></p>
        </div>

        <!-- Items Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th style="width: 150px;">Kode Barang</th>
                        <th>Nama Barang</th>
                        <th style="width: 200px;">Kategori</th>
                        <th style="width: 150px;">Harga</th>
                        <th style="width: 120px; text-align: center;">Stok Saat Ini</th>
                        <th style="width: 150px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $total_stock_all = 0;
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $total_stock_all += intval($row['stok']);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= esc($row['kode_barang']) ?></strong></td>
                            <td><?= esc($row['nama_barang']) ?></td>
                            <td><?= esc($row['category_name']) ?></td>
                            <td>Rp <?= number_format($row['price'], 0, ',', '.') ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= esc($row['stok']) ?></td>
                            <td>
                                <?php if ($row['stok'] <= 0): ?>
                                    <span style="color: var(--danger-color); font-weight: bold;">Habis</span>
                                <?php elseif ($row['stok'] < 10): ?>
                                    <span style="color: #f39c12; font-weight: bold;">Menipis</span>
                                <?php else: ?>
                                    <span style="color: var(--success-color); font-weight: bold;">Tersedia</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center;'>Tidak ada data barang.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 25px; display: grid; grid-template-columns: 1fr; gap: 20px;">
            <div class="card">
                <h3>Total Akumulasi Stok Barang</h3>
                <div class="value" style="color: var(--primary-color);"><?= esc($total_stock_all) ?> <span style="font-size: 1rem; color: #666; font-weight: normal;">unit</span></div>
            </div>
        </div>
        
        <!-- Print Footer signature -->
        <div class="print-only" style="display: none; margin-top: 50px; text-align: right; font-size: 0.95rem;">
            <p>Jakarta, <?= date('d F Y') ?></p>
            <br><br><br>
            <p style="text-decoration: underline; font-weight: bold;"><?= esc($_SESSION['name']) ?></p>
            <p>Warehouse Manager</p>
        </div>
    </div>

    <style>
        /* Styles specific for printing signature visibility control */
        @media print {
            .print-only {
                display: block !important;
            }
        }
    </style>
</body>
</html>
<?php
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
?>
