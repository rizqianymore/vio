<?php
require_once '../config.php';
check_login();

$role = $_SESSION['role'];
$error = '';
$success = '';

// Check permission for modifications (Admin & Petugas only)
$can_edit = ($role === 'Admin' || $role === 'Petugas');

// Create upload directory if not exists
$upload_dir = '../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    // Write index.html to prevent directory listing
    file_put_contents($upload_dir . 'index.html', '');
    // Write .htaccess to disable script execution in uploads folder
    file_put_contents($upload_dir . '.htaccess', "ForceType application/octet-stream\n<FilesMatch \"(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|pl|py|cgi|sh|asp|aspx|exe|msi|bat|cmd)$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>");
}

// Handle Item CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Token keamanan CSRF tidak valid.";
    }

    if (empty($error)) {
        // ADD ITEM
        if (isset($_POST['add_item'])) {
            $category_id = intval($_POST['category_id'] ?? 0);
            $nama_barang = trim($_POST['nama_barang'] ?? '');
            $kode_barang = trim($_POST['kode_barang'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $stok = intval($_POST['stok'] ?? 0);
            $foto_name = null;

            if (empty($nama_barang) || empty($kode_barang) || $category_id <= 0) {
                $error = "Nama barang, kode barang, dan kategori wajib diisi.";
            } else {
                // Handle File Upload
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['foto']['tmp_name'];
                    $file_size = $_FILES['foto']['size'];
                    $orig_name = $_FILES['foto']['name'];
                    
                    // Validate File Size (Max 2MB)
                    if ($file_size > 2 * 1024 * 1024) {
                        $error = "Ukuran file terlalu besar. Maksimal 2MB.";
                    } else {
                        // Validate MIME type via Magic Bytes (finfo)
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_tmp);
                        finfo_close($finfo);
                        
                        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                        
                        if (!in_array($mime_type, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
                            $error = "Format file tidak valid. Hanya JPG, JPEG, PNG, dan WEBP yang diperbolehkan.";
                        } else {
                            // Generate safe random filename
                            $foto_name = bin2hex(random_bytes(16)) . '.' . $ext;
                            if (!move_uploaded_file($file_tmp, $upload_dir . $foto_name)) {
                                $error = "Gagal mengunggah file foto.";
                                $foto_name = null;
                            }
                        }
                    }
                }

                if (empty($error)) {
                    // Check if Kode Barang already exists
                    $check_stmt = mysqli_prepare($conn, "SELECT id FROM items WHERE kode_barang = ?");
                    mysqli_stmt_bind_param($check_stmt, "s", $kode_barang);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_store_result($check_stmt);
                    
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $error = "Kode barang sudah terdaftar.";
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO items (category_id, nama_barang, kode_barang, deskripsi, stok, foto) VALUES (?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "isssis", $category_id, $nama_barang, $kode_barang, $deskripsi, $stok, $foto_name);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Barang berhasil ditambahkan.";
                        } else {
                            $error = "Gagal menyimpan data barang.";
                        }
                        mysqli_stmt_close($stmt);
                    }
                    mysqli_stmt_close($check_stmt);
                }
            }
        }

        // EDIT ITEM
        if (isset($_POST['edit_item'])) {
            $id = intval($_POST['id'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0);
            $nama_barang = trim($_POST['nama_barang'] ?? '');
            $kode_barang = trim($_POST['kode_barang'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $stok = intval($_POST['stok'] ?? 0);
            $foto_name = null;

            // Fetch current foto
            $curr_stmt = mysqli_prepare($conn, "SELECT foto FROM items WHERE id = ?");
            mysqli_stmt_bind_param($curr_stmt, "i", $id);
            mysqli_stmt_execute($curr_stmt);
            $curr_res = mysqli_stmt_get_result($curr_stmt);
            $curr_row = mysqli_fetch_assoc($curr_res);
            $foto_name = $curr_row['foto'] ?? null;
            mysqli_stmt_close($curr_stmt);

            if (empty($nama_barang) || empty($kode_barang) || $category_id <= 0) {
                $error = "Nama barang, kode barang, dan kategori wajib diisi.";
            } else {
                // Handle File Upload if new image is provided
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['foto']['tmp_name'];
                    $file_size = $_FILES['foto']['size'];
                    $orig_name = $_FILES['foto']['name'];
                    
                    if ($file_size > 2 * 1024 * 1024) {
                        $error = "Ukuran file terlalu besar. Maksimal 2MB.";
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_tmp);
                        finfo_close($finfo);
                        
                        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                        
                        if (!in_array($mime_type, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
                            $error = "Format file tidak valid. Hanya JPG, JPEG, PNG, dan WEBP yang diperbolehkan.";
                        } else {
                            // Delete old file if exists
                            if ($foto_name && file_exists($upload_dir . $foto_name)) {
                                unlink($upload_dir . $foto_name);
                            }
                            
                            $foto_name = bin2hex(random_bytes(16)) . '.' . $ext;
                            if (!move_uploaded_file($file_tmp, $upload_dir . $foto_name)) {
                                $error = "Gagal mengunggah file foto.";
                                $foto_name = $curr_row['foto'] ?? null;
                            }
                        }
                    }
                }

                if (empty($error)) {
                    // Check if Kode Barang already exists for other items
                    $check_stmt = mysqli_prepare($conn, "SELECT id FROM items WHERE kode_barang = ? AND id != ?");
                    mysqli_stmt_bind_param($check_stmt, "si", $kode_barang, $id);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_store_result($check_stmt);
                    
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $error = "Kode barang sudah terdaftar.";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE items SET category_id = ?, nama_barang = ?, kode_barang = ?, deskripsi = ?, stok = ?, foto = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "isssisi", $category_id, $nama_barang, $kode_barang, $deskripsi, $stok, $foto_name, $id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Barang berhasil diperbarui.";
                        } else {
                            $error = "Gagal memperbarui data barang.";
                        }
                        mysqli_stmt_close($stmt);
                    }
                    mysqli_stmt_close($check_stmt);
                }
            }
        }

        // DELETE ITEM
        if (isset($_POST['delete_item'])) {
            $id = intval($_POST['id'] ?? 0);
            
            // Delete associated file
            $curr_stmt = mysqli_prepare($conn, "SELECT foto FROM items WHERE id = ?");
            mysqli_stmt_bind_param($curr_stmt, "i", $id);
            mysqli_stmt_execute($curr_stmt);
            $curr_res = mysqli_stmt_get_result($curr_stmt);
            if ($curr_row = mysqli_fetch_assoc($curr_res)) {
                $foto_name = $curr_row['foto'] ?? null;
                if ($foto_name && file_exists($upload_dir . $foto_name)) {
                    unlink($upload_dir . $foto_name);
                }
            }
            mysqli_stmt_close($curr_stmt);

            $stmt = mysqli_prepare($conn, "DELETE FROM items WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Barang berhasil dihapus.";
            } else {
                $error = "Gagal menghapus data barang.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Search keyword
$search = trim($_GET['search'] ?? '');

// Fetch Items with Search
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

// Fetch all categories for forms
$categories_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
$categories = [];
while ($cat = mysqli_fetch_assoc($categories_res)) {
    $categories[] = $cat;
}

// Generate CSRF Token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Barang - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <a href="/dashboard.php" class="sidebar-brand">Gudang App</a>
        <ul class="sidebar-menu">
            <li><a href="/dashboard.php">Dashboard</a></li>
            <li><a href="/categories/index.php">Kategori Barang</a></li>
            <li><a href="/items/index.php" class="active">Data Barang</a></li>
            <?php if ($role === 'Admin' || $role === 'Petugas'): ?>
                <li><a href="/transactions/index.php">Transaksi Barang</a></li>
            <?php endif; ?>
            <?php if ($role === 'Admin'): ?>
                <li><a href="/users/index.php">Kelola User</a></li>
            <?php endif; ?>
            <li><a href="/report/index.php">Laporan</a></li>
            <li><a href="/logout.php" style="color: #e74c3c;">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="navbar">
            <h2>Data Barang</h2>
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
                <h3>Tambah Barang Baru</h3>
                <form action="/items/index.php" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="kode_barang">Kode Barang</label>
                            <input type="text" id="kode_barang" name="kode_barang" class="form-control" placeholder="Contoh: BRG-001" required>
                        </div>
                        <div class="form-group">
                            <label for="nama_barang">Nama Barang</label>
                            <input type="text" id="nama_barang" name="nama_barang" class="form-control" placeholder="Nama Barang" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="category_id">Kategori</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= esc($cat['id']) ?>"><?= esc($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stok">Stok Awal</label>
                            <input type="number" id="stok" name="stok" class="form-control" min="0" value="0" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="2" placeholder="Deskripsi Barang"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="foto">Foto Barang (JPG/PNG/WEBP, Max 2MB)</label>
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                    </div>

                    <button type="submit" name="add_item" class="btn btn-primary">Simpan Barang</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Form Edit (Only Admin & Petugas) -->
        <?php 
        if ($can_edit && isset($_GET['edit_id'])) {
            $edit_id = intval($_GET['edit_id']);
            $edit_stmt = mysqli_prepare($conn, "SELECT * FROM items WHERE id = ?");
            mysqli_stmt_bind_param($edit_stmt, "i", $edit_id);
            mysqli_stmt_execute($edit_stmt);
            $edit_res = mysqli_stmt_get_result($edit_stmt);
            if ($edit_row = mysqli_fetch_assoc($edit_res)) {
        ?>
            <div class="form-modal-inline">
                <h3>Edit Barang</h3>
                <form action="/items/index.php" method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    <input type="hidden" name="id" value="<?= esc($edit_row['id']) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="kode_barang_edit">Kode Barang</label>
                            <input type="text" id="kode_barang_edit" name="kode_barang" class="form-control" value="<?= esc($edit_row['kode_barang']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nama_barang_edit">Nama Barang</label>
                            <input type="text" id="nama_barang_edit" name="nama_barang" class="form-control" value="<?= esc($edit_row['nama_barang']) ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="category_id_edit">Kategori</label>
                            <select id="category_id_edit" name="category_id" class="form-control" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= esc($cat['id']) ?>" <?= ($cat['id'] == $edit_row['category_id']) ? 'selected' : '' ?>><?= esc($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stok_edit">Stok</label>
                            <input type="number" id="stok_edit" name="stok" class="form-control" min="0" value="<?= esc($edit_row['stok']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi_edit">Deskripsi</label>
                        <textarea id="deskripsi_edit" name="deskripsi" class="form-control" rows="2"><?= esc($edit_row['deskripsi']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="foto_edit">Foto Barang Baru (Kosongkan jika tidak diganti)</label>
                        <input type="file" id="foto_edit" name="foto" class="form-control" accept="image/*">
                        <?php if ($edit_row['foto']): ?>
                            <div style="margin-top: 10px;">
                                <p style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Foto saat ini:</p>
                                <img src="/uploads/<?= esc($edit_row['foto']) ?>" class="img-preview" alt="Foto">
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="edit_item" class="btn btn-success">Perbarui Barang</button>
                    <a href="/items/index.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        <?php
            }
            mysqli_stmt_close($edit_stmt);
        }
        ?>

        <!-- Search & Grid actions -->
        <div class="actions-row">
            <h3>Daftar Barang</h3>
            <form action="/items/index.php" method="GET" class="search-form">
                <input type="text" name="search" class="form-control" style="width: auto;" placeholder="Cari Kode, Nama, Kategori..." value="<?= esc($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="/items/index.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Items Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">No</th>
                        <th style="width: 100px;">Foto</th>
                        <th style="width: 120px;">Kode Barang</th>
                        <th>Nama Barang</th>
                        <th style="width: 150px;">Kategori</th>
                        <th style="width: 100px;">Stok</th>
                        <th>Deskripsi</th>
                        <?php if ($can_edit): ?>
                            <th style="width: 180px; text-align: center;">Aksi</th>
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
                            <td>
                                <?php if ($row['foto']): ?>
                                    <img src="/uploads/<?= esc($row['foto']) ?>" class="img-preview" alt="Foto Barang">
                                <?php else: ?>
                                    <span style="font-size: 0.8rem; color: #999;">Tidak ada foto</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= esc($row['kode_barang']) ?></strong></td>
                            <td><?= esc($row['nama_barang']) ?></td>
                            <td><?= esc($row['category_name']) ?></td>
                            <td>
                                <?php if ($row['stok'] <= 0): ?>
                                    <span style="color: var(--danger-color); font-weight: bold;">Habis</span>
                                <?php else: ?>
                                    <?= esc($row['stok']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($row['deskripsi']) ?></td>
                            <?php if ($can_edit): ?>
                                <td style="text-align: center;">
                                    <a href="/items/index.php?edit_id=<?= esc($row['id']) ?>" class="btn btn-success btn-sm">Edit</a>
                                    <form action="/items/index.php" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus barang ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                        <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                                        <button type="submit" name="delete_item" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='" . ($can_edit ? 8 : 7) . "' style='text-align:center;'>Data tidak ditemukan.</td></tr>";
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
