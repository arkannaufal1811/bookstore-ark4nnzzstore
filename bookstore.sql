-- ===================================================
-- Database: bookstore
-- Ark4nnzzStore - Sistem Penjualan Buku Online
-- ===================================================

CREATE DATABASE IF NOT EXISTS bookstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookstore;

-- ---------------------------------------------------
-- Tabel users (Admin & User digabung, dibedakan oleh kolom role)
-- ---------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_member TINYINT(1) NOT NULL DEFAULT 0,
    points INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------
-- Tabel categories
-- ---------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------
-- Tabel books
-- ---------------------------------------------------
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(150) DEFAULT NULL,
    category_id INT NOT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    cover_image VARCHAR(255) DEFAULT NULL,
    description TEXT,
    rating DECIMAL(2,1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel cart_items (Add to Cart)
-- ---------------------------------------------------
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel orders (Pesanan / Payment at Delivery - COD)
-- ---------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) DEFAULT 'COD (Bayar di Tempat)',
    payment_proof VARCHAR(255) DEFAULT NULL,
    shipping_address TEXT,
    shipping_phone VARCHAR(20) DEFAULT NULL,
    courier VARCHAR(50) DEFAULT NULL,
    tracking_no VARCHAR(60) DEFAULT NULL,
    status ENUM('Baru','Diproses','Dikirim','Selesai','Dibatalkan') NOT NULL DEFAULT 'Baru',
    cancel_status VARCHAR(12) DEFAULT NULL,
    cancel_reason VARCHAR(255) DEFAULT NULL,
    cancel_note VARCHAR(255) DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel order_items
-- ---------------------------------------------------
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    book_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel messages (Contact to Admin)
-- ---------------------------------------------------
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------
-- Tabel wishlists (Simpan buku favorit)
-- ---------------------------------------------------
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel reviews (Ulasan & rating pembeli)
-- ---------------------------------------------------
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (book_id, user_id),
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel password_resets (Lupa Password)
-- ---------------------------------------------------
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel memberships (Ark4nnzz Club)
-- ---------------------------------------------------
CREATE TABLE memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan ENUM('mingguan','bulanan','tahunan') NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    payment_proof VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','active','rejected','expired','cancelled') NOT NULL DEFAULT 'pending',
    started_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_note VARCHAR(255) DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------
-- Tabel upload_backups (cadangan gambar bukti transfer)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS upload_backups (
    filename VARCHAR(255) NOT NULL PRIMARY KEY,
    mime VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
    data LONGBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------
-- Tabel remember_tokens ("Ingatkan Saya")
-- ---------------------------------------------------
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================
-- DATA AWAL (SEED)
-- ===================================================
-- CATATAN: Data user (Admin, Malik, Siti Nur, dst) TIDAK di-insert di sini
-- karena password perlu di-hash dengan bcrypt oleh PHP (password_hash) agar valid.
-- Jalankan file "database/seed.php" sekali lewat browser/CLI setelah import SQL ini
-- untuk membuat akun-akun contoh.

INSERT INTO categories (name) VALUES
('Novel'), ('Filsafat'), ('Pelajaran'), ('Sastra Indonesia'), ('Komputer'),
('Fiksi'), ('Pengembangan Diri'), ('Bisnis'), ('Teknologi'), ('Sejarah'), ('Anak-anak');

INSERT INTO books (title, author, category_id, price, stock, cover_image, rating) VALUES
('Cantik Itu Luka', 'Eka Kurniawan', 1, 85000, 20, 'cantik_itu_luka.jpg', 4.8),
('3726 MDPL', 'Purbina Dani', 1, 90000, 15, '3726_mdpl.jpg', 4.5),
('Kuliah Ringkas 7 Cabang Filsafat', 'Muhammad Nur Wahid', 2, 80000, 10, 'filsafat.jpg', 4.3),
('Seni Budaya', 'Kemendikbud', 3, 120000, 25, 'seni_budaya.jpg', 4.0),
('Laut Bercerita', 'Leila S. Chudori', 4, 150000, 18, 'laut_bercerita.jpg', 4.9),
('Perahu Kertas', 'Dee Lestari', 6, 95000, 30, 'perahu_kertas.jpg', 4.9),
('Atomic Habits', 'James Clear', 7, 85000, 40, 'atomic_habits.jpg', 4.2),
('Zero To One', 'Peter Thiel', 8, 105000, 22, 'zero_to_one.jpg', 5.0),
('Innovator''s Dilemma', 'Clayton Christensen', 8, 75000, 12, 'innovators_dilemma.jpg', 3.8),
('Api Sejarah', 'Ahmad Mansur S.', 10, 125000, 14, 'api_sejarah.jpg', 4.0),
('Harry Potter and the Cursed Child', 'J.K. Rowling', 6, 100000, 35, 'harry_potter.jpg', 5.0),
('Filosofi Teras', 'Henry Manampiring', 7, 50000, 50, 'filosofi_teras.jpg', 5.0),
('Dunia Sophie', 'Jostein Gaarder', 2, 110000, 16, 'dunia_sophie.jpg', 4.7);
