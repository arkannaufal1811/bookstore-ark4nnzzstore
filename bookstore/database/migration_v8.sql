-- ===================================================
-- MIGRATION V8 - Ark4nnzzStore
-- Cadangan gambar bukti transfer di database (anti hilang)
-- Jalankan di HeidiSQL / phpMyAdmin pada database "bookstore"
-- ===================================================
USE bookstore;

CREATE TABLE IF NOT EXISTS upload_backups (
    filename VARCHAR(255) NOT NULL PRIMARY KEY,
    mime VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    data LONGBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
