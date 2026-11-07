<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$user = current_user();
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT o.*, 
        (SELECT GROUP_CONCAT(b.title, ' x', oi.qty SEPARATOR ' | ') FROM order_items oi JOIN books b ON b.id=oi.book_id WHERE oi.order_id = o.id) AS item_summary
        FROM orders o WHERE o.user_id = ?";
$params = [$user['id']];
if ($statusFilter !== ''){
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY o.order_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$summary = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM orders WHERE user_id = ? AND status <> 'Dibatalkan'");
$summary->execute([$user['id']]);
$summary = $summary->fetch();

function badgeClass($status){
    return match($status){
        'Baru' => 'badge-baru',
        'Diproses' => 'badge-diproses',
        'Dikirim' => 'badge-dikirim',
        'Selesai' => 'badge-selesai',
        'Dibatalkan' => 'badge-batal',
        default => ''
    };
}

$pageTitle = 'Profil Saya';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page">
  <h1 style="font-size:26px; font-weight:800; margin-bottom:6px;">👤 <?= h($user['name']) ?></h1>
  <p style="color:var(--gray); margin-bottom:20px;"><?= h($user['email']) ?></p>

  <?php if (is_member_user($pdo)): ?>
    <div class="member-badge" style="background:var(--primary); color:#fff; margin-bottom:20px;">
      👑 Member Ark4nnzz Club &middot; <?= user_points($pdo) ?> Poin
    </div>
    <div><?= membership_countdown(active_membership($pdo), false) ?></div>
  <?php else: ?>
    <div class="panel" style="padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
      <span>Belum jadi member Ark4nnzz Club — gabung buat dapetin diskon 50%, gratis ongkir, dan poin reward!</span>
      <a href="<?= BASE_URL ?>/user/join_club.php" class="btn btn-primary btn-sm">Gabung Sekarang</a>
    </div>
  <?php endif; ?>

  <div class="stat-cards" style="grid-template-columns:repeat(2,1fr); margin-bottom:26px;">
    <div class="stat-card pesanan"><div class="lbl">Total Pesanan</div><div class="val"><?= $summary['c'] ?></div></div>
    <div class="stat-card pendapatan"><div class="lbl">Total Belanja</div><div class="val" style="font-size:20px;"><?= rupiah($summary['t']) ?></div></div>
  </div>

  <?php if (($_GET['msg'] ?? '') === 'cancel_requested'): ?><div class="alert alert-success">Pengajuan pembatalan terkirim. Admin akan memeriksa pesananmu.</div><?php endif; ?>
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
    <h2 style="font-size:20px; font-weight:800;">Riwayat Pesanan</h2>
    <form method="get" style="display:flex; gap:8px; align-items:center;">
      <label style="font-weight:700; font-size:14px;">Filter Status:</label>
      <select name="status" onchange="this.form.submit()" style="padding:8px 12px; border:1.5px solid var(--border); border-radius:8px;">
        <option value="" <?= $statusFilter===''?'selected':'' ?>>Semua</option>
        <option value="Baru" <?= $statusFilter==='Baru'?'selected':'' ?>>Baru Dibuat</option>
        <option value="Diproses" <?= $statusFilter==='Diproses'?'selected':'' ?>>Diproses</option>
        <option value="Dikirim" <?= $statusFilter==='Dikirim'?'selected':'' ?>>Dikirim</option>
        <option value="Selesai" <?= $statusFilter==='Selesai'?'selected':'' ?>>Selesai</option>
        <option value="Dibatalkan" <?= $statusFilter==='Dibatalkan'?'selected':'' ?>>Dibatalkan</option>
      </select>
    </form>
  </div>

  <div class="panel">
    <table class="data-table">
      <thead><tr><th>No. Pesanan</th><th>Tanggal</th><th>Item</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><?= date('d F Y', strtotime($o['order_date'])) ?></td>
          <td style="max-width:260px;">
            <details>
              <summary style="cursor:pointer; color:var(--primary); font-weight:700; font-size:13px;">Lihat item</summary>
              <div style="font-size:13px; color:#555; margin-top:6px;"><?= nl2br(h(str_replace(' | ', "\n", $o['item_summary'] ?? '-'))) ?></div>
            </details>
          </td>
          <td><?= rupiah($o['total']) ?></td>
          <td>
            <span class="badge <?= badgeClass($o['status']) ?>"><?= h($o['status']) ?></span>
            <?php $cs = $o['cancel_status'] ?? ''; ?>
            <?php if ($cs === 'diajukan'): ?><br><span class="badge badge-minta" style="margin-top:4px;">Menunggu verifikasi pembatalan</span>
            <?php elseif ($cs === 'ditolak'): ?><br><span style="font-size:12px; color:#a51c1c;">Pengajuan batal ditolak<?= !empty($o['cancel_note']) ? ': ' . h($o['cancel_note']) : '' ?></span>
            <?php elseif ($o['status'] === 'Dibatalkan' && !empty($o['cancel_reason'])): ?><br><span style="font-size:12px; color:#777;">Alasan: <?= h($o['cancel_reason']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="order-actions">
              <a href="<?= BASE_URL ?>/struk.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-outline btn-sm">🧾 Invoice</a>
              <?php if (array_key_exists('cancel_status', $o) && in_array($o['status'], ['Baru','Diproses'], true) && empty($o['cancel_status'])): ?>
                <a href="<?= BASE_URL ?>/user/cancel_order.php?id=<?= $o['id'] ?>" class="btn btn-cancel btn-sm">✖ Ajukan Batal</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
        <tr><td colspan="6" style="text-align:center; color:#999;">Belum ada pesanan<?= $statusFilter ? ' dengan status ini' : '' ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
