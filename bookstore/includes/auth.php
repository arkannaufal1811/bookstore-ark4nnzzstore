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
