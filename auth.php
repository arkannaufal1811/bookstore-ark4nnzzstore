<?php
session_start();
require_once __DIR__ . '/../config/db.php';

function is_logged_in(){
    return isset($_SESSION['user_id']);
}

function current_user(){
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

function is_admin(){
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

function require_login($redirect = '/user/login.php'){
    if (!is_logged_in()){
        header("Location: " . BASE_URL . $redirect);
        exit;
    }
}

function require_admin(){
    if (!is_logged_in() || $_SESSION['user_role'] !== 'admin'){
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

function rupiah($num){
    return "Rp " . number_format($num, 0, ',', '.');
}

// Jumlah buku terjual per buku (semua pesanan kecuali Dibatalkan, sama seperti laporan admin)
function sold_counts($pdo){
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    try {
        $rows = $pdo->query("SELECT oi.book_id, SUM(oi.qty) AS n FROM order_items oi JOIN orders o ON o.id = oi.order_id AND o.status <> 'Dibatalkan' GROUP BY oi.book_id")->fetchAll();
        foreach ($rows as $r){ $map[(int)$r['book_id']] = (int)$r['n']; }
    } catch (Throwable $e) {}
    return $map;
}
function sold_label($pdo, $bookId){
    $n = sold_counts($pdo)[(int)$bookId] ?? 0;
    return ' · <span class="sold">Terjual ' . number_format($n, 0, ',', '.') . '</span>';
}

function cart_count($pdo){
    if (!is_logged_in()) return 0;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) AS c FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetch()['c'];
}

function wishlist_count($pdo){
    if (!is_logged_in()) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM wishlists WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetch()['c'];
}

function is_wishlisted($pdo, $bookId){
    if (!is_logged_in()) return false;
    $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$_SESSION['user_id'], $bookId]);
    return (bool) $stmt->fetch();
}

function active_membership($pdo){
    if (!is_logged_in()) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM memberships WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null; // tabel memberships belum ada (migration_v5 belum dijalankan)
    }
}

function pending_membership($pdo){
    if (!is_logged_in()) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM memberships WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null; // tabel memberships belum ada (migration_v5 belum dijalankan)
    }
}

function is_member_user($pdo){
    return active_membership($pdo) !== null;
}

function membership_plans(){
    return [
        'mingguan' => ['label' => 'Mingguan', 'price' => 15000, 'days' => 7, 'desc' => 'Cocok buat nyoba dulu'],
        'bulanan'  => ['label' => 'Bulanan', 'price' => 49000, 'days' => 30, 'desc' => 'Paling populer', 'popular' => true],
        'tahunan'  => ['label' => 'Tahunan', 'price' => 499000, 'days' => 365, 'desc' => 'Hemat sampai 15%'],
    ];
}

function user_points($pdo){
    if (!is_logged_in()) return 0;
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $row ? (int) $row['points'] : 0;
}

function book_rating($pdo, $book){
    // Pakai rata-rata dari review asli kalau ada, kalau belum ada review fallback ke rating statis buku
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c, COALESCE(AVG(rating),0) AS avg_r FROM reviews WHERE book_id = ?");
    $stmt->execute([$book['id']]);
    $r = $stmt->fetch();
    if ($r['c'] > 0){
        return ['rating' => round($r['avg_r'], 1), 'count' => (int)$r['c']];
    }
    return ['rating' => $book['rating'], 'count' => 0];
}

function h($str){
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ===================================================
// "Ingatkan Saya" (Remember Me) - login otomatis 30 hari
// Token disimpan di tabel remember_tokens (jalankan database/migration_v7.sql).
// Kalau tabel belum ada, fitur ini dilewati dan login biasa tetap jalan.
// ===================================================
define('REMEMBER_COOKIE', 'remember_me');
define('REMEMBER_DAYS', 30);

function remember_cookie_set($value, $expires){
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_issue($pdo, $userId){
    try {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expires   = time() + 60 * 60 * 24 * REMEMBER_DAYS;
        $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)")
            ->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);
        remember_cookie_set($selector . ':' . $validator, $expires);
    } catch (Throwable $e) {
        // tabel belum ada / error lain -> abaikan, login tetap berhasil
    }
}

function remember_forget($pdo){
    if (empty($_COOKIE[REMEMBER_COOKIE])) return;
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    try {
        $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$parts[0]]);
    } catch (Throwable $e) {}
    remember_cookie_set('', time() - 3600);
}

function remember_autologin($pdo){
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE] ?? '', 2);
    if (count($parts) !== 2) return;
    try {
        $stmt = $pdo->prepare("SELECT rt.*, u.name, u.email, u.role FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.selector = ? AND rt.expires_at > NOW() LIMIT 1");
        $stmt->execute([$parts[0]]);
        $row = $stmt->fetch();
        if ($row && hash_equals($row['token_hash'], hash('sha256', $parts[1]))){
            session_regenerate_id(true);
            $_SESSION['user_id']    = $row['user_id'];
            $_SESSION['user_name']  = $row['name'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_role']  = $row['role'];
            // ganti token lama dengan yang baru (rotasi)
            $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
            remember_issue($pdo, $row['user_id']);
        } else {
            remember_cookie_set('', time() - 3600);
        }
    } catch (Throwable $e) {}
}

if (!is_logged_in() && !empty($_COOKIE[REMEMBER_COOKIE])){
    remember_autologin($pdo);
}

// ===================================================
// Cadangan gambar bukti transfer di DATABASE (anti hilang)
// Setiap bukti yang diupload disalin ke tabel upload_backups.
// Kalau file di folder uploads/ hilang (misal folder tertimpa saat update project),
// file otomatis dipulihkan dari database saat halaman dibuka.
// Butuh tabel dari database/migration_v8.sql (kalau belum ada, fitur dilewati).
// ===================================================
function upload_backup_save($pdo, $name){
    $name = basename((string) $name);
    $path = UPLOAD_DIR . $name;
    if ($name === '' || !is_file($path) || filesize($path) > 8 * 1024 * 1024) return false;
    try {
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        $stmt = $pdo->prepare("REPLACE INTO upload_backups (filename, mime, data) VALUES (?, ?, ?)");
        $stmt->bindValue(1, $name);
        $stmt->bindValue(2, $mime);
        $stmt->bindValue(3, file_get_contents($path), PDO::PARAM_LOB);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// Pastikan file ada: kalau ada -> pastikan sudah dicadangkan; kalau hilang -> pulihkan dari database.
function upload_ensure($pdo, $name){
    if (!$name) return false;
    $name = basename((string) $name);
    $path = UPLOAD_DIR . $name;
    static $saved = null;

    if (is_file($path)){
        if ($saved === null){
            try {
                $saved = array_flip($pdo->query("SELECT filename FROM upload_backups")->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable $e) {
                $saved = false; // tabel belum ada
            }
        }
        if ($saved !== false && !isset($saved[$name]) && upload_backup_save($pdo, $name)){
            $saved[$name] = 1;
        }
        return true;
    }

    try {
        $stmt = $pdo->prepare("SELECT data FROM upload_backups WHERE filename = ?");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row){
            if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
            return @file_put_contents($path, $row['data']) !== false;
        }
    } catch (Throwable $e) {}
    return false;
}

// ===================================================
// Penghitung waktu membership (hitung mundur langsung)
// $m = hasil active_membership(); $dark = true kalau tampil di atas latar ungu
// ===================================================
function membership_countdown($m, $dark = false){
    if (!$m || empty($m['expires_at'])) return '';
    $remaining = strtotime($m['expires_at']) - time();
    static $scriptPrinted = false;
    $box = $dark
        ? 'background:rgba(255,255,255,.18); color:#fff; border:1px solid rgba(255,255,255,.35);'
        : 'background:#fff; color:#333; border:1.5px solid var(--primary);';
    $html  = '<div class="mcd" data-remaining="' . max(0, (int) $remaining) . '" style="' . $box . ' border-radius:12px; padding:12px 16px; margin:0 0 16px; display:inline-block; min-width:260px;">';
    $html .= '<div style="font-size:12.5px; opacity:.9;">⏳ Sisa waktu membership</div>';
    $html .= '<div class="mcd-time" style="font-size:20px; font-weight:800; letter-spacing:.3px; margin:2px 0;">--</div>';
    $html .= '<div style="font-size:12px; opacity:.9;">Berakhir: ' . date('d M Y, H:i', strtotime($m['expires_at'])) . '</div>';
    $html .= '<div class="mcd-warn" style="font-size:12px; font-weight:700; margin-top:4px; display:none;">⚠️ Segera perpanjang supaya diskon 50% tidak hilang</div>';
    $html .= '</div>';
    if (!$scriptPrinted){
        $scriptPrinted = true;
        $html .= '<script>
        (function(){
          var boxes = document.querySelectorAll(".mcd");
          var start = Date.now();
          function pad(n){ return n < 10 ? "0" + n : n; }
          function tick(){
            boxes.forEach(function(b){
              var left = parseInt(b.getAttribute("data-remaining"), 10) - Math.floor((Date.now() - start) / 1000);
              var t = b.querySelector(".mcd-time"), w = b.querySelector(".mcd-warn");
              if (left <= 0){ t.textContent = "Membership sudah berakhir"; w.style.display = "none"; return; }
              var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
              t.textContent = (d > 0 ? d + " hari " : "") + pad(h) + " : " + pad(m) + " : " + pad(s);
              w.style.display = left < 3 * 86400 ? "block" : "none";
            });
          }
          tick(); setInterval(tick, 1000);
        })();
        </script>';
    }
    return $html;
}

// ===================================================
// Helper nomor HP & cek kolom (supaya aman walau migration_v9 belum dijalankan)
// ===================================================
function db_has_column($pdo, $table, $column){
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])){
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
    }
    return $cache[$key];
}

// Normalisasi nomor HP Indonesia -> 08xxxxxxxxxx ; return false kalau tidak valid
function normalize_phone($raw){
    $d = preg_replace('/\D+/', '', (string) $raw);
    if (strpos($d, '62') === 0) $d = '0' . substr($d, 2);
    elseif (strpos($d, '8') === 0) $d = '0' . $d;
    return preg_match('/^08[0-9]{8,12}$/', $d) ? $d : false;
}

// ===================================================
// Generator nomor resi otomatis (contoh) - format berbeda tiap kurir
// CATATAN: nomor ini dibuat sistem, BUKAN resi asli dari kurir (tidak bisa dilacak di kurir).
// ===================================================
function generate_resi($pdo, $courier, $orderId){
    $digits = function($n){ $s = ''; for ($i = 0; $i < $n; $i++) $s .= random_int(0, 9); return $s; };
    $r = '';
    for ($try = 0; $try < 10; $try++){
        switch ($courier){
            case 'JNE':                  $r = $digits(16); break;
            case 'J&T Express':          $r = 'JP' . $digits(10); break;
            case 'SiCepat':              $r = '00' . $digits(10); break;
            case 'AnterAja':             $r = '10' . $digits(10); break;
            case 'Pos Indonesia':        $r = 'P' . $digits(12); break;
            case 'Ninja Xpress':         $r = 'NLIDAP' . $digits(10); break;
            case 'Gojek / Grab Instant': $r = 'INS' . date('ymd') . $digits(4); break;
            case 'Kurir Toko':           return 'KT-' . date('Ymd') . '-' . str_pad((int) $orderId, 5, '0', STR_PAD_LEFT);
            default: return '';
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE tracking_no = ? AND id <> ?");
            $stmt->execute([$r, (int) $orderId]);
            if ((int) $stmt->fetchColumn() === 0) return $r;
        } catch (Throwable $e) { return $r; }
    }
    return $r;
}

// ===================================================
// Pembatalan pesanan (butuh database/migration_v10.sql)
// cancel_status: NULL | diajukan (user minta) | disetujui | ditolak | admin (dibatalkan langsung admin)
// ===================================================
function cancel_ready($pdo){
    return db_has_column($pdo, 'orders', 'cancel_status');
}

// Batalkan pesanan: status -> Dibatalkan, stok buku dikembalikan, poin member ditarik lagi.
// Hanya bisa saat status Baru / Diproses. Aman dipanggil dua kali (yang kedua ditolak, stok tidak dobel).
function cancel_order($pdo, $orderId, $cancelStatus, $reason, $note = ''){
    $own = !$pdo->inTransaction();
    try {
        if ($own) $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([(int) $orderId]);
        $o = $stmt->fetch();
        if (!$o) throw new Exception('Pesanan tidak ditemukan.');
        if (!in_array($o['status'], ['Baru', 'Diproses'], true)){
            throw new Exception($o['status'] === 'Dibatalkan' ? 'Pesanan ini sudah dibatalkan.' : 'Pesanan berstatus ' . $o['status'] . ' tidak bisa dibatalkan lagi.');
        }
        $items = $pdo->prepare("SELECT book_id, qty, price FROM order_items WHERE order_id = ?");
        $items->execute([(int) $orderId]);
        $subtotal = 0;
        foreach ($items->fetchAll() as $it){
            $pdo->prepare("UPDATE books SET stock = stock + ? WHERE id = ?")->execute([$it['qty'], $it['book_id']]);
            $subtotal += $it['price'] * $it['qty'];
        }
        // Poin hanya didapat pesanan member (diskon 50%); non-member total = subtotal (+ongkir 15.000)
        $total = (float) $o['total'];
        $nonMember = abs($total - ($subtotal + 15000)) < 0.5 || abs($total - $subtotal) < 0.5;
        if (!$nonMember){
            $pts = (int) floor($total / 10000);
            if ($pts > 0) $pdo->prepare("UPDATE users SET points = GREATEST(points - ?, 0) WHERE id = ?")->execute([$pts, $o['user_id']]);
        }
        $pdo->prepare("UPDATE orders SET status = 'Dibatalkan', cancel_status = ?, cancel_reason = ?, cancel_note = ?, cancelled_at = NOW() WHERE id = ?")
            ->execute([$cancelStatus, mb_substr_safe($reason, 255), $note !== '' ? mb_substr_safe($note, 255) : null, (int) $orderId]);
        if ($own) $pdo->commit();
        return [true, 'ok'];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage()];
    }
}

function mb_substr_safe($s, $len){
    $s = (string) $s;
    if (strlen($s) <= $len) return $s;
    return preg_match('/^.{1,' . $len . '}/us', $s, $m) ? $m[0] : substr($s, 0, $len);
}

// ===================================================
// CSRF token sederhana (dipakai form aksi admin: membership, dll)
// ===================================================
function csrf_token(){
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(){
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
function csrf_valid(){
    return !empty($_SESSION['csrf']) && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

// ===================================================
// Membership: status efektif, label, dan sinkron flag is_member (butuh migration_v11.sql untuk catatan & batal)
// ===================================================
function membership_ready($pdo){
    return db_has_column($pdo, 'memberships', 'admin_note') && db_has_column($pdo, 'memberships', 'cancelled_at');
}

// 'active' yang tanggalnya sudah lewat dianggap 'expired' (kolom status di DB tidak otomatis berubah)
function membership_effective_status($m){
    if (($m['status'] ?? '') === 'active' && !empty($m['expires_at']) && strtotime($m['expires_at']) <= time()) return 'expired';
    return $m['status'] ?? '';
}

function membership_status_label($status){
    return ['pending' => 'Menunggu', 'active' => 'Aktif', 'expired' => 'Kedaluwarsa', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'][$status] ?? ucfirst((string) $status);
}

function membership_badge_class($status){
    return match($status){
        'pending' => 'badge-minta',
        'active' => 'badge-selesai',
        'expired' => 'badge-dikirim',
        'rejected', 'cancelled' => 'badge-batal',
        default => ''
    };
}

// Samakan users.is_member dengan ada/tidaknya membership yang masih berlaku
function membership_sync_flag($pdo, $userId){
    $st = $pdo->prepare("SELECT COUNT(*) FROM memberships WHERE user_id = ? AND status = 'active' AND expires_at > NOW()");
    $st->execute([$userId]);
    $pdo->prepare("UPDATE users SET is_member = ? WHERE id = ?")->execute([((int) $st->fetchColumn() > 0) ? 1 : 0, $userId]);
}
