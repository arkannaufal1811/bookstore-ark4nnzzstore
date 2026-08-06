<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$totalBuku = $pdo->query("SELECT COUNT(*) c FROM books")->fetch()['c'];
$totalUser = $pdo->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch()['c'];
$totalPesanan = $pdo->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
$pendapatan = $pdo->query("SELECT COALESCE(SUM(total),0) t FROM orders WHERE status='Selesai'")->fetch()['t'];

$recentOrders = $pdo->query("
    SELECT o.id, u.name AS nama_pemesan, o.order_date, o.total, o.status
    FROM orders o JOIN users u ON u.id = o.user_id
    ORDER BY o.order_date DESC LIMIT 6
")->fetchAll();

function badgeClass($status){
    return match($status){
        'Diproses' => 'badge-diproses',
        'Dikirim' => 'badge-dikirim',
        'Selesai' => 'badge-selesai',
        default => ''
    };
}

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Dashboard</h1>
  <div class="admin-user">👤 Admin ▾</div>
</div>

<div class="stat-cards">
  <div class="stat-card buku"><div class="lbl">Total Buku</div><div class="val"><?= $totalBuku ?></div></div>
  <div class="stat-card user"><div class="lbl">Total User</div><div class="val"><?= $totalUser ?></div></div>
  <div class="stat-card pesanan"><div class="lbl">Total Pesanan</div><div class="val"><?= $totalPesanan ?></div></div>
  <div class="stat-card pendapatan"><div class="lbl">Pendapatan</div><div class="val"><?= rupiah($pendapatan) ?></div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Pesanan Terbaru</h2>
    <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">Lihat Semua</a>
  </div>
  <table class="data-table">
    <thead>
      <tr><th>No.</th><th>Nama Pemesan</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recentOrders as $i => $o): ?>
      <tr>
        <td><?= $i+1 ?>.</td>
        <td><?= h($o['nama_pemesan']) ?></td>
        <td><?= date('d F Y', strtotime($o['order_date'])) ?></td>
        <td><?= rupiah($o['total']) ?></td>
        <td><span class="badge <?= badgeClass($o['status']) ?>"><?= h($o['status']) ?></span></td>
        <td><a href="<?= BASE_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>">✅</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recentOrders): ?>
      <tr><td colspan="6" style="text-align:center; color:#999;">Belum ada pesanan</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
