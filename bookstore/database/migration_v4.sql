-- ===================================================
-- MIGRATION V4 - Ark4nnzzStore
-- Mengaktifkan fitur "Ark4nnzz Club" (membership beneran)
-- Jalankan lewat tab SQL di phpMyAdmin (database "bookstore" aktif)
-- ===================================================
USE bookstore;

ALTER TABLE users ADD COLUMN is_member TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN points INT NOT NULL DEFAULT 0;
