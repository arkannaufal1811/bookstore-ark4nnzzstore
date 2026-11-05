-- ===================================================
-- MIGRATION V3 - Ark4nnzzStore
-- Menambahkan dukungan metode pembayaran Transfer Bank + bukti transfer
-- Jalankan lewat tab SQL di phpMyAdmin (pastikan database "bookstore" aktif)
-- ===================================================
USE bookstore;

ALTER TABLE orders ADD COLUMN payment_proof VARCHAR(255) DEFAULT NULL AFTER payment_method;
