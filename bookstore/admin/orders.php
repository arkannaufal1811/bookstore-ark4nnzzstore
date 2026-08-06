<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$orders = $pdo->query("
    SELECT o.id, u.name AS nama_pemesan, o.order_date, o.total, o.status
    FROM orders o JOIN users u ON u.id = o.user_id
    ORDER BY o.order_date DESC
")->fetchAll();

function badgeClass($status){
    return match($status){
        'Diproses' => 'badge-diproses',
        'Dikirim' => 'badge-dikirim',
        'Selesai' => 'badge-selesai',
        default => ''
    };
}

$pageTitle = 'Pesanan';
$active = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Data Pesanan</h1></div>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>No.</th><th>Nama Pemesan</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $i => $o): ?>
      <tr>
        <td><?= $i+1 ?>.</td>
        <td><?= h($o['nama_pemesan']) ?></td>
        <td><?= date('d F Y', strtotime($o['order_date'])) ?></td>
        <td><?= rupiah($o['total']) ?></td>
        <td><span class="badge <?= badgeClass($o['status']) ?>"><?= h($o['status']) ?></span></td>
        <td>
          <div class="action-icons">
            <a class="edit" href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" title="Lihat Struk">🧾</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?>
      <tr><td colspan="6" style="text-align:center; color:#999;">Belum ada pesanan</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
