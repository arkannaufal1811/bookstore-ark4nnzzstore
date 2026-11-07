<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
if (!$id){ header("Location: " . BASE_URL . "/admin/memberships.php"); exit; }

$ready = membership_ready($pdo);
$planDays = ['mingguan' => 7, 'bulanan' => 30, 'tahunan' => 365];
$err = '';

$hasPhone = db_has_column($pdo, 'users', 'phone');
$load = $pdo->prepare("SELECT m.*, u.name AS user_name, u.email" . ($hasPhone ? ", u.phone AS user_phone" : "") . "
                       FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.id = ?");
$load->execute([$id]);
$m = $load->fetch();
if (!$m){ header("Location: " . BASE_URL . "/admin/memberships.php"); exit; }

// ===== Aksi (semua lewat POST + CSRF; update bersyarat status supaya tidak bisa dobel) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $act  = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');
    if (!csrf_valid()){
        $err = 'Sesi form kedaluwarsa, muat ulang halaman lalu coba lagi.';
    } elseif ($act === 'approve'){
        $days = $planDays[$m['plan']] ?? 30;
        $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $upd = $pdo->prepare("UPDATE memberships SET status = 'active', started_at = NOW(), expires_at = ? WHERE id = ? AND status = 'pending'");
        $upd->execute([$expires, $id]);
        if ($upd->rowCount() > 0){
            membership_sync_flag($pdo, $m['user_id']);
            header("Location: " . BASE_URL . "/admin/memberships.php?msg=approved"); exit;
        }
        $err = 'Pendaftaran ini sudah tidak berstatus menunggu.';
    } elseif ($act === 'reject'){
        if (mb_strlen($note) < 5){
            $err = 'Alasan penolakan wajib diisi (minimal 5 huruf) supaya user tahu kenapa ditolak.';
        } else {
            $upd = $ready
                ? $pdo->prepare("UPDATE memberships SET status = 'rejected', admin_note = ? WHERE id = ? AND status = 'pending'")
                : $pdo->prepare("UPDATE memberships SET status = 'rejected' WHERE id = ? AND status = 'pending'");
            $upd->execute($ready ? [mb_substr($note, 0, 255), $id] : [$id]);
            if ($upd->rowCount() > 0){ header("Location: " . BASE_URL . "/admin/memberships.php?msg=rejected"); exit; }
            $err = 'Pendaftaran ini sudah tidak berstatus menunggu.';
        }
    } elseif ($act === 'cancel'){
        if (!$ready){
            $err = 'Jalankan database/migration_v11.sql dulu untuk mengaktifkan pembatalan membership.';
        } elseif (mb_strlen($note) < 5){
            $err = 'Alasan pembatalan wajib diisi (minimal 5 huruf).';
        } else {
            $upd = $pdo->prepare("UPDATE memberships SET status = 'cancelled', admin_note = ?, cancelled_at = NOW() WHERE id = ? AND status = 'active'");
            $upd->execute([mb_substr($note, 0, 255), $id]);
            if ($upd->rowCount() > 0){
                membership_sync_flag($pdo, $m['user_id']);   // is_member ikut mati kalau tidak ada membership aktif lain
                header("Location: " . BASE_URL . "/admin/memberships.php?msg=cancelled"); exit;
            }
            $err = 'Membership ini sudah tidak aktif.';
        }
    }
    // muat ulang data setelah aksi gagal (status bisa saja sudah berubah)
    $load->execute([$id]);
    $m = $load->fetch() ?: $m;
}

$eff = membership_effective_status($m);

// riwayat membership user ini (membantu cek: sudah pernah daftar, ada dua paket aktif, dll)
$hist = $pdo->prepare("SELECT * FROM memberships WHERE user_id = ? ORDER BY created_at DESC, id DESC");
$hist->execute([$m['user_id']]);
$hist = $hist->fetchAll();

$proofOk = !empty($m['payment_proof']) && upload_ensure($pdo, $m['payment_proof']);

$pageTitle = 'Detail Membership';
$active = 'memberships';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Detail Membership #<?= (int) $m['id'] ?></h1>
  <a href="<?= BASE_URL ?>/admin/memberships.php" class="btn btn-outline">← Kembali</a>
</div>

<?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
<?php if (!$ready): ?><div class="alert alert-error">⚠️ Jalankan <code>database/migration_v11.sql</code> untuk mengaktifkan pembatalan membership dan catatan admin.</div><?php endif; ?>

<div class="panel" style="margin-bottom:24px;">
  <div class="panel-head"><h2>Informasi Pendaftaran</h2></div>
  <div style="padding:0 22px 22px;">
    <p><strong>Nama:</strong> <?= h($m['user_name']) ?></p>
    <p><strong>Email:</strong> <?= h($m['email']) ?></p>
    <?php if ($hasPhone && !empty($m['user_phone'])): ?>
      <p><strong>No. HP:</strong> <a href="https://wa.me/<?= '62' . substr(h($m['user_phone']), 1) ?>" target="_blank"><?= h($m['user_phone']) ?></a></p>
    <?php endif; ?>
    <p><strong>Paket:</strong> <?= h(ucfirst($m['plan'])) ?> (<?= (int) ($planDays[$m['plan']] ?? 0) ?> hari) &middot; <strong><?= rupiah($m['price']) ?></strong></p>
    <p><strong>Tanggal daftar:</strong> <?= date('d F Y H:i', strtotime($m['created_at'])) ?></p>
    <p><strong>Status:</strong> <span class="badge <?= membership_badge_class($eff) ?>"><?= h(membership_status_label($eff)) ?></span></p>
    <?php if (!empty($m['started_at'])): ?><p><strong>Mulai aktif:</strong> <?= date('d F Y H:i', strtotime($m['started_at'])) ?></p><?php endif; ?>
    <?php if (!empty($m['expires_at'])): ?>
      <p><strong>Berlaku sampai:</strong> <?= date('d F Y H:i', strtotime($m['expires_at'])) ?>
        <?php if ($eff === 'active'): ?>&middot; sisa <?= max(1, (int) ceil((strtotime($m['expires_at']) - time()) / 86400)) ?> hari<?php endif; ?></p>
    <?php endif; ?>
    <?php if (!empty($m['cancelled_at'])): ?><p><strong>Dibatalkan pada:</strong> <?= date('d F Y H:i', strtotime($m['cancelled_at'])) ?></p><?php endif; ?>
    <?php if (!empty($m['admin_note'])): ?><p><strong>Catatan admin:</strong> <?= h($m['admin_note']) ?></p><?php endif; ?>

    <p style="margin-top:14px;"><strong>Bukti Pembayaran:</strong></p>
    <?php if ($proofOk): ?>
      <a href="<?= UPLOAD_URL . h(basename($m['payment_proof'])) ?>" target="_blank">
        <img src="<?= UPLOAD_URL . h(basename($m['payment_proof'])) ?>" alt="Bukti Pembayaran" style="max-width:320px; border-radius:8px; border:1.5px solid var(--border); margin-top:6px;">
      </a>
      <p style="font-size:12.5px; color:var(--gray); margin-top:4px;">Klik gambar untuk lihat ukuran penuh. Cocokkan nominal dengan <?= rupiah($m['price']) ?>.</p>
    <?php elseif (!empty($m['payment_proof'])): ?>
      <p style="color:#a51c1c;">⚠️ File <code><?= h($m['payment_proof']) ?></code> tidak ditemukan di folder <code>uploads/</code> (kemungkinan terhapus saat file project ditimpa).</p>
    <?php else: ?>
      <p style="color:#a51c1c;">⚠️ Belum ada bukti pembayaran.</p>
    <?php endif; ?>
  </div>
</div>

<?php if ($m['status'] === 'pending'): ?>
<div class="panel" style="margin-bottom:24px; padding:16px 18px;">
  <h3 style="margin-bottom:8px;">🔎 Verifikasi Pembayaran</h3>
  <p style="font-size:13px; color:#666; margin-bottom:12px;">Cek bukti transfer di atas. Kalau valid, aktifkan membership. Kalau tidak valid, tolak dengan alasan yang jelas.</p>
  <div style="display:flex; gap:14px; flex-wrap:wrap;">
    <form method="post" style="flex:1; min-width:220px;" onsubmit="return confirm('Aktifkan membership user ini?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="approve">
      <button type="submit" class="btn btn-primary">✅ Setujui &amp; Aktifkan</button>
    </form>
    <form method="post" style="flex:2; min-width:260px;" onsubmit="return confirm('Tolak pendaftaran ini?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="reject">
      <input type="text" name="note" maxlength="200" required placeholder="Alasan menolak, mis. nominal transfer tidak sesuai" style="width:100%; padding:9px 10px; border:1.5px solid #ccc; border-radius:8px; margin-bottom:8px;">
      <button type="submit" class="btn btn-outline" style="border-color:#c0392b; color:#c0392b;">❌ Tolak Pendaftaran</button>
    </form>
  </div>
</div>
<?php elseif ($eff === 'active'): ?>
<div class="panel" style="margin-bottom:24px; padding:16px 18px; border:1.5px solid #222;">
  <h3 style="margin-bottom:8px;">Batalkan Membership</h3>
  <p style="font-size:13px; color:#666; margin-bottom:10px;">Dipakai kalau pembayaran ternyata bermasalah atau ada pelanggaran. Diskon 50% dan gratis ongkir user langsung berhenti. Pesanan lama dan poin yang sudah dikumpulkan tidak berubah.</p>
  <form method="post" onsubmit="return confirm('Batalkan membership aktif user ini? Diskon dan gratis ongkirnya langsung berhenti.');">
    <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
    <textarea name="note" rows="2" maxlength="200" required placeholder="Alasan pembatalan (tampil ke user), mis. bukti transfer ternyata tidak valid" style="width:100%; padding:10px; border:1.5px solid #ccc; border-radius:8px; margin-bottom:8px;"></textarea>
    <button type="submit" class="btn btn-outline" style="border-color:#c0392b; color:#c0392b;" <?= $ready ? '' : 'disabled' ?>>🚫 Batalkan Membership</button>
    <p style="font-size:12.5px; color:#96730a; margin-top:8px;">💸 Kalau user sudah transfer, kembalikan dananya secara manual (hubungi lewat WhatsApp).</p>
  </form>
</div>
<?php endif; ?>

<?php if (count($hist) > 1): ?>
<div class="panel" style="margin-bottom:24px;">
  <div class="panel-head"><h2>Riwayat Membership User Ini</h2></div>
  <table class="data-table">
    <thead><tr><th>Daftar</th><th>Paket</th><th>Harga</th><th>Status</th><th>Berlaku Sampai</th></tr></thead>
    <tbody>
      <?php foreach ($hist as $h2): $e2 = membership_effective_status($h2); ?>
      <tr<?= (int) $h2['id'] === (int) $m['id'] ? ' style="background:#faf5fb;"' : '' ?>>
        <td><?= date('d M Y', strtotime($h2['created_at'])) ?></td>
        <td><?= h(ucfirst($h2['plan'])) ?></td>
        <td><?= rupiah($h2['price']) ?></td>
        <td><span class="badge <?= membership_badge_class($e2) ?>"><?= h(membership_status_label($e2)) ?></span></td>
        <td><?= $h2['expires_at'] ? date('d M Y', strtotime($h2['expires_at'])) : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
