-- Jalankan file ini SETELAH migration_v2.sql
-- Kalau muncul error "Duplicate column name", berarti kolomnya udah ada, abaikan aja / skip file ini.
USE bookstore;
ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0;
