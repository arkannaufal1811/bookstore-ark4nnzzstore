-- ===================================================
-- MIGRATION V9 - Ark4nnzzStore
-- Nomor HP (pembeli & penerima) + info pengiriman (kurir & nomor resi)
-- Aman dijalankan berulang kali. Jalankan di HeidiSQL / phpMyAdmin pada database "bookstore"
-- ===================================================
USE bookstore;

SET @s = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone') = 0,
  'ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_phone') = 0,
  'ALTER TABLE orders ADD COLUMN shipping_phone VARCHAR(20) DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'courier') = 0,
  'ALTER TABLE orders ADD COLUMN courier VARCHAR(50) DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'tracking_no') = 0,
  'ALTER TABLE orders ADD COLUMN tracking_no VARCHAR(60) DEFAULT NULL', 'SELECT 1'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
