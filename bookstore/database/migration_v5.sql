-- ===================================================
-- MIGRATION V5 - Ark4nnzzStore
-- Mengubah Ark4nnzz Club jadi sistem membership berbayar (paket + transfer)
-- Jalankan lewat tab SQL di phpMyAdmin (database "bookstore" aktif)
-- ===================================================
USE bookstore;

CREATE TABLE IF NOT EXISTS memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan ENUM('mingguan','bulanan','tahunan') NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    payment_proof VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','active','rejected','expired') NOT NULL DEFAULT 'pending',
    started_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
