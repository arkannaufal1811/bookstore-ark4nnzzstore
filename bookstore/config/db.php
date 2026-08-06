<?php
// ===================================================
// Konfigurasi koneksi database - Ark4nnzzStore
// Sesuaikan DB_USER / DB_PASS dengan setup MySQL Anda
// ===================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'bookstore');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

define('BASE_URL', '/bookstore'); // ubah sesuai folder instalasi
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
