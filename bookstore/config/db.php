<?php
// ===================================================
// Konfigurasi koneksi database - Ark4nnzzStore
// Sesuaikan DB_USER / DB_PASS dengan setup MySQL Anda
// ===================================================
// Zona waktu WIB - dipakai PHP & MySQL supaya waktu membership/pesanan konsisten
date_default_timezone_set('Asia/Jakarta');

// Alamat toko (tampil di label pengiriman & invoice)
define('STORE_NAME', 'Ark4nnzzStore');
define('STORE_ADDRESS', 'Jl. Arabika VII No.9 Blok Y9, RT.10/RW.6, Pd. Kopi, Kec. Duren Sawit, Kota Jakarta Timur, Daerah Khusus Ibukota Jakarta 13460');

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
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

define('BASE_URL', '/bookstore'); // ubah sesuai folder instalasi
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
