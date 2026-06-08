<?php
require_once '../config.php';
// Restrict to Warehouse Manager only
check_role(['Warehouse Manager']);

$error = '';
$success = '';

// Handle User CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Token keamanan CSRF tidak valid.";
    }

    if (empty($error)) {
        // ADD USER
        if (isset($_POST['add_user'])) {
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name) || empty($username) || empty($password)) {
                $error = "Nama, Username, dan Password wajib diisi.";
            } elseif (strlen($password) < 8) {
                $error = "Password minimal harus memiliki panjang 8 karakter.";
            } else {
                // Check if username already exists
                $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
                mysqli_stmt_bind_param($check_stmt, "s", $username);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Username sudah terdaftar.";
                } else {
                    // Hash Password with Bcrypt
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    
                    $stmt = mysqli_prepare($conn, "INSERT INTO users (name, username, password, role, phone) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "sssss", $name, $username, $hashed_password, $role, $phone);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "User berhasil ditambahkan.";
                    } else {
                        $error = "Gagal menyimpan user.";
                    }
                    mysqli_stmt_close($stmt);
                }
                mysqli_stmt_close($check_stmt);
            }
        }

        // EDIT USER
        if (isset($_POST['edit_user'])) {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            $phone = trim($_POST['phone'] ?? '');

            // Prevent self-demotion or self-deletion from Warehouse Manager to maintain system availability
            if ($id === $_SESSION['user_id'] && $role !== 'Warehouse Manager') {
                $error = "Anda tidak dapat mengubah role Anda sendiri dari Warehouse Manager.";
            }

            if (empty($name) || empty($username)) {
                $error = "Nama dan Username wajib diisi.";
            }

            if (empty($error)) {
                // Check if username already exists for other users
                $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
                mysqli_stmt_bind_param($check_stmt, "si", $username, $id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Username sudah terdaftar.";
                } else {
                    if (!empty($password)) {
                        // If password is changed
                        if (strlen($password) < 8) {
                            $error = "Password baru minimal harus memiliki panjang 8 karakter.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, username = ?, password = ?, role = ?, phone = ? WHERE id = ?");
                            mysqli_stmt_bind_param($stmt, "sssssi", $name, $username, $hashed_password, $role, $phone, $id);
                        }
                    } else {
                        // Keep current password
                        $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, username = ?, role = ?, phone = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "ssssi", $name, $username, $role, $phone, $id);
                    }

                    if (empty($error)) {
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "User berhasil diperbarui.";
                        } else {
                            $error = "Gagal memperbarui data user.";
                        }
                        mysqli_stmt_close($stmt);
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        }

        // DELETE USER
        if (isset($_POST['delete_user'])) {
            $id = intval($_POST['id'] ?? 0);
            
            // Prevent deleting current logged-in user
            if ($id === $_SESSION['user_id']) {
                $error = "Anda tidak dapat menghapus akun Anda sendiri.";
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "User berhasil dihapus.";
                } else {
                    $error = "Gagal menghapus user.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Search keyword
$search = trim($_GET['search'] ?? '');

// Fetch Users with Search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $query = "SELECT * FROM users WHERE name LIKE ? OR username LIKE ? OR role LIKE ? ORDER BY name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $search_param, $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT * FROM users ORDER BY name ASC";
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
    <title>Kelola User - UKK RPL Pergudangan</title>
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
            <li><a href="/users/index.php" class="active">Kelola User</a></li>
            <li><a href="/report/index.php">Laporan</a></li>
            <li><a href="/logout.php" style="color: #e74c3c;">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="navbar">
            <h2>Manajemen User</h2>
            <div class="user-info">
                Halo, <strong><?= esc($_SESSION['name']) ?></strong> (Role: <?= esc($_SESSION['role']) ?>)
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= esc($success) ?></div>
        <?php endif; ?>

        <!-- Form Tambah User -->
        <?php if (!isset($_GET['edit_id'])): ?>
            <div class="form-modal-inline">
                <h3>Tambah User Baru</h3>
                <form action="/users/index.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="name">Nama Lengkap</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Nama Lengkap" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Username" required autocomplete="username">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="password">Password (Min. 8 karakter)</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" required autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="staff">staff</option>
                                <option value="Warehouse Manager">Warehouse Manager</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">No. Telepon / HP</label>
                        <input type="text" id="phone" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>

                    <button type="submit" name="add_user" class="btn btn-primary">Simpan User</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Form Edit User -->
        <?php 
        if (isset($_GET['edit_id'])) {
            $edit_id = intval($_GET['edit_id']);
            $edit_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
            mysqli_stmt_bind_param($edit_stmt, "i", $edit_id);
            mysqli_stmt_execute($edit_stmt);
            $edit_res = mysqli_stmt_get_result($edit_stmt);
            if ($edit_row = mysqli_fetch_assoc($edit_res)) {
        ?>
            <div class="form-modal-inline">
                <h3>Edit User</h3>
                <form action="/users/index.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    <input type="hidden" name="id" value="<?= esc($edit_row['id']) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="name_edit">Nama Lengkap</label>
                            <input type="text" id="name_edit" name="name" class="form-control" value="<?= esc($edit_row['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="username_edit">Username</label>
                            <input type="text" id="username_edit" name="username" class="form-control" value="<?= esc($edit_row['username']) ?>" required autocomplete="username">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="password_edit">Password Baru (Kosongkan jika tidak diganti)</label>
                            <input type="password" id="password_edit" name="password" class="form-control" placeholder="Minimal 8 karakter" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="role_edit">Role</label>
                            <select id="role_edit" name="role" class="form-control" required>
                                <option value="staff" <?= ($edit_row['role'] === 'staff') ? 'selected' : '' ?>>staff</option>
                                <option value="Warehouse Manager" <?= ($edit_row['role'] === 'Warehouse Manager') ? 'selected' : '' ?>>Warehouse Manager</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone_edit">No. Telepon / HP</label>
                        <input type="text" id="phone_edit" name="phone" class="form-control" value="<?= esc($edit_row['phone']) ?>">
                    </div>

                    <button type="submit" name="edit_user" class="btn btn-success">Perbarui User</button>
                    <a href="/users/index.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        <?php
            }
            mysqli_stmt_close($edit_stmt);
        }
        ?>

        <!-- Search & Grid actions -->
        <div class="actions-row">
            <h3>Daftar User</h3>
            <form action="/users/index.php" method="GET" class="search-form">
                <input type="text" name="search" class="form-control" style="width: auto;" placeholder="Cari nama, username, role..." value="<?= esc($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="/users/index.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">No</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>No. Telepon</th>
                        <th style="width: 200px; text-align: center;">Aksi</th>
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
                            <td><?= esc($row['username']) ?></td>
                            <td><span style="font-weight: 500;"><?= esc($row['role']) ?></span></td>
                            <td><?= esc($row['phone'] ?? '-') ?></td>
                            <td style="text-align: center;">
                                <a href="/users/index.php?edit_id=<?= esc($row['id']) ?>" class="btn btn-success btn-sm">Edit</a>
                                <?php if ($row['id'] !== $_SESSION['user_id']): ?>
                                    <form action="/users/index.php" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:0.8rem; color:#999; font-style:italic;">Sedang Aktif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>Data tidak ditemukan.</td></tr>";
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
