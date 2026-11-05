-- ===================================================
-- MIGRATION V7 - Ark4nnzzStore
-- Fitur "Ingatkan Saya" (login otomatis 30 hari) di login admin & user
-- Jalankan lewat tab SQL di phpMyAdmin (database "bookstore" aktif)
-- ===================================================
USE bookstore;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
