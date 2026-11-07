<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);

if (!cancel_ready($pdo)){ header("Location: " . BASE_URL . "/user/profile.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);
$order = $stmt->fetch();
if (!$order){ header("Location: " . BASE_URL . "/user/profile.php"); exit; }

$canRequest = in_array($order['status'], ['Baru', 'Diproses'], true) && empty($order['cancel_status']);
$reasons = ['Salah memesan buku atau jumlah', 'Ingin mengganti metode pembayaran', 'Alamat pengiriman salah', 'Menemukan harga yang lebih murah', 'Lainnya'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canRequest){
    $pick = $_POST['reason_pick'] ?? '';
    $detail = trim($_POST['reason_detail'] ?? '');
    if (!in_array($pick, $reasons, true)){
        $error = 'Pilih alasan pembatalan.';
    } elseif (strlen($detail) < 5){
        $error = 'Jelaskan alasannya dengan singkat (minimal 5 huruf).';
    } else {
        $reason = mb_substr_safe($pick . ': ' . $detail, 255);
        $upd = $pdo->prepare("UPDATE orders SET cancel_status = 'diajukan', cancel_reason = ? WHERE id = ? AND user_id = ? AND status IN ('Baru','Diproses') AND cancel_status IS NULL");
        $upd->execute([$reason, $id, $user['id']]);
        if ($upd->rowCount() > 0){
            header("Location: " . BASE_URL . "/user/profile.php?msg=cancel_requested");
            exit;
        }
        $error = 'Pengajuan gagal. Status pesanan mungkin sudah berubah.';
    }
}

$items = $pdo->prepare("SELECT b.title, oi.qty FROM order_items oi JOIN books b ON b.id = oi.book_id WHERE oi.order_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$pageTitle = 'Ajukan Pembatalan';
$active = 'profile';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="section" style="max-width:640px;">
  <div class="sec-head"><h2>Ajukan pembatalan pesanan</h2></div>

  <div style="border:1.5px solid #222; border-radius:12px; padding:16px 18px; margin-bottom:18px; font-size:14px;">
    <strong>Pesanan #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></strong> &middot; <?= date('d F Y', strtotime($order['order_date'])) ?><br>
    <?php foreach ($items as $it): ?><?= h($it['title']) ?> &times; <?= (int) $it['qty'] ?><br><?php endforeach; ?>
    <strong>Total: <?= rupiah($order['total']) ?></strong> &middot; Status: <?= h($order['status']) ?>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <?php if (!$canRequest): ?>
    <div class="alert alert-error">
      <?php if (($order['cancel_status'] ?? '') === 'diajukan'): ?>Pengajuan pembatalan sudah dikirim dan sedang diperiksa admin.
      <?php elseif (($order['cancel_status'] ?? '') === 'ditolak'): ?>Pengajuan pembatalan sebelumnya ditolak admin<?= !empty($order['cancel_note']) ? ': ' . h($order['cancel_note']) : '' ?>. Silakan hubungi admin lewat halaman Contact.
      <?php elseif ($order['status'] === 'Dibatalkan'): ?>Pesanan ini sudah dibatalkan.
      <?php else: ?>Pesanan berstatus <?= h($order['status']) ?> tidak bisa dibatalkan lagi. Silakan hubungi admin lewat halaman Contact.
      <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-outline">Kembali</a>
  <?php else: ?>
    <p style="font-size:14px; color:#6f5c7a; margin-bottom:14px;">Pengajuan akan diperiksa admin. Pesananmu baru benar-benar dibatalkan setelah admin menyetujuinya, dan sebelum itu pesanan tetap berjalan.</p>
    <form method="post">
      <div class="form-group">
        <label style="font-weight:700; display:block; margin-bottom:6px;">Alasan</label>
        <select name="reason_pick" required style="width:100%; padding:12px; border:1.5px solid #ccc; border-radius:8px;">
          <option value="">-- Pilih alasan --</option>
          <?php foreach ($reasons as $r): ?><option value="<?= h($r) ?>" <?= (($_POST['reason_pick'] ?? '') === $r) ? 'selected' : '' ?>><?= h($r) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label style="font-weight:700; display:block; margin-bottom:6px;">Penjelasan singkat</label>
        <textarea name="reason_detail" rows="3" required maxlength="200" placeholder="Contoh: salah memilih jumlah buku" style="width:100%; padding:12px; border:1.5px solid #ccc; border-radius:8px;"><?= h($_POST['reason_detail'] ?? '') ?></textarea>
      </div>
      <div style="display:flex; gap:10px; margin-top:16px;">
        <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
        <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-outline">Kembali</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
