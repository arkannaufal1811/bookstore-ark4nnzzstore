<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$cancelReady = cancel_ready($pdo);
$onlyCancel = $cancelReady && isset($_GET['cancel']);
$orders = $pdo->query("
    SELECT o.*, u.name AS nama_pemesan
    FROM orders o JOIN users u ON u.id = o.user_id
    " . ($onlyCancel ? "WHERE o.cancel_status = 'diajukan'" : '') . "
    ORDER BY o.order_date DESC
")->fetchAll();
$pendingCancel = $cancelReady ? (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE cancel_status = 'diajukan'")->fetchColumn() : 0;

// Cek apakah file bukti transfer masih ada di folder uploads
function proof_exists($name){
    global $pdo;
    return $name && upload_ensure($pdo, $name);
}

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

$pageTitle = 'Pesanan';
$active = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Data Pesanan</h1></div>

<?php if ($pendingCancel > 0): ?>
  <div class="alert alert-error"><strong><?= $pendingCancel ?> pengajuan pembatalan</strong> menunggu verifikasi kamu.
    <?php if ($onlyCancel): ?><a href="<?= BASE_URL ?>/admin/orders.php">Tampilkan semua pesanan</a><?php else: ?><a href="<?= BASE_URL ?>/admin/orders.php?cancel=1">Lihat yang diajukan</a><?php endif; ?>
  </div>
<?php endif; ?>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>No.</th><th>Nama Pemesan</th><th>Tanggal</th><th>Pembayaran</th><th>Bukti</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $i => $o): ?>
      <tr>
        <td><?= $i+1 ?>.</td>
        <td><?= h($o['nama_pemesan']) ?></td>
        <td><?= date('d F Y', strtotime($o['order_date'])) ?></td>
        <td><?= h($o['payment_method']) ?></td>
        <td>
          <?php if ($o['payment_proof']): ?>
            <?php if (proof_exists($o['payment_proof'])): ?>
              <a href="<?= UPLOAD_URL . h(basename($o['payment_proof'])) ?>" target="_blank" title="Klik untuk lihat ukuran penuh">
                <img src="<?= UPLOAD_URL . h(basename($o['payment_proof'])) ?>" alt="Bukti Transfer" style="width:46px; height:46px; object-fit:cover; border-radius:6px; border:1px solid #ccc;">
              </a>
            <?php else: ?>
              <span class="badge badge-diproses" title="File <?= h($o['payment_proof']) ?> tidak ditemukan di folder uploads">File hilang</span>
            <?php endif; ?>
          <?php elseif ((stripos($o['payment_method'],'transfer') !== false || stripos($o['payment_method'],'qris') !== false)): ?>
            <span class="badge badge-diproses">Belum ada bukti</span>
          <?php else: ?>
            <span style="color:#bbb;">-</span>
          <?php endif; ?>
        </td>
        <td><?= rupiah($o['total']) ?></td>
        <td>
          <span class="badge <?= badgeClass($o['status']) ?>"><?= h($o['status']) ?></span>
          <?php if (($o['cancel_status'] ?? '') === 'diajukan'): ?><br><span class="badge badge-minta" style="margin-top:4px;">Minta batal</span><?php endif; ?>
        </td>
        <td>
          <div class="action-icons">
            <a class="edit" href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" title="Lihat Detail">🧾</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
      <tr><td colspan="8" style="text-align:center; color:#999;">Belum ada pesanan</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
