<?php
// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Set secure cookie parameter if HTTPS is active
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pergudangan');

// Connect to Database
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Automatically create database and seed tables if not exist
$db_selected = @mysqli_select_db($conn, DB_NAME);
if (!$db_selected) {
    $create_db_query = "CREATE DATABASE IF NOT EXISTS `" . mysqli_real_escape_string($conn, DB_NAME) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if (mysqli_query($conn, $create_db_query)) {
        mysqli_select_db($conn, DB_NAME);
        run_sql_seed($conn);
    } else {
        die("Gagal membuat database otomatis: " . mysqli_error($conn));
    }
} else {
    // Check if critical tables (like users) exist, seed if missing
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (mysqli_num_rows($table_check) === 0) {
        run_sql_seed($conn);
    } else {
        // UKK Fix: Automatically convert old hashed passwords back to plaintext
        mysqli_query($conn, "UPDATE users SET password = 'staff123' WHERE username = 'staff' AND length(password) > 50");
        mysqli_query($conn, "UPDATE users SET password = 'manager123' WHERE username = 'manager' AND length(password) > 50");
    }
}

/**
 * Run database.sql file to seed database tables and structure
 */
function run_sql_seed($connection) {
    $sql_file = __DIR__ . '/database.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        $sql = preg_replace('/--.*?\n/', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//', '', $sql);
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                mysqli_query($connection, $query);
            }
        }
    }
}

// Set charset to utf8mb4 for security and encoding compatibility
mysqli_set_charset($conn, "utf8mb4");

/**
 * Escapes HTML output to prevent XSS.
 * @param string|null $string
 * @return string
 */
function esc($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a cryptographically secure CSRF token.
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from request.
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the user is authenticated.
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }
}

/**
 * Check if the user has one of the allowed roles.
 * @param array $roles
 */
function check_role($roles) {
    check_login();
    if (!in_array($_SESSION['role'], $roles)) {
        // Access Denied / Fail Close
        http_response_code(403);
        echo "<h1 style='text-align:center; margin-top:50px;'>Akses Ditolak</h1>";
        echo "<p style='text-align:center;'>Anda tidak memiliki izin untuk mengakses halaman ini.</p>";
        echo "<p style='text-align:center;'><a href='/dashboard.php'>Kembali ke Dashboard</a></p>";
        exit;
    }
}
