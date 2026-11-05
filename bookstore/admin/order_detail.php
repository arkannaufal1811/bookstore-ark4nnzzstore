<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/admin/orders.php"); exit; }

$proofMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_proof'])){
    if (empty($_FILES['proof']['name'])){
        $proofMsg = 'Pilih file gambar dulu.';
    } else {
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])){
            $proofMsg = 'Format harus JPG, PNG, atau WEBP.';
        } else {
            $name = 'bukti_' . uniqid() . '.' . $ext;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
            if (move_uploaded_file($_FILES['proof']['tmp_name'], UPLOAD_DIR . $name)){
                upload_backup_save($pdo, $name);
                $pdo->prepare("UPDATE orders SET payment_proof = ? WHERE id = ?")->execute([$name, $id]);
                header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=proof");
                exit;
            }
            $proofMsg = 'Gagal menyimpan file. Cek izin folder uploads.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipping'])){
    if (db_has_column($pdo, 'orders', 'courier')){
        $courier = trim($_POST['courier'] ?? '');
        $resi = trim($_POST['tracking_no'] ?? '');
        $pdo->prepare("UPDATE orders SET courier = ?, tracking_no = ? WHERE id = ?")
            ->execute([$courier !== '' ? $courier : null, $resi !== '' ? $resi : null, $id]);
    }
    header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=shipping");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $status = $_POST['status'] ?? 'Diproses';
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=saved");
    exit;
}

$stmt = $pdo->prepare("SELECT o.*, u.name AS nama_pemesan, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order){ header("Location: " . BASE_URL . "/admin/orders.php"); exit; }

$stmt = $pdo->prepare("SELECT oi.*, b.title FROM order_items oi JOIN books b ON b.id = oi.book_id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$pageTitle = 'Detail Pesanan';
$active = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Detail Pesanan #<?= $order['id'] ?></h1>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/struk.php?id=<?= $order['id'] ?>&doc=label" target="_blank" class="btn btn-primary">🏷️ Cetak Label Pengiriman (penjual)</a>
    <a href="<?= BASE_URL ?>/struk.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline">🧾 Invoice Pembeli</a>
  </div>
</div>

<?php if (($_GET['msg'] ?? '') === 'shipping'): ?><div class="alert alert-success">Info pengiriman disimpan.</div>
<?php elseif (($_GET['msg'] ?? '') === 'proof'): ?><div class="alert alert-success">Bukti transfer disimpan.</div>
<?php elseif (isset($_GET['msg'])): ?><div class="alert alert-success">Status pesanan diperbarui.</div><?php endif; ?>
<?php if ($proofMsg): ?><div class="alert alert-error"><?= h($proofMsg) ?></div><?php endif; ?>

<div class="panel" style="margin-bottom:24px;">
  <div class="panel-head"><h2>Informasi Pemesan</h2></div>
  <div style="padding:0 22px 22px;">
    <p><strong>Nama:</strong> <?= h($order['nama_pemesan']) ?></p>
    <p><strong>Email:</strong> <?= h($order['email']) ?></p>
    <?php if (!empty($order['shipping_phone'])): ?>
      <p><strong>No. HP Penerima:</strong> <a href="https://wa.me/<?= '62' . substr(h($order['shipping_phone']), 1) ?>" target="_blank"><?= h($order['shipping_phone']) ?></a></p>
    <?php endif; ?>
    <p><strong>Alamat Pengiriman:</strong> <?= nl2br(h($order['shipping_address'])) ?></p>

    <?php if (db_has_column($pdo, 'orders', 'courier')): ?>
    <form method="post" style="margin:12px 0; padding:12px 14px; background:#faf5fb; border-radius:10px;">
      <input type="hidden" name="save_shipping" value="1">
      <label style="font-size:13px; font-weight:700;">Info Pengiriman (tampil di label &amp; invoice pembeli)</label>
      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
        <?php $couriers = ['JNE','J&T Express','SiCepat','AnterAja','Pos Indonesia','Ninja Xpress','Gojek / Grab Instant','Kurir Toko']; ?>
        <select name="courier" style="padding:8px 10px; border:1.5px solid #ccc; border-radius:8px;">
          <option value="">-- Pilih kurir --</option>
          <?php foreach ($couriers as $c): ?>
            <option value="<?= h($c) ?>" <?= ($order['courier'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="tracking_no" value="<?= h($order['tracking_no'] ?? '') ?>" placeholder="Nomor resi" style="padding:8px 10px; border:1.5px solid #ccc; border-radius:8px; min-width:200px;">
        <button type="submit" class="btn btn-outline" style="padding:6px 14px;">Simpan</button>
      </div>
    </form>
    <?php else: ?>
      <p style="color:#a51c1c; font-size:13px;">⚠️ Jalankan <code>database/migration_v9.sql</code> untuk mengaktifkan nomor HP, kurir &amp; resi.</p>
    <?php endif; ?>
    <p><strong>Metode Pembayaran:</strong> <?= h($order['payment_method']) ?></p>
    <?php if (!empty($order['payment_proof'])): ?>
      <p><strong>Bukti Transfer:</strong></p>
      <?php if (upload_ensure($pdo, $order['payment_proof'])): ?>
        <a href="<?= UPLOAD_URL . h(basename($order['payment_proof'])) ?>" target="_blank">
          <img src="<?= UPLOAD_URL . h(basename($order['payment_proof'])) ?>" alt="Bukti Transfer" style="max-width:280px; border-radius:8px; border:1.5px solid var(--border); margin-top:6px;">
        </a>
        <p style="font-size:12.5px; color:var(--gray); margin-top:4px;">Klik gambar untuk lihat ukuran penuh</p>
      <?php else: ?>
        <p style="color:#a51c1c;">⚠️ File gambar <code><?= h($order['payment_proof']) ?></code> tidak ditemukan di folder <code>uploads/</code> (kemungkinan terhapus saat file project ditimpa). Kalau masih ada backup, copy lagi file itu ke folder uploads.</p>
      <?php endif; ?>
    <?php elseif ((stripos($order['payment_method'], 'transfer') !== false || stripos($order['payment_method'], 'qris') !== false)): ?>
      <p style="color:#a51c1c;"><strong>⚠️ Belum ada bukti transfer diupload.</strong></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" style="margin:10px 0;">
      <input type="hidden" name="upload_proof" value="1">
      <label style="font-size:13px; font-weight:700;">Pasang / ganti gambar bukti transfer:</label><br>
      <input type="file" name="proof" accept="image/jpeg,image/png,image/webp">
      <button type="submit" class="btn btn-outline" style="padding:6px 14px;">Simpan Bukti</button>
    </form>
    <p><strong>Tanggal:</strong> <?= date('d F Y H:i', strtotime($order['order_date'])) ?></p>
  </div>
</div>

<div class="panel" style="margin-bottom:24px;">
  <table class="data-table">
    <thead><tr><th>Buku</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= h($it['title']) ?></td>
        <td><?= $it['qty'] ?></td>
        <td><?= rupiah($it['price']) ?></td>
        <td><?= rupiah($it['price'] * $it['qty']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr><td colspan="3" style="text-align:right; font-weight:800;">Total</td><td style="font-weight:800;"><?= rupiah($order['total']) ?></td></tr>
    </tbody>
  </table>
</div>

<form method="post" class="admin-form">
  <div class="form-group">
    <label>Status Pesanan</label>
    <select name="status">
      <option value="Baru" <?= $order['status']==='Baru'?'selected':'' ?>>Baru Dibuat</option>
      <option value="Diproses" <?= $order['status']==='Diproses'?'selected':'' ?>>Diproses</option>
      <option value="Dikirim" <?= $order['status']==='Dikirim'?'selected':'' ?>>Dikirim</option>
      <option value="Selesai" <?= $order['status']==='Selesai'?'selected':'' ?>>Selesai</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Update Status</button>
  <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline">Kembali</a>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
