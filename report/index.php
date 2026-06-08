<?php
require_once '../config.php';
check_login();

$role = $_SESSION['role'];

// Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$tipe = $_GET['tipe'] ?? '';

// Build SQL query based on filters
$query = "SELECT transactions.*, items.nama_barang, items.kode_barang, users.name as operator 
          FROM transactions 
          JOIN items ON transactions.item_id = items.id 
          JOIN users ON transactions.user_id = users.id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($start_date)) {
    $query .= " AND transactions.tanggal >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $query .= " AND transactions.tanggal <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if (!empty($tipe) && in_array($tipe, ['Masuk', 'Keluar'])) {
    $query .= " AND transactions.tipe = ?";
    $params[] = $tipe;
    $types .= "s";
}

$query .= " ORDER BY transactions.tanggal ASC, transactions.id ASC";

$stmt = mysqli_prepare($conn, $query);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="/dashboard.php" class="sidebar-brand">Gudang App</a>
        <ul class="sidebar-menu">
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><a href="/categories/index.php">Kategori Barang</a></li>
            <li><a href="/items/index.php">Data Barang</a></li>
            <?php if ($role === 'Admin' || $role === 'Petugas'): ?>
                <li><a href="/transactions/index.php">Transaksi Barang</a></li>
            <?php endif; ?>
            <?php if ($role === 'Admin'): ?>
                <li><a href="/users/index.php">Kelola User</a></li>
            <?php endif; ?>
            <li><a href="/report/index.php" class="active">Laporan</a></li>
            <li><a href="/logout.php" style="color: #e74c3c;">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="navbar">
            <h2>Laporan Transaksi Barang</h2>
            <div class="user-info">
                Halo, <strong><?= esc($_SESSION['name']) ?></strong> (Role: <?= esc($role) ?>)
            </div>
        </div>

        <!-- Filter Form (Hidden on print) -->
        <div class="card no-print" style="margin-bottom: 20px;">
            <h3>Filter Laporan</h3>
            <form action="/report/index.php" method="GET" style="margin-top: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="start_date">Tanggal Mulai</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?= esc($start_date) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">Tanggal Selesai</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?= esc($end_date) ?>">
                    </div>
                    <div class="form-group">
                        <label for="tipe">Tipe Transaksi</label>
                        <select id="tipe" name="tipe" class="form-control">
                            <option value="">-- Semua Tipe --</option>
                            <option value="Masuk" <?= ($tipe === 'Masuk') ? 'selected' : '' ?>>Masuk</option>
                            <option value="Keluar" <?= ($tipe === 'Keluar') ? 'selected' : '' ?>>Keluar</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Filter Data</button>
                    <a href="/report/index.php" class="btn btn-secondary">Reset</a>
                    <button type="button" onclick="window.print();" class="btn btn-success">Cetak Laporan</button>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <div class="report-header" style="text-align: center; margin-bottom: 30px;">
            <h1 style="font-size: 1.6rem; margin-bottom: 5px;">LAPORAN TRANSAKSI PERGUDANGAN</h1>
            <p style="color: #666;">UKK RPL RPL Warehouse Management System</p>
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                <p style="font-size: 0.9rem; font-weight: bold; margin-top: 10px;">
                    Periode: 
                    <?= !empty($start_date) ? date('d-m-Y', strtotime($start_date)) : 'Awal' ?> 
                    s/d 
                    <?= !empty($end_date) ? date('d-m-Y', strtotime($end_date)) : 'Sekarang' ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Transactions Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th style="width: 100px;">Tanggal</th>
                        <th style="width: 120px;">Kode Barang</th>
                        <th>Nama Barang</th>
                        <th style="width: 80px;">Tipe</th>
                        <th style="width: 80px; text-align: center;">Jumlah</th>
                        <th>Keterangan</th>
                        <th style="width: 150px;">Petugas/Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $total_masuk = 0;
                    $total_keluar = 0;
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            if ($row['tipe'] === 'Masuk') {
                                $total_masuk += intval($row['qty']);
                            } else {
                                $total_keluar += intval($row['qty']);
                            }
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                            <td><strong><?= esc($row['kode_barang']) ?></strong></td>
                            <td><?= esc($row['nama_barang']) ?></td>
                            <td>
                                <?php if ($row['tipe'] === 'Masuk'): ?>
                                    <span style="color: var(--success-color); font-weight: bold;">Masuk</span>
                                <?php else: ?>
                                    <span style="color: var(--danger-color); font-weight: bold;">Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-weight: bold;"><?= esc($row['qty']) ?></td>
                            <td><?= esc($row['keterangan'] ?? '-') ?></td>
                            <td><?= esc($row['operator']) ?></td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align:center;'>Tidak ada data transaksi yang cocok dengan filter.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="card">
                <h3>Ringkasan Masuk</h3>
                <div class="value" style="color: var(--success-color);"><?= esc($total_masuk) ?> <span style="font-size: 1rem; color: #666; font-weight: normal;">unit</span></div>
            </div>
            <div class="card">
                <h3>Ringkasan Keluar</h3>
                <div class="value" style="color: var(--danger-color);"><?= esc($total_keluar) ?> <span style="font-size: 1rem; color: #666; font-weight: normal;">unit</span></div>
            </div>
        </div>
        
        <!-- Print Footer signature -->
        <div class="print-only" style="display: none; margin-top: 50px; text-align: right; font-size: 0.95rem;">
            <p>Jakarta, <?= date('d F Y') ?></p>
            <br><br><br>
            <p style="text-decoration: underline; font-weight: bold;"><?= esc($_SESSION['name']) ?></p>
            <p>Staff Pergudangan</p>
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
mysqli_stmt_close($stmt);
?>
