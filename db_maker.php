<?php
// Secure session setup
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';
$setup_done = false;

// Check if credentials are submitted or load from config.php if exists
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'pergudangan';

if (file_exists('config.php')) {
    // We can extract current definitions from config.php dynamically
    $config_content = file_get_contents('config.php');
    if (preg_match("/define\(\s*'DB_HOST'\s*,\s*'(.*)'\s*\)/", $config_content, $matches)) {
        $db_host = $matches[1];
    }
    if (preg_match("/define\(\s*'DB_USER'\s*,\s*'(.*)'\s*\)/", $config_content, $matches)) {
        $db_user = $matches[1];
    }
    if (preg_match("/define\(\s*'DB_PASS'\s*,\s*'(.*)'\s*\)/", $config_content, $matches)) {
        $db_pass = $matches[1];
    }
    if (preg_match("/define\(\s*'DB_NAME'\s*,\s*'(.*)'\s*\)/", $config_content, $matches)) {
        $db_name = $matches[1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    $db_host = trim($_POST['db_host'] ?? $db_host);
    $db_user = trim($_POST['db_user'] ?? $db_user);
    $db_pass = $_POST['db_pass'] ?? $db_pass;
    $db_name = trim($_POST['db_name'] ?? $db_name);

    // 1. Connect to MySQL server (without DB name first, in case DB doesn't exist)
    $conn = @mysqli_connect($db_host, $db_user, $db_pass);
    
    if (!$conn) {
        $error = "Koneksi ke server MySQL gagal: " . mysqli_connect_error();
    } else {
        // 2. Create database if it doesn't exist
        $create_db_query = "CREATE DATABASE IF NOT EXISTS `" . mysqli_real_escape_string($conn, $db_name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
        if (mysqli_query($conn, $create_db_query)) {
            // Select the database
            mysqli_select_db($conn, $db_name);
            
            // 3. Read and execute database.sql
            if (file_exists('database.sql')) {
                $sql = file_get_contents('database.sql');
                
                // Remove MySQL comments
                $sql = preg_replace('/--.*?\n/', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//', '', $sql);
                
                // Split queries by semicolon
                $queries = explode(';', $sql);
                $success_count = 0;
                $fail_count = 0;
                
                foreach ($queries as $query) {
                    $query = trim($query);
                    if (!empty($query)) {
                        if (mysqli_query($conn, $query)) {
                            $success_count++;
                        } else {
                            $fail_count++;
                        }
                    }
                }
                
                // Update config.php values if they changed
                if (file_exists('config.php')) {
                    $config_content = file_get_contents('config.php');
                    $config_content = preg_replace("/define\(\s*'DB_HOST'\s*,\s*'.*'\s*\)/", "define('DB_HOST', '$db_host')", $config_content);
                    $config_content = preg_replace("/define\(\s*'DB_USER'\s*,\s*'.*'\s*\)/", "define('DB_USER', '$db_user')", $config_content);
                    $config_content = preg_replace("/define\(\s*'DB_PASS'\s*,\s*'.*'\s*\)/", "define('DB_PASS', '$db_pass')", $config_content);
                    $config_content = preg_replace("/define\(\s*'DB_NAME'\s*,\s*'.*'\s*\)/", "define('DB_NAME', '$db_name')", $config_content);
                    file_put_contents('config.php', $config_content);
                }
                
                $message = "Database berhasil dibuat! $success_count query berhasil dijalankan.";
                if ($fail_count > 0) {
                    $message .= " ($fail_count query gagal, mungkin karena tabel drop/create checks).";
                }
                $setup_done = true;
            } else {
                $error = "File database.sql tidak ditemukan di direktori root.";
            }
        } else {
            $error = "Gagal membuat database: " . mysqli_error($conn);
        }
        mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Maker Utility - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .maker-body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .maker-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            padding: 30px;
            box-sizing: border-box;
        }
        .maker-title {
            text-align: center;
            margin-bottom: 25px;
            color: #2c3e50;
        }
        .maker-title h1 {
            font-size: 1.8rem;
            margin: 0 0 5px 0;
        }
        .maker-title p {
            color: #7f8c8d;
            margin: 0;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #34495e;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #3498db;
            outline: none;
        }
        .btn-setup {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 12px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            transition: background 0.2s;
        }
        .btn-setup:hover {
            background-color: #219653;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .alert-danger {
            background-color: #fde8e8;
            color: #c53030;
            border: 1px solid #f8b4b4;
        }
        .alert-success {
            background-color: #def7ec;
            color: #03543f;
            border: 1px solid #84e1bc;
        }
        .link-login {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .link-login:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="maker-body">
    <div class="maker-card">
        <div class="maker-title">
            <h1>Database Maker</h1>
            <p>Penginstal & Pembuat Database Pergudangan</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($setup_done): ?>
            <div style="text-align: center;">
                <p style="color: #27ae60; font-size: 1.1rem; font-weight: bold; margin-bottom: 10px;">Setup Selesai!</p>
                <p style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 25px;">Akun Default: <br><strong>staff</strong> (password: staff123)<br><strong>manager</strong> (password: manager123)</p>
                <a href="/login.php" class="btn btn-setup" style="display: inline-block; text-decoration: none; text-align: center;">Lanjut ke Login Page</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="db_host">MySQL Host</label>
                    <input type="text" id="db_host" name="db_host" class="form-control" value="<?= htmlspecialchars($db_host) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_user">MySQL Username</label>
                    <input type="text" id="db_user" name="db_user" class="form-control" value="<?= htmlspecialchars($db_user) ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">MySQL Password</label>
                    <input type="password" id="db_pass" name="db_pass" class="form-control" value="<?= htmlspecialchars($db_pass) ?>">
                </div>
                <div class="form-group">
                    <label for="db_name">Nama Database</label>
                    <input type="text" id="db_name" name="db_name" class="form-control" value="<?= htmlspecialchars($db_name) ?>" required>
                </div>
                <button type="submit" name="run_setup" class="btn-setup">Buat & Inisialisasi Database</button>
            </form>
            <a href="/login.php" class="link-login">Kembali ke Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
