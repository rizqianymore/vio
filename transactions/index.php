<?php
require_once '../config.php';
check_login();

$role = $_SESSION['role'];
$error = '';
$success = '';

// Check permission for modifications (staff and Warehouse Manager can edit)
$can_edit = ($role === 'staff' || $role === 'Warehouse Manager');

// Handle Transaction Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Token keamanan CSRF tidak valid.";
    }

    if (empty($error)) {
        if (isset($_POST['add_transaction'])) {
            $item_id = intval($_POST['item_id'] ?? 0);
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
            $qty = intval($_POST['qty'] ?? 0);
            $tipe = $_POST['tipe'] ?? ''; // Masuk / Keluar
            $keterangan = trim($_POST['keterangan'] ?? '');
            $user_id = $_SESSION['user_id'];
            
            // Check supplier for incoming stock
            $supplier_id = null;
            if ($tipe === 'Masuk') {
                $supplier_id = isset($_POST['supplier_id']) && $_POST['supplier_id'] !== '' ? intval($_POST['supplier_id']) : null;
            }

            if ($item_id <= 0 || $qty <= 0 || !in_array($tipe, ['Masuk', 'Keluar'])) {
                $error = "Semua input transaksi wajib diisi dengan benar. Qty minimal 1.";
            } elseif ($tipe === 'Masuk' && empty($supplier_id)) {
                $error = "Supplier wajib dipilih untuk transaksi barang masuk.";
            } else {
                // Database transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // 1. Fetch Item Stock (FOR UPDATE to prevent race conditions)
                    $item_stmt = mysqli_prepare($conn, "SELECT stok, nama_barang FROM items WHERE id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($item_stmt, "i", $item_id);
                    mysqli_stmt_execute($item_stmt);
                    $item_res = mysqli_stmt_get_result($item_stmt);
                    $item = mysqli_fetch_assoc($item_res);
                    mysqli_stmt_close($item_stmt);

                    if (!$item) {
                        throw new Exception("Barang tidak ditemukan.");
                    }

                    $current_stock = intval($item['stok']);
                    $new_stock = $current_stock;

                    // 2. Validate outgoing quantity
                    if ($tipe === 'Keluar') {
                        if ($qty > $current_stock) {
                            throw new Exception("Stok tidak mencukupi. Stok saat ini: " . $current_stock);
                        }
                        $new_stock = $current_stock - $qty;
                    } else {
                        // Masuk
                        $new_stock = $current_stock + $qty;
                    }

                    // 3. Insert into Transactions
                    $ins_stmt = mysqli_prepare($conn, "INSERT INTO transactions (user_id, item_id, supplier_id, tanggal, qty, tipe, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($ins_stmt, "iiisiss", $user_id, $item_id, $supplier_id, $tanggal, $qty, $tipe, $keterangan);
                    
                    if (!mysqli_stmt_execute($ins_stmt)) {
                        mysqli_stmt_close($ins_stmt);
                        throw new Exception("Gagal menyimpan riwayat transaksi.");
                    }
                    mysqli_stmt_close($ins_stmt);

                    // 4. Update Item Stock
                    $upd_stmt = mysqli_prepare($conn, "UPDATE items SET stok = ? WHERE id = ?");
                    mysqli_stmt_bind_param($upd_stmt, "ii", $new_stock, $item_id);
                    
                    if (!mysqli_stmt_execute($upd_stmt)) {
                        mysqli_stmt_close($upd_stmt);
                        throw new Exception("Gagal memperbarui stok barang.");
                    }
                    mysqli_stmt_close($upd_stmt);

                    // Commit Transaction
                    mysqli_commit($conn);
                    $success = "Transaksi berhasil dicatat dan stok diperbarui.";
                } catch (Exception $e) {
                    // Rollback on any failure
                    mysqli_rollback($conn);
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Search keyword
$search = trim($_GET['search'] ?? '');

// Fetch Transactions with Search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $query = "SELECT transactions.*, items.nama_barang, items.kode_barang, users.name as operator, suppliers.name as supplier_name 
              FROM transactions 
              JOIN items ON transactions.item_id = items.id 
              JOIN users ON transactions.user_id = users.id 
              LEFT JOIN suppliers ON transactions.supplier_id = suppliers.id
              WHERE items.nama_barang LIKE ? OR items.kode_barang LIKE ? OR transactions.tipe LIKE ? OR transactions.keterangan LIKE ? OR suppliers.name LIKE ?
              ORDER BY transactions.tanggal DESC, transactions.id DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT transactions.*, items.nama_barang, items.kode_barang, users.name as operator, suppliers.name as supplier_name 
              FROM transactions 
              JOIN items ON transactions.item_id = items.id 
              JOIN users ON transactions.user_id = users.id 
              LEFT JOIN suppliers ON transactions.supplier_id = suppliers.id
              ORDER BY transactions.tanggal DESC, transactions.id DESC";
    $result = mysqli_query($conn, $query);
}

// Fetch active items list for form dropdown
$items_res = mysqli_query($conn, "SELECT id, nama_barang, kode_barang, stok FROM items ORDER BY nama_barang ASC");
$items = [];
while ($it = mysqli_fetch_assoc($items_res)) {
    $items[] = $it;
}

// Fetch active suppliers list for form dropdown
$suppliers_res = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name ASC");
$suppliers = [];
while ($sup = mysqli_fetch_assoc($suppliers_res)) {
    $suppliers[] = $sup;
}

// Generate CSRF Token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Barang - UKK RPL Pergudangan</title>
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
            <li><a href="/transactions/index.php" class="active">Transaksi Barang</a></li>
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
            <h2>Transaksi Masuk / Keluar</h2>
            <div class="user-info">
                Halo, <strong><?= esc($_SESSION['name']) ?></strong> (Role: <?= esc($role) ?>)
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= esc($success) ?></div>
        <?php endif; ?>

        <!-- Form Entry Transaksi (Only staff and Warehouse Manager) -->
        <?php if ($can_edit): ?>
            <div class="form-modal-inline">
                <h3>Input Transaksi Barang</h3>
                <form action="/transactions/index.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="item_id">Pilih Barang</label>
                            <select id="item_id" name="item_id" class="form-control" required>
                                <option value="">-- Pilih Barang --</option>
                                <?php foreach ($items as $it): ?>
                                    <option value="<?= esc($it['id']) ?>"><?= esc($it['nama_barang']) ?> (Kode: <?= esc($it['kode_barang']) ?> | Stok: <?= esc($it['stok']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tanggal">Tanggal Transaksi</label>
                            <input type="date" id="tanggal" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="tipe">Tipe Transaksi</label>
                            <select id="tipe" name="tipe" class="form-control" onchange="toggleSupplierField()" required>
                                <option value="Masuk">Masuk (Stok Bertambah)</option>
                                <option value="Keluar">Keluar (Stok Berkurang)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="qty">Jumlah (Quantity)</label>
                            <input type="number" id="qty" name="qty" class="form-control" min="1" required>
                        </div>
                    </div>

                    <!-- Supplier field: only required for Incoming Stock ('Masuk') -->
                    <div class="form-group" id="supplier-group">
                        <label for="supplier_id">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="form-control">
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= esc($sup['id']) ?>"><?= esc($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="keterangan">Keterangan / Catatan</label>
                        <textarea id="keterangan" name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>

                    <button type="submit" name="add_transaction" class="btn btn-primary">Simpan Transaksi</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Search & Grid actions -->
        <div class="actions-row">
            <h3>Riwayat Transaksi</h3>
            <form action="/transactions/index.php" method="GET" class="search-form">
                <input type="text" name="search" class="form-control" style="width: auto;" placeholder="Cari Kode, Nama, Supplier, Tipe..." value="<?= esc($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="/transactions/index.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th style="width: 120px;">Tanggal</th>
                        <th style="width: 120px;">Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Supplier</th>
                        <th style="width: 100px;">Jumlah</th>
                        <th style="width: 100px;">Tipe</th>
                        <th>Keterangan</th>
                        <th style="width: 150px;">Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if (isset($result) && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                            <td><strong><?= esc($row['kode_barang']) ?></strong></td>
                            <td><?= esc($row['nama_barang']) ?></td>
                            <td><?= esc($row['supplier_name'] ?? '-') ?></td>
                            <td style="font-weight: bold;"><?= esc($row['qty']) ?></td>
                            <td>
                                <?php if ($row['tipe'] === 'Masuk'): ?>
                                    <span style="color: var(--success-color); font-weight: bold;">Masuk</span>
                                <?php else: ?>
                                    <span style="color: var(--danger-color); font-weight: bold;">Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($row['keterangan'] ?? '-') ?></td>
                            <td><?= esc($row['operator']) ?></td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align:center;'>Belum ada data transaksi.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function toggleSupplierField() {
        const tipe = document.getElementById('tipe').value;
        const supplierGroup = document.getElementById('supplier-group');
        const supplierSelect = document.getElementById('supplier_id');
        
        if (tipe === 'Masuk') {
            supplierGroup.style.display = 'block';
            supplierSelect.setAttribute('required', 'required');
        } else {
            supplierGroup.style.display = 'none';
            supplierSelect.removeAttribute('required');
            supplierSelect.value = '';
        }
    }
    
    // Run on load to set initial state
    document.addEventListener('DOMContentLoaded', toggleSupplierField);
    </script>
</body>
</html>
<?php
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
?>
