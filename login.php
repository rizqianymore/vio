<?php
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error = '';

// Check Lockout
if (isset($_SESSION['lockout_time'])) {
    if (time() < $_SESSION['lockout_time']) {
        $remaining = $_SESSION['lockout_time'] - time();
        $error = "Terlalu banyak percobaan login salah. Silakan coba lagi dalam " . $remaining . " detik.";
    } else {
        unset($_SESSION['login_attempts']);
        unset($_SESSION['lockout_time']);
    }
}
if (!isset($_SESSION['lockout_time']) && isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3) {
    unset($_SESSION['login_attempts']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Verification
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Token keamanan CSRF tidak valid.";
    }

    // If not locked out and CSRF is valid
    if (empty($error)) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Username dan Password wajib diisi.";
        } else {
            // Prepared Statement to get user
            $query = "SELECT * FROM users WHERE username = ? LIMIT 1";
            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($user = mysqli_fetch_assoc($result)) {
                    // Check password
                    if (password_verify($password, $user['password'])) {
                        // Reset login attempts
                        unset($_SESSION['login_attempts']);
                        unset($_SESSION['lockout_time']);
                        
                        // Set Session Data
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Regenerate Session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        header("Location: /dashboard.php");
                        exit;
                    }
                }
                
                // Track failed attempt
                if (!isset($_SESSION['login_attempts'])) {
                    $_SESSION['login_attempts'] = 1;
                } else {
                    $_SESSION['login_attempts']++;
                }

                if ($_SESSION['login_attempts'] >= 3) {
                    $_SESSION['lockout_time'] = time() + 30; // 30 seconds lockout
                    $error = "Terlalu banyak percobaan login salah. Akun Anda dikunci selama 30 detik.";
                } else {
                    $error = "Username atau password salah. (Sisa percobaan: " . (3 - $_SESSION['login_attempts']) . ")";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Terjadi kesalahan sistem.";
            }
        }
    }
}

// Generate new CSRF token for the login form
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UKK RPL Pergudangan</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="login-body">
    <div class="login-card">
        <h2>Pergudangan</h2>
        <p style="text-align: center; margin-bottom: 20px; color: #666;">Silakan masuk ke akun Anda</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>

        <form action="/login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;" <?= (isset($_SESSION['lockout_time']) && time() < $_SESSION['lockout_time']) ? 'disabled' : '' ?>>Masuk</button>
        </form>
        <div style="margin-top: 20px; text-align: center;">
            <a href="/db_maker.php" style="font-size: 0.85rem; color: #7f8c8d; text-decoration: none;">⚙️ Database Setup Utility</a>
        </div>
    </div>
</body>
</html>
