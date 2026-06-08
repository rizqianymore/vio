<?php
require_once '../config.php';
check_login();

$role = $_SESSION['role'];
$error = '';
$success = '';

// Check permission for modifications (staff and Warehouse Manager can edit)
$can_edit = ($role === 'staff' || $role === 'Warehouse Manager');
$can_delete = ($role === 'Warehouse Manager');

// Handle Category CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Token keamanan CSRF tidak valid.";
    }

    if (empty($error)) {
        // CREATE
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name'] ?? '');
            if (empty($name)) {
                $error = "Nama kategori tidak boleh kosong.";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO categories (category_name) VALUES (?)");
                mysqli_stmt_bind_param($stmt, "s", $name);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Kategori berhasil ditambahkan.";
                } else {
                    $error = "Kategori sudah ada atau terjadi kesalahan.";
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        // UPDATE
        if (isset($_POST['edit_category'])) {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['category_name'] ?? '');
            if (empty($name)) {
                $error = "Nama kategori tidak boleh kosong.";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE categories SET category_name = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $name, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Kategori berhasil diperbarui.";
                } else {
                    $error = "Kategori sudah ada atau terjadi kesalahan.";
                }
                mysqli_stmt_close($stmt);
            }
        }

        // DELETE
        if (isset($_POST['delete_category']) && $can_delete) {
            $id = intval($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Kategori berhasil dihapus.";
            } else {
                $error = "Gagal menghapus kategori. Kategori mungkin masih digunakan oleh data barang.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Search keyword
$search = trim($_GET['search'] ?? '');

// Fetch Categories with Search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $query = "SELECT * FROM categories WHERE category_name LIKE ? ORDER BY category_name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT * FROM categories ORDER BY category_name ASC";
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
    <title>Kelola Kategori - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="/dashboard.php" class="sidebar-brand">Gudang App</a>
        <ul class="sidebar-menu">
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><a href="/categories/index.php" class="active">Kategori Barang</a></li>
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
            <h2>Kategori Barang</h2>
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

        <!-- Form Tambah (Only Admin & Petugas) -->
        <?php if ($can_edit && !isset($_GET['edit_id'])): ?>
            <div class="form-modal-inline">
                <h3>Tambah Kategori Baru</h3>
                <form action="/categories/index.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end; margin-top: 10px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
                        <label for="category_name">Nama Kategori</label>
                        <input type="text" id="category_name" name="category_name" class="form-control" placeholder="Nama Kategori" required>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Form Edit (Only Admin & Petugas) -->
        <?php 
        if ($can_edit && isset($_GET['edit_id'])) {
            $edit_id = intval($_GET['edit_id']);
            $edit_stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE id = ?");
            mysqli_stmt_bind_param($edit_stmt, "i", $edit_id);
            mysqli_stmt_execute($edit_stmt);
            $edit_res = mysqli_stmt_get_result($edit_stmt);
            if ($edit_row = mysqli_fetch_assoc($edit_res)) {
        ?>
            <div class="form-modal-inline">
                <h3>Edit Kategori</h3>
                <form action="/categories/index.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end; margin-top: 10px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    <input type="hidden" name="id" value="<?= esc($edit_row['id']) ?>">
                    <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
                        <label for="category_name_edit">Nama Kategori</label>
                        <input type="text" id="category_name_edit" name="category_name" class="form-control" value="<?= esc($edit_row['category_name']) ?>" required>
                    </div>
                    <button type="submit" name="edit_category" class="btn btn-success">Perbarui</button>
                    <a href="/categories/index.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        <?php
            }
            mysqli_stmt_close($edit_stmt);
        }
        ?>

        <!-- Search & Grid actions -->
        <div class="actions-row">
            <h3>Daftar Kategori</h3>
            <form action="/categories/index.php" method="GET" class="search-form">
                <input type="text" name="search" class="form-control" style="width: auto;" placeholder="Cari Kategori..." value="<?= esc($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="/categories/index.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Category Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">No</th>
                        <th>Nama Kategori</th>
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
                            <td><?= esc($row['category_name']) ?></td>
                            <?php if ($can_edit): ?>
                                <td style="text-align: center;">
                                    <a href="/categories/index.php?edit_id=<?= esc($row['id']) ?>" class="btn btn-success btn-sm">Edit</a>
                                    <?php if ($can_delete): ?>
                                        <form action="/categories/index.php" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini? Semua barang dengan kategori ini juga akan terhapus.');">
                                            <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                            <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                            <button type="submit" name="delete_category" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='" . ($can_edit ? 3 : 2) . "' style='text-align:center;'>Data tidak ditemukan.</td></tr>";
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
