<?php
require_once '../config.php';
check_login();

$role = $_SESSION['role'];
$error = '';
$success = '';

// Check permission for modifications (staff and Warehouse Manager can edit)
$can_edit = ($role === 'staff' || $role === 'Warehouse Manager');
$can_delete = ($role === 'Warehouse Manager');

// Handle Supplier CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Token keamanan CSRF tidak valid.";
    }

    if (empty($error)) {
        // CREATE
        if (isset($_POST['add_supplier'])) {
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($name) || empty($address) || empty($phone) || empty($email)) {
                $error = "Semua input wajib diisi.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid.";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO suppliers (name, address, phone, email) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $name, $address, $phone, $email);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Supplier berhasil ditambahkan.";
                } else {
                    $error = "Terjadi kesalahan saat menambahkan supplier.";
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        // UPDATE
        if (isset($_POST['edit_supplier'])) {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($name) || empty($address) || empty($phone) || empty($email)) {
                $error = "Semua input wajib diisi.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE suppliers SET name = ?, address = ?, phone = ?, email = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssssi", $name, $address, $phone, $email, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Supplier berhasil diperbarui.";
                } else {
                    $error = "Terjadi kesalahan saat memperbarui supplier.";
                }
                mysqli_stmt_close($stmt);
            }
        }

        // DELETE
        if (isset($_POST['delete_supplier']) && $can_delete) {
            $id = intval($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Supplier berhasil dihapus.";
            } else {
                $error = "Gagal menghapus supplier. Supplier mungkin masih dirujuk dalam transaksi.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Search keyword
$search = trim($_GET['search'] ?? '');

// Fetch Suppliers with Search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $query = "SELECT * FROM suppliers WHERE name LIKE ? OR address LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT * FROM suppliers ORDER BY name ASC";
    $result = mysqli_query($conn, $query);
}

// Generate CSRF Token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Supplier - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="/dashboard.php" class="sidebar-brand">Gudang App</a>
        <ul class="sidebar-menu">
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><a href="/categories/index.php">Kategori Barang</a></li>
            <li><a href="/suppliers/index.php" class="active">Data Supplier</a></li>
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
            <h2>Data Supplier</h2>
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

        <!-- Form Tambah Supplier (Only staff & Warehouse Manager) -->
        <?php if ($can_edit && !isset($_GET['edit_id'])): ?>
            <div class="form-modal-inline">
                <h3>Tambah Supplier Baru</h3>
                <form action="/suppliers/index.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="name">Nama Supplier</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Nama Supplier" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="supplier@example.com" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="phone">No. Telepon</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="08xxxxxxxxxx" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Alamat</label>
                            <input type="text" id="address" name="address" class="form-control" placeholder="Alamat Lengkap" required>
                        </div>
                    </div>

                    <button type="submit" name="add_supplier" class="btn btn-primary">Simpan Supplier</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Form Edit Supplier (Only staff & Warehouse Manager) -->
        <?php 
        if ($can_edit && isset($_GET['edit_id'])) {
            $edit_id = intval($_GET['edit_id']);
            $edit_stmt = mysqli_prepare($conn, "SELECT * FROM suppliers WHERE id = ?");
            mysqli_stmt_bind_param($edit_stmt, "i", $edit_id);
            mysqli_stmt_execute($edit_stmt);
            $edit_res = mysqli_stmt_get_result($edit_stmt);
            if ($edit_row = mysqli_fetch_assoc($edit_res)) {
        ?>
            <div class="form-modal-inline">
                <h3>Edit Supplier</h3>
                <form action="/suppliers/index.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    <input type="hidden" name="id" value="<?= esc($edit_row['id']) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="name_edit">Nama Supplier</label>
                            <input type="text" id="name_edit" name="name" class="form-control" value="<?= esc($edit_row['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email_edit">Email</label>
                            <input type="email" id="email_edit" name="email" class="form-control" value="<?= esc($edit_row['email']) ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="phone_edit">No. Telepon</label>
                            <input type="text" id="phone_edit" name="phone" class="form-control" value="<?= esc($edit_row['phone']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="address_edit">Alamat</label>
                            <input type="text" id="address_edit" name="address" class="form-control" value="<?= esc($edit_row['address']) ?>" required>
                        </div>
                    </div>

                    <button type="submit" name="edit_supplier" class="btn btn-success">Perbarui Supplier</button>
                    <a href="/suppliers/index.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        <?php
            }
            mysqli_stmt_close($edit_stmt);
        }
        ?>

        <!-- Search & Grid actions -->
        <div class="actions-row">
            <h3>Daftar Supplier</h3>
            <form action="/suppliers/index.php" method="GET" class="search-form">
                <input type="text" name="search" class="form-control" style="width: auto;" placeholder="Cari Supplier..." value="<?= esc($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="/suppliers/index.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Supplier Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">No</th>
                        <th>Nama Supplier</th>
                        <th>Alamat</th>
                        <th>No. Telepon</th>
                        <th>Email</th>
                        <?php if ($can_edit): ?>
                            <th style="width: 200px; text-align: center;">Aksi</th>
                        <?php endif; ?>
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
                            <td><?= esc($row['name']) ?></td>
                            <td><?= esc($row['address']) ?></td>
                            <td><?= esc($row['phone']) ?></td>
                            <td><?= esc($row['email']) ?></td>
                            <?php if ($can_edit): ?>
                                <td style="text-align: center;">
                                    <a href="/suppliers/index.php?edit_id=<?= esc($row['id']) ?>" class="btn btn-success btn-sm">Edit</a>
                                    <?php if ($can_delete): ?>
                                        <form action="/suppliers/index.php" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus supplier ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                            <button type="submit" name="delete_supplier" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='" . ($can_edit ? 6 : 5) . "' style='text-align:center;'>Data tidak ditemukan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
<?php
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
?>
