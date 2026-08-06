<?php
require_once __DIR__ . '/includes/auth.php';
require_login('/user/login.php');

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/user/index.php"); exit; }

$stmt = $pdo->prepare("SELECT o.*, u.name AS nama_pemesan, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order){ header("Location: " . BASE_URL . "/user/index.php"); exit; }

// Hanya admin atau pemilik pesanan yang boleh melihat struk
if (!is_admin() && $order['user_id'] != $_SESSION['user_id']){
    header("Location: " . BASE_URL . "/user/index.php"); exit;
}

$stmt = $pdo->prepare("SELECT oi.*, b.title FROM order_items oi JOIN books b ON b.id = oi.book_id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Struk Pesanan #<?= $order['id'] ?> - Ark4nnzzStore</title>
<style>
  *{box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif;}
  body{background:#f2f2f2; padding:30px;}
  .receipt{
    max-width:480px; margin:0 auto; background:#fff; border:1.5px dashed #333;
    border-radius:10px; padding:30px 26px;
  }
  .r-header{text-align:center; margin-bottom:18px;}
  .r-header .brand{font-size:22px; font-weight:800; color:#c724d6;}
  .r-header .sub{font-size:13px; color:#666;}
  .r-divider{border-top:2px dashed #999; margin:16px 0;}
  .r-row{display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;}
  .r-items table{width:100%; border-collapse:collapse; font-size:13px;}
  .r-items th{text-align:left; border-bottom:1.5px solid #333; padding:6px 0; font-size:12px;}
  .r-items td{padding:8px 0; border-bottom:1px dashed #ddd; vertical-align:top;}
  .r-total{display:flex; justify-content:space-between; font-size:17px; font-weight:800; margin-top:14px;}
  .r-status{
    text-align:center; margin-top:16px; padding:8px; border-radius:8px; font-weight:800;
    background:#fbe9b8; color:#96730a;
  }
  .r-footer{text-align:center; margin-top:22px; font-size:12px; color:#777;}
  .print-btn{
    display:block; max-width:480px; margin:0 auto 16px; text-align:right;
  }
  .print-btn button{
    padding:10px 22px; border:none; border-radius:8px; background:#c724d6; color:#fff; font-weight:700; cursor:pointer;
  }
  @media print{ .print-btn{display:none;} body{background:#fff; padding:0;} .receipt{border:none;} }
</style>
</head>
<body>
<div class="print-btn"><button onclick="window.print()">🖨️ Cetak Struk</button></div>
<div class="receipt">
  <div class="r-header">
    <div class="brand">📚 Ark4nnzzStore</div>
    <div class="sub">Struk / Bukti Pesanan</div>
  </div>
  <div class="r-divider"></div>
  <div class="r-row"><span>No. Pesanan</span><strong>#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></strong></div>
  <div class="r-row"><span>Tanggal</span><strong><?= date('d F Y H:i', strtotime($order['order_date'])) ?></strong></div>
  <div class="r-row"><span>Nama Pemesan</span><strong><?= h($order['nama_pemesan']) ?></strong></div>
  <div class="r-row"><span>Metode Bayar</span><strong><?= h($order['payment_method']) ?></strong></div>
  <div class="r-row" style="align-items:flex-start;"><span>Alamat</span><strong style="text-align:right; max-width:60%;"><?= nl2br(h($order['shipping_address'])) ?></strong></div>
  <div class="r-divider"></div>
  <div class="r-items">
    <table>
      <thead><tr><th>Buku</th><th>Qty</th><th style="text-align:right;">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= h($it['title']) ?><br><span style="color:#888;">@<?= rupiah($it['price']) ?></span></td>
          <td><?= $it['qty'] ?></td>
          <td style="text-align:right;"><?= rupiah($it['price'] * $it['qty']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="r-divider"></div>
  <div class="r-total"><span>Total Bayar</span><span><?= rupiah($order['total']) ?></span></div>
  <div class="r-status">Status: <?= h($order['status']) ?></div>
  <div class="r-footer">
    Terima kasih telah berbelanja di Ark4nnzzStore 🙏<br>
    Pembayaran dilakukan saat barang diterima (COD).
  </div>
</div>
</body>
</html>
