<?php
session_start();

// Cek jika sedang di hosting shared (disable error reporting untuk production)
if ($_SERVER['HTTP_HOST'] != 'localhost') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistem_penggajian');

// Konfigurasi Path
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('SLIP_PATH', dirname(__DIR__) . '/uploads/slip');
define('TTD_PATH', dirname(__DIR__) . '/uploads/ttd');

// Konfigurasi Fonnte - GANTI DENGAN TOKEN ASLI ANDA
define('FONNTE_API_KEY', ''); // Token dari Fonnte
define('FONNTE_API_URL', '');

// Fungsi koneksi database
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Set timeout untuk mencegah koneksi timeout di shared hosting
            $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
            
        } catch(PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    return $db;
}

// Fungsi untuk cek login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Fungsi untuk redirect jika belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Fungsi untuk format Rupiah
function formatRupiah($angka) {
    return 'Rp' . number_format($angka, 0, ',', '.') . ',00';
}
?>