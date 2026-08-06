<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$user = current_user();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

function badgeClass($status){
    return match($status){
        'Diproses' => 'badge-diproses',
        'Dikirim' => 'badge-dikirim',
        'Selesai' => 'badge-selesai',
        default => ''
    };
}

$pageTitle = 'Profil Saya';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page">
  <h1 style="font-size:26px; font-weight:800; margin-bottom:6px;">👤 <?= h($user['name']) ?></h1>
  <p style="color:var(--gray); margin-bottom:24px;"><?= h($user['email']) ?></p>

  <h2 style="font-size:20px; font-weight:800; margin-bottom:14px;">Riwayat Pesanan</h2>
  <div class="panel">
    <table class="data-table">
      <thead><tr><th>No. Pesanan</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= str_pad($o['id'],5,'0',STR_PAD_LEFT) ?></td>
          <td><?= date('d F Y', strtotime($o['order_date'])) ?></td>
          <td><?= rupiah($o['total']) ?></td>
          <td><span class="badge <?= badgeClass($o['status']) ?>"><?= h($o['status']) ?></span></td>
          <td><a href="<?= BASE_URL ?>/struk.php?id=<?= $o['id'] ?>" target="_blank">🧾 Lihat Struk</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
        <tr><td colspan="5" style="text-align:center; color:#999;">Belum ada pesanan</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
