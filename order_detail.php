<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/admin/orders.php"); exit; }

// Endpoint kecil: buat nomor resi otomatis untuk kurir tertentu (dipakai tombol/dropdown di form)
if (isset($_GET['gen_resi'])){
    header('Content-Type: text/plain; charset=utf-8');
    echo generate_resi($pdo, $_GET['gen_resi'], (int) $id);
    exit;
}

$proofMsg = '';
$cancelErr = '';
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_action']) && cancel_ready($pdo)){
    $act = $_POST['cancel_action'];
    $note = trim($_POST['note'] ?? '');
    $cur = $pdo->prepare("SELECT cancel_status, cancel_reason FROM orders WHERE id = ?");
    $cur->execute([$id]);
    $cur = $cur->fetch() ?: [];
    if ($act === 'admin_cancel'){
        if (strlen($note) < 5){ $cancelErr = 'Alasan pembatalan wajib diisi (minimal 5 huruf).'; }
        else {
            list($ok, $msg) = cancel_order($pdo, $id, 'admin', $note, '');
            if ($ok){ header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=cancelled"); exit; }
            $cancelErr = $msg;
        }
    } elseif ($act === 'approve_cancel'){
        if (($cur['cancel_status'] ?? '') !== 'diajukan'){ $cancelErr = 'Tidak ada pengajuan pembatalan yang menunggu.'; }
        else {
            list($ok, $msg) = cancel_order($pdo, $id, 'disetujui', $cur['cancel_reason'] ?? '', $note);
            if ($ok){ header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=approved"); exit; }
            $cancelErr = $msg;
        }
    } elseif ($act === 'reject_cancel'){
        if (strlen($note) < 5){ $cancelErr = 'Alasan penolakan wajib diisi (minimal 5 huruf).'; }
        else {
            $pdo->prepare("UPDATE orders SET cancel_status = 'ditolak', cancel_note = ? WHERE id = ? AND cancel_status = 'diajukan'")
                ->execute([mb_substr_safe($note, 255), $id]);
            header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=rejected"); exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipping'])){
    if (db_has_column($pdo, 'orders', 'courier')){
        $courier = trim($_POST['courier'] ?? '');
        $resi = trim($_POST['tracking_no'] ?? '');
        if ($courier !== '' && $resi === '') $resi = generate_resi($pdo, $courier, (int) $id);   // otomatis kalau dikosongkan
        $pdo->prepare("UPDATE orders SET courier = ?, tracking_no = ? WHERE id = ?")
            ->execute([$courier !== '' ? $courier : null, $resi !== '' ? $resi : null, $id]);
    }
    header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=shipping");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $status = $_POST['status'] ?? 'Diproses';
    $curSt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $curSt->execute([$id]);
    $curRow = $curSt->fetch() ?: [];
    if (($curRow['cancel_status'] ?? '') === 'diajukan' && in_array($status, ['Dikirim','Selesai'], true)){
        // pengajuan batal harus dijawab dulu, kalau tidak pengajuannya menggantung (pesanan sudah tidak bisa dibatalkan)
        $cancelErr = 'Ada pengajuan pembatalan dari pembeli. Setujui atau tolak dulu sebelum pesanan dikirim.';
    } else {
        if (in_array($status, ['Baru','Diproses','Dikirim','Selesai'], true) && ($curRow['status'] ?? '') !== 'Dibatalkan'){
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }
        header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=saved");
        exit;
    }
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

<?php if (($_GET['msg'] ?? '') === 'cancelled'): ?><div class="alert alert-success">Pesanan dibatalkan. Stok buku sudah dikembalikan.</div>
<?php elseif (($_GET['msg'] ?? '') === 'approved'): ?><div class="alert alert-success">Pembatalan disetujui. Pesanan dibatalkan dan stok buku dikembalikan.</div>
<?php elseif (($_GET['msg'] ?? '') === 'rejected'): ?><div class="alert alert-success">Pengajuan pembatalan ditolak. Pesanan tetap berjalan.</div>
<?php elseif (($_GET['msg'] ?? '') === 'shipping'): ?><div class="alert alert-success">Info pengiriman disimpan.</div>
<?php elseif (($_GET['msg'] ?? '') === 'proof'): ?><div class="alert alert-success">Bukti transfer disimpan.</div>
<?php elseif (isset($_GET['msg'])): ?><div class="alert alert-success">Status pesanan diperbarui.</div><?php endif; ?>
<?php if ($cancelErr): ?><div class="alert alert-error"><?= h($cancelErr) ?></div><?php endif; ?>
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
        <select name="courier" id="courierSel" style="padding:8px 10px; border:1.5px solid #ccc; border-radius:8px;">
          <option value="">-- Pilih kurir --</option>
          <?php foreach ($couriers as $c): ?>
            <option value="<?= h($c) ?>" <?= ($order['courier'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="tracking_no" id="resiInput" value="<?= h($order['tracking_no'] ?? '') ?>" placeholder="Nomor resi (otomatis)" style="padding:8px 10px; border:1.5px solid #ccc; border-radius:8px; min-width:200px;">
        <button type="button" id="resiRand" class="btn btn-outline" style="padding:6px 12px;" title="Acak ulang nomor resi">🎲 Acak</button>
        <button type="submit" class="btn btn-outline" style="padding:6px 14px;">Simpan</button>
      </div>
      <p style="font-size:12px; color:#888; margin-top:6px;">Nomor resi terisi otomatis saat kurir dipilih (format beda tiap kurir) dan bisa diubah manual. Ini nomor contoh dari sistem, bukan resi asli kurir.</p>
    </form>
    <script>
    (function(){
      var sel = document.getElementById('courierSel'), inp = document.getElementById('resiInput'), btn = document.getElementById('resiRand');
      var auto = inp.value === '';   // resi kosong = boleh diisi otomatis
      function gen(){
        if (!sel.value){ return; }
        fetch('?id=<?= (int) $order['id'] ?>&gen_resi=' + encodeURIComponent(sel.value))
          .then(function(r){ return r.text(); })
          .then(function(t){ inp.value = t.trim(); auto = true; });
      }
      sel.addEventListener('change', function(){ if (auto || inp.value === '') gen(); });
      btn.addEventListener('click', function(){ if (!sel.value){ alert('Pilih kurir dulu.'); return; } gen(); });
      inp.addEventListener('input', function(){ auto = false; });
    })();
    </script>
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

<?php if (cancel_ready($pdo)): $cs = $order['cancel_status'] ?? ''; $canCancel = in_array($order['status'], ['Baru','Diproses'], true); ?>
<div class="panel" style="padding:16px 18px; margin-bottom:16px; border:1.5px solid <?= $order['status'] === 'Dibatalkan' ? '#c0392b' : '#222' ?>;">
  <h3 style="margin-bottom:8px;">Pembatalan Pesanan</h3>
  <?php if ($order['status'] === 'Dibatalkan'): ?>
    <p style="color:#a51c1c;"><strong>Pesanan ini dibatalkan</strong> <?= $cs === 'admin' ? 'oleh admin' : 'atas permintaan pembeli (disetujui admin)' ?><?= !empty($order['cancelled_at']) ? ' pada ' . date('d F Y H:i', strtotime($order['cancelled_at'])) : '' ?>.</p>
    <p><strong>Alasan:</strong> <?= h($order['cancel_reason'] ?? '-') ?></p>
    <?php if (!empty($order['cancel_note'])): ?><p><strong>Catatan admin:</strong> <?= h($order['cancel_note']) ?></p><?php endif; ?>
    <p style="font-size:12.5px; color:#888;">Stok buku sudah dikembalikan dan pesanan tidak masuk laporan penjualan.</p>
  <?php else: ?>
    <?php if ($cs === 'diajukan'): ?>
      <div class="alert alert-error" style="margin-bottom:12px;">
        <strong>Pembeli mengajukan pembatalan.</strong><br>Alasan: <?= h($order['cancel_reason'] ?? '-') ?>
      </div>
      <form method="post">
        <textarea name="note" rows="2" placeholder="Catatan untuk pembeli (wajib diisi kalau menolak)" style="width:100%; padding:10px; border:1.5px solid #ccc; border-radius:8px; margin-bottom:8px;"></textarea>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <?php if ($canCancel): ?><button type="submit" name="cancel_action" value="approve_cancel" class="btn btn-primary" onclick="return confirm('Setujui pembatalan? Pesanan dibatalkan dan stok dikembalikan.');">Setujui Pembatalan</button><?php endif; ?>
          <button type="submit" name="cancel_action" value="reject_cancel" class="btn btn-outline">Tolak Pengajuan</button>
        </div>
      </form>
    <?php else: ?>
      <?php if ($cs === 'ditolak'): ?><p style="color:#96730a; margin-bottom:8px;">Pengajuan pembatalan pembeli sebelumnya <strong>ditolak</strong><?= !empty($order['cancel_note']) ? ': ' . h($order['cancel_note']) : '' ?>.</p><?php endif; ?>
      <?php if ($canCancel): ?>
        <p style="font-size:13px; color:#666; margin-bottom:8px;">Batalkan kalau bukti transfer atau informasi pesanan tidak valid. Pembeli akan melihat alasannya.</p>
        <form method="post" onsubmit="return confirm('Batalkan pesanan ini? Stok buku akan dikembalikan.');">
          <input type="hidden" name="cancel_action" value="admin_cancel">
          <textarea name="note" rows="2" required placeholder="Alasan pembatalan, misal: bukti transfer tidak sesuai nominal" style="width:100%; padding:10px; border:1.5px solid #ccc; border-radius:8px; margin-bottom:8px;"></textarea>
          <button type="submit" class="btn btn-outline" style="border-color:#c0392b; color:#c0392b;">Batalkan Pesanan</button>
        </form>
      <?php else: ?>
        <p style="color:#888; font-size:13px;">Pesanan berstatus <?= h($order['status']) ?> tidak bisa dibatalkan lagi.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($order['status'] === 'Dibatalkan'): ?>
  <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline">Kembali</a>
<?php else: ?>
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
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
