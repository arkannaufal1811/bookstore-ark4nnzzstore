# Ark4nnzzStore - Sistem Penjualan Buku Online

Website toko buku dengan panel Admin & sisi User, dibuat dengan PHP native + MySQL (PDO), HTML, CSS, JavaScript.

## Fitur

**Admin**
- Login admin
- Dashboard (statistik, **stok menipis**, pesanan terbaru)
- CRUD Kategori Buku
- CRUD Data Buku (dengan upload cover, **pagination**)
- List & edit Data User (role admin/user)
- List Pesanan + ubah status (**Baru Dibuat** → Diproses → Dikirim → Selesai), badge notifikasi pesanan baru
- **Pesan Masuk** (lihat & kelola pesan dari Contact Us)
- **Laporan Penjualan** (grafik pendapatan 6 bulan, top 5 buku terlaris)
- Lihat & cetak Struk/Invoice pesanan
- **Membership Ark4nnzz Club**: laporan ringkas (pendapatan, member aktif, per paket), halaman detail + bukti pembayaran, setujui / tolak / **batalkan** membership dengan alasan yang tampil ke user

**User**
- Registrasi & Login
- **Lupa Password** (reset password)
- Home dengan Kategori Populer, Buku Terbaru, Rekomendasi
- Pencarian & filter buku (judul, penulis, kategori, harga, rating) + **pagination**
- Detail buku dengan **ulasan & rating pembeli** dan **buku terkait**
- **Wishlist** (simpan buku favorit)
- Halaman About Us
- Contact to Admin (kirim pesan)
- Add to Cart & kelola keranjang
- Checkout dengan 2 metode: **COD** atau **Transfer Bank** (Bank BSI 7370615769 a.n. Arkan Naufal; bukti transfer dikirim ke admin lewat WhatsApp)
- Jumlah buku bisa diubah (+/-) langsung di keranjang & checkout, **dibatasi sesuai stok**
- **Ingatkan Saya** di login admin & user (login otomatis 30 hari)
- **Beli Langsung** (skip keranjang, langsung checkout) atau **+ Keranjang** (kumpulin dulu) — gaya TikTok Shop
- Info stok real-time di tiap kartu buku, badge "🔥 Stok Terbatas" kalau sisa ≤5
- Sistem anti-rebutan stok (row locking) — kalau 2 orang checkout stok terakhir bersamaan, cuma 1 yang berhasil, gak ada oversell
- **Riwayat pesanan dengan filter status** + detail item + lihat/cetak Struk

> Catatan: Admin dan User disimpan dalam satu tabel `users`, dibedakan lewat kolom `role`.
> Fitur **Ark4nnzz Club** aktif beneran: member dapat diskon 50% + gratis ongkir otomatis pas checkout, dan poin reward (1 poin/Rp10.000 belanja).

> Fitur "Lupa Password" menampilkan link reset langsung di layar (bukan dikirim email), karena server lokal (XAMPP) biasanya belum aktif layanan SMTP/email.

## Instalasi (Fresh Install / Baru Pertama Kali)

1. Copy folder `bookstore` ke `C:\xampp\htdocs\bookstore\`
2. Buat database baru `bookstore` di phpMyAdmin (collation `utf8mb4_unicode_ci`)
3. Import `database/bookstore.sql` (sudah termasuk semua tabel terbaru: wishlists, reviews, password_resets)
4. Jalankan: `http://localhost/bookstore/database/seed.php`
5. Login admin: `http://localhost/bookstore/admin/login.php`

## Struktur Folder

```
bookstore/
├── admin/          -> semua halaman admin panel (+ messages.php, reports.php)
├── user/           -> semua halaman sisi toko/user (+ wishlist.php, forgot/reset_password.php)
├── config/db.php   -> koneksi database
├── includes/       -> header/footer & fungsi auth
├── database/       -> file SQL (bookstore.sql) + seed.php
├── assets/         -> css, js, images (logo, hero background)
├── uploads/        -> cover buku
├── struk.php       -> halaman struk/invoice (bisa dicetak)
└── index.php       -> redirect ke halaman home toko
```

## Kredensial Login

| Role  | Email                          | Password    |
|-------|----------------------------------|-------------|
| Admin | arknxyzz60@gmail.com            | naufal1811  |
| User  | malik2009@gmail.com             | password123 |
| User  | siti_nur08@gmail.com            | password123 |
| User  | anto_weni01@gmail.com           | password123 |
| User  | maulrani36@gmail.com            | password123 |

## Troubleshooting

- **Halaman Not Found**: pastikan folder `bookstore` ada langsung di `htdocs` (bukan `htdocs/bookstore/bookstore`)
- **Table 'users' already exists**: database `bookstore` masih ada isi lama, kosongkan dulu (drop semua tabel) sebelum import ulang
- **Gambar bukti transfer lama hilang di admin**: file gambarnya ada di folder `uploads/` (nama `bukti_*.png`). Saat menimpa project, JANGAN hapus/ganti folder `uploads/` — gabungkan saja isinya. Admin sekarang menampilkan tulisan "File hilang" kalau file-nya tidak ada
- **Cover buku tidak muncul**: pastikan folder `uploads/` ke-copy semua isinya
- **Perubahan tampilan tidak muncul**: hard refresh browser (Ctrl+Shift+R)
