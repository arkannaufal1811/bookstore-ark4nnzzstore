<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Pendapatan 6 bulan terakhir (dari pesanan berstatus Selesai)
$monthlyRevenue = $pdo->query("
    SELECT DATE_FORMAT(order_date, '%Y-%m') AS ym, DATE_FORMAT(order_date, '%b %Y') AS label, SUM(total) AS total
    FROM orders WHERE status = 'Selesai' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym, label ORDER BY ym ASC
")->fetchAll();
$maxRevenue = 0;
foreach ($monthlyRevenue as $m){ $maxRevenue = max($maxRevenue, $m['total']); }
if ($maxRevenue == 0) $maxRevenue = 1;

// Top 5 buku terlaris (berdasarkan total qty di order_items, semua status kecuali Dibatalkan)
$topBooks = $pdo->query("
    SELECT b.title, b.cover_image, SUM(oi.qty) AS total_terjual, SUM(oi.qty*oi.price) AS total_omzet
    FROM order_items oi JOIN books b ON b.id = oi.book_id
    JOIN orders o ON o.id = oi.order_id AND o.status <> 'Dibatalkan'
    GROUP BY oi.book_id, b.title, b.cover_image ORDER BY total_terjual DESC LIMIT 5
")->fetchAll();

// Ringkasan bulan ini vs bulan lalu
$thisMonth = $pdo->query("SELECT COALESCE(SUM(total),0) t, COUNT(*) c FROM orders WHERE status='Selesai' AND MONTH(order_date)=MONTH(CURDATE()) AND YEAR(order_date)=YEAR(CURDATE())")->fetch();
$lastMonth = $pdo->query("SELECT COALESCE(SUM(total),0) t, COUNT(*) c FROM orders WHERE status='Selesai' AND MONTH(order_date)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(order_date)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetch();

$pageTitle = 'Laporan';
$active = 'reports';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Laporan Penjualan</h1></div>

<div class="stat-cards">
  <div class="stat-card pendapatan">
    <div class="lbl">Pendapatan Bulan Ini</div>
    <div class="val" style="font-size:22px;"><?= rupiah($thisMonth['t']) ?></div>
  </div>
  <div class="stat-card pesanan">
    <div class="lbl">Pesanan Selesai Bulan Ini</div>
    <div class="val"><?= $thisMonth['c'] ?></div>
  </div>
  <div class="stat-card buku">
    <div class="lbl">Pendapatan Bulan Lalu</div>
    <div class="val" style="font-size:22px;"><?= rupiah($lastMonth['t']) ?></div>
  </div>
  <div class="stat-card user">
    <div class="lbl">Pesanan Selesai Bulan Lalu</div>
    <div class="val"><?= $lastMonth['c'] ?></div>
  </div>
</div>

<div class="panel" style="margin-bottom:30px;">
  <div class="panel-head"><h2>Pendapatan 6 Bulan Terakhir</h2></div>
  <div style="padding:10px 22px 26px;">
    <?php if (!$monthlyRevenue): ?>
      <p style="color:#999;">Belum ada pesanan selesai dalam 6 bulan terakhir.</p>
    <?php endif; ?>
    <?php foreach ($monthlyRevenue as $m): ?>
      <div class="bar-row">
        <span class="bar-label"><?= h($m['label']) ?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?= max(4, ($m['total']/$maxRevenue)*100) ?>%;"></div></div>
        <span class="bar-value"><?= rupiah($m['total']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>🏆 Top 5 Buku Terlaris</h2></div>
  <table class="data-table">
    <thead><tr><th>No.</th><th>Judul</th><th>Total Terjual</th><th>Total Omzet</th></tr></thead>
    <tbody>
      <?php foreach ($topBooks as $i => $tb): ?>
      <tr>
        <td><?= $i+1 ?>.</td>
        <td><?= h($tb['title']) ?></td>
        <td><?= $tb['total_terjual'] ?> buku</td>
        <td><?= rupiah($tb['total_omzet']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$topBooks): ?>
      <tr><td colspan="4" style="text-align:center; color:#999;">Belum ada data penjualan</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
