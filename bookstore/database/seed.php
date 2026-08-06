<?php
/**
 * Jalankan file ini SATU KALI lewat browser (mis. http://localhost/bookstore/database/seed.php)
 * atau CLI (php seed.php) SETELAH mengimpor bookstore.sql, untuk membuat akun contoh
 * dengan password ter-hash bcrypt yang valid.
 *
 * Kredensial ADMIN:
 * Email    : arkannaufal@ark4nnzzstore.com
 * Password : naufal1811
 *
 * Kredensial akun USER contoh lainnya:
 * Password : password123
 */
require_once __DIR__ . '/../config/db.php';

// Akun admin dengan password khusus
$adminHash = password_hash('naufal1811', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')
    ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password)");
$stmt->execute(['Arkan Naufal', 'arkannaufal@ark4nnzzstore.com', $adminHash]);
echo "Admin dibuat/diupdate: arkannaufal@ark4nnzzstore.com (password: naufal1811)\n";

// Akun user contoh dengan password default
$users = [
    ['Malik',    'malik2009@gmail.com',     'user'],
    ['Siti Nur', 'siti_nur08@gmail.com',    'user'],
    ['Ludianto', 'anto_weni01@gmail.com',   'user'],
    ['Maulana',  'maulrani36@gmail.com',    'user'],
];

$hash = password_hash('password123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE password = VALUES(password)");

foreach ($users as $u) {
    $stmt->execute([$u[0], $u[1], $hash, $u[2]]);
    echo "User dibuat/diupdate: {$u[1]} ({$u[2]})\n";
}

echo "\nSelesai!\n";
echo "Login admin di /admin/login.php dengan arkannaufal@ark4nnzzstore.com / naufal1811\n";
