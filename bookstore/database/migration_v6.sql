-- ===================================================
-- MIGRATION V6 - Ark4nnzzStore
-- Menambah status "Baru" di alur pesanan (Baru -> Diproses -> Dikirim -> Selesai)
-- Jalankan lewat tab SQL di phpMyAdmin (database "bookstore" aktif)
-- ===================================================
USE bookstore;

ALTER TABLE orders MODIFY status ENUM('Baru','Diproses','Dikirim','Selesai') NOT NULL DEFAULT 'Baru';
