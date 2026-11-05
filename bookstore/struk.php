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

// Jenis dokumen (seperti marketplace):
//  invoice = Invoice Pembelian       -> untuk PEMBELI  (rincian pembayaran, status)
//  label   = Label Pengiriman + Packing List -> untuk PENJUAL/admin (penerima, barang yang dikemas, COD)
$doc = (($_GET['doc'] ?? 'invoice') === 'label') ? 'label' : 'invoice';
if ($doc === 'label' && !is_admin()){
    header("Location: " . BASE_URL . "/struk.php?id=" . (int) $order['id'] . (isset($_GET['popup']) ? '&popup=1' : ''));
    exit;
}

$subtotal = 0; $totalQty = 0;
foreach ($items as $it){ $subtotal += $it['price'] * $it['qty']; $totalQty += $it['qty']; }

// Rincian biaya (mengikuti aturan checkout: member diskon 50% + gratis ongkir; non-member ongkir 15.000, gratis jika >= 150.000)
$discount = 0; $ongkir = 0; $total = (float) $order['total'];
if (abs($total - ($subtotal + 15000)) < 0.5){ $ongkir = 15000; }
elseif (abs($total - $subtotal) < 0.5){ $ongkir = 0; }
else { $discount = max(0, $subtotal - $total); }   // member (atau selisih lain)

$isCod     = stripos($order['payment_method'], 'cod') !== false;
$isQris    = stripos($order['payment_method'], 'qris') !== false;
$confirmed = $order['status'] !== 'Baru';          // admin sudah memproses = pembayaran dianggap terverifikasi
$invNo     = 'INV/' . date('Ymd', strtotime($order['order_date'])) . '/' . str_pad($order['id'], 5, '0', STR_PAD_LEFT);

// Status pembayaran
if ($isCod){
    $payClass = 'cod';  $payText = $order['status'] === 'Selesai' ? 'Lunas (COD diterima)' : 'Bayar di Tempat (COD)';
    if ($order['status'] === 'Selesai') $payClass = 'ok';
} elseif ($confirmed){
    $payClass = 'ok';   $payText = 'Lunas';
} else {
    $payClass = 'wait'; $payText = 'Menunggu Verifikasi Pembayaran';
}

// Barcode Code 39 (angka) sebagai SVG
function code39_svg($digits, $height = 56){
    $map = ['0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn','4'=>'nnnwwnnnw',
            '5'=>'wnnwwnnnn','6'=>'nnwwwnnnn','7'=>'nnnwnnwnw','8'=>'wnnwnnwnn','9'=>'nnwwnnwnn','*'=>'nwnnwnwnn'];
    $text = '*' . $digits . '*';
    $n = 2; $w = 5; $x = 12; $bars = '';
    foreach (str_split($text) as $i => $ch){
        $pat = $map[$ch];
        for ($k = 0; $k < 9; $k++){
            $width = $pat[$k] === 'w' ? $w : $n;
            if ($k % 2 === 0) $bars .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '" fill="#000"/>';
            $x += $width;
        }
        $x += $n; // jarak antar karakter
    }
    $total = $x + 10;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $height . '" style="width:100%; max-width:300px; height:' . $height . 'px;" preserveAspectRatio="none"><rect width="' . $total . '" height="' . $height . '" fill="#fff"/>' . $bars . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title><?= $doc === 'label' ? 'Label Pengiriman' : 'Invoice' ?> #<?= $order['id'] ?> - Ark4nnzzStore</title>
<style>
  *{box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif;}
  body{background:#f2f2f2; padding:30px;}
  body.popup{background:#fff; padding:44px 14px 18px;}
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
    display:flex; justify-content:space-between; align-items:center; gap:10px; max-width:480px; margin:0 auto 16px;
  }
  .back-btn{
    padding:10px 22px; border:1.5px solid #c724d6; border-radius:8px; background:#fff; color:#c724d6; font-weight:700; text-decoration:none; font-size:14px;
  }
  .print-btn button{
    padding:10px 22px; border:none; border-radius:8px; background:#c724d6; color:#fff; font-weight:700; cursor:pointer;
  }
  @media print{ .print-btn{display:none;} body{background:#fff; padding:0;} .receipt{border:none;} }
  .receipt.invoice{border:1.5px solid #d6d6d6; border-style:solid; border-radius:12px; max-width:480px;}
  .inv-head{display:flex; justify-content:space-between; align-items:flex-start; gap:10px;}
  .inv-title{font-size:22px; font-weight:800; color:#8e1a9b; letter-spacing:.06em;}
  .inv-no{font-size:12px; color:#555; margin-top:2px;}
  .pill{display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:800;}
  .pill.ok{background:#d9f0e0; color:#1a7f37;}
  .pill.wait{background:#fbe9b8; color:#96730a;}
  .pill.cod{background:#e3eefc; color:#1d4f91;}
  .inv-box{display:flex; gap:14px; margin:12px 0; font-size:12.5px;}
  .inv-box > div{flex:1; background:#faf5fb; border-radius:8px; padding:10px 12px;}
  .inv-box small{display:block; color:#888; font-weight:700; text-transform:uppercase; font-size:10.5px; letter-spacing:.05em; margin-bottom:3px;}
  .steps{display:flex; justify-content:space-between; margin:14px 4px 6px; position:relative;}
  .steps:before{content:""; position:absolute; top:9px; left:8%; right:8%; height:2px; background:#e2e2e2;}
  .step{position:relative; text-align:center; font-size:10.5px; color:#999; flex:1;}
  .step i{display:block; width:20px; height:20px; border-radius:50%; background:#e2e2e2; margin:0 auto 4px; position:relative; z-index:1;}
  .step.done{color:#1a7f37; font-weight:700;} .step.done i{background:#1a7f37;}
  .step.now{color:#8e1a9b; font-weight:800;} .step.now i{background:#8e1a9b; box-shadow:0 0 0 4px #f0d4f4;}
  .pay-info{font-size:12px; background:#fff8e1; border:1px dashed #e0b400; border-radius:8px; padding:9px 12px; margin-top:10px; color:#6b5400;}

  /* ===== Label pengiriman (penjual) ===== */
  .label{max-width:420px; margin:0 auto; background:#fff; border:2.5px solid #000; border-radius:4px; font-family:Arial, Helvetica, sans-serif; color:#000;}
  .label .lb-top{display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-bottom:2.5px solid #000;}
  .label .lb-top b{font-size:16px; letter-spacing:.05em;}
  .label .lb-top span{font-size:11px;}
  .label .lb-cod{border-bottom:2.5px solid #000; text-align:center; padding:8px; background:#000; color:#fff;}
  .label .lb-cod .big{font-size:26px; font-weight:900; letter-spacing:.04em;}
  .label .lb-cod .sm{font-size:11px; letter-spacing:.08em;}
  .label .lb-noncod{border-bottom:2.5px solid #000; text-align:center; padding:7px; font-weight:900; font-size:15px; letter-spacing:.06em;}
  .label .lb-bar{text-align:center; padding:10px 12px 4px; border-bottom:1.5px solid #000;}
  .label .lb-bar .no{font-family:"Courier New", monospace; font-size:13px; font-weight:700; letter-spacing:.12em; margin-top:2px;}
  .label .lb-sec{padding:8px 12px; border-bottom:1.5px solid #000; font-size:13px;}
  .label .lb-sec small{display:block; font-size:10px; font-weight:800; letter-spacing:.1em; color:#444; margin-bottom:2px;}
  .label .lb-sec .name{font-size:16px; font-weight:900;}
  .label table{width:100%; border-collapse:collapse; font-size:12.5px;}
  .label th{text-align:left; font-size:10px; letter-spacing:.08em; border-bottom:1.5px solid #000; padding:3px 0;}
  .label td{padding:5px 0; border-bottom:1px dotted #888; vertical-align:top;}
  .label .box{display:inline-block; width:13px; height:13px; border:1.5px solid #000; margin-right:7px; vertical-align:-2px;}
  .label .lb-foot{display:flex; justify-content:space-between; padding:6px 12px; font-size:11px;}
  .locked{max-width:480px; margin:40px auto; text-align:center; background:#fff; border:2px dashed #999; border-radius:12px; padding:36px 24px;}
</style>
</head>
<body class="<?= isset($_GET['popup']) ? 'popup' : '' ?>">
<?php
  // Tujuan tombol Kembali: admin -> daftar pesanan; user -> beranda (setelah checkout) / profil (riwayat)
  if (is_admin()) $backUrl = BASE_URL . '/admin/orders.php';
  elseif (isset($_GET['success'])) $backUrl = BASE_URL . '/user/index.php';
  else $backUrl = BASE_URL . '/user/profile.php';
  $backLabel = (!is_admin() && isset($_GET['success'])) ? '← Kembali ke Beranda' : '← Kembali';
?>
<div class="print-btn">
  <?php if (isset($_GET['popup'])): ?>
    <button type="button" class="back-btn" style="cursor:pointer;" onclick="parent.postMessage('close-struk','*')">✕ Tutup</button>
  <?php else: ?>
    <a class="back-btn" href="<?= $backUrl ?>"><?= $backLabel ?></a>
  <?php endif; ?>
  <button onclick="window.print()">🖨️ Cetak <?= $doc === 'label' ? 'Label' : 'Invoice' ?></button>
</div>

<?php if ($doc === 'invoice'): ?>
<!-- ================= INVOICE : UNTUK PEMBELI ================= -->
<div class="receipt invoice">
  <div class="inv-head">
    <div>
      <div class="inv-title">INVOICE</div>
      <div class="inv-no"><?= $invNo ?></div>
      <div class="inv-no">Tanggal Pembelian: <?= date('d F Y, H:i', strtotime($order['order_date'])) ?> WIB</div>
    </div>
    <div style="text-align:right;">
      <div class="brand" style="font-size:15px; color:#8e1a9b; font-weight:800;">📚 Ark4nnzzStore</div>
      <span class="pill <?= $payClass ?>" style="margin-top:6px;"><?= $payText ?></span>
    </div>
  </div>

  <div class="inv-box">
    <div><small>Pembeli</small><strong><?= h($order['nama_pemesan']) ?></strong><br><?= h($order['email']) ?></div>
    <div><small>Penjual</small><strong><?= h(STORE_NAME) ?></strong><br><?= h(STORE_ADDRESS) ?></div>
  </div>

  <div class="steps">
    <?php
      $flow = ['Baru' => 'Pesanan Dibuat', 'Diproses' => 'Diproses', 'Dikirim' => 'Dikirim', 'Selesai' => 'Selesai'];
      $keys = array_keys($flow); $curIdx = array_search($order['status'], $keys);
      foreach ($flow as $k => $lbl):
        $idx = array_search($k, $keys);
        $cls = $idx < $curIdx ? 'done' : ($idx === $curIdx ? 'now' : '');
    ?>
      <div class="step <?= $cls ?>"><i></i><?= $lbl ?></div>
    <?php endforeach; ?>
  </div>

  <div class="r-divider"></div>
  <div class="r-items">
    <table>
      <thead><tr><th>Produk</th><th>Qty</th><th style="text-align:right;">Harga</th></tr></thead>
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

  <div style="font-size:12px; font-weight:800; color:#555; margin-bottom:4px;">RINCIAN PEMBAYARAN</div>
  <div class="r-row"><span>Total Harga (<?= $totalQty ?> barang)</span><span><?= rupiah($subtotal) ?></span></div>
  <?php if ($discount > 0): ?>
    <div class="r-row"><span>Diskon Member Ark4nnzz Club</span><span style="color:#1a7f37;">- <?= rupiah($discount) ?></span></div>
  <?php endif; ?>
  <div class="r-row"><span>Total Ongkos Kirim</span><span><?= $ongkir > 0 ? rupiah($ongkir) : 'GRATIS' ?></span></div>
  <div class="r-total"><span>Total Belanja</span><span><?= rupiah($order['total']) ?></span></div>
  <div class="r-row" style="margin-top:6px;"><span>Metode Pembayaran</span><strong><?= h($order['payment_method']) ?></strong></div>

  <div class="r-divider"></div>
  <div style="font-size:12px; font-weight:800; color:#555; margin-bottom:4px;">ALAMAT PENGIRIMAN</div>
  <div style="font-size:13px;"><strong><?= h($order['nama_pemesan']) ?></strong><?php if (!empty($order['shipping_phone'])): ?> &middot; <?= h($order['shipping_phone']) ?><?php endif; ?><br><?= nl2br(h($order['shipping_address'])) ?></div>
  <?php if (!empty($order['courier']) || !empty($order['tracking_no'])): ?>
    <div class="r-divider"></div>
    <div style="font-size:12px; font-weight:800; color:#555; margin-bottom:4px;">INFO PENGIRIMAN</div>
    <div class="r-row"><span>Kurir</span><strong><?= !empty($order['courier']) ? h($order['courier']) : '-' ?></strong></div>
    <div class="r-row"><span>No. Resi</span><strong style="font-family:'Courier New', monospace;"><?= !empty($order['tracking_no']) ? h($order['tracking_no']) : '-' ?></strong></div>
  <?php endif; ?>

  <?php if (!$isCod && !$confirmed): ?>
    <div class="pay-info">
      <?php if ($isQris): ?>
        Selesaikan pembayaran lewat <strong>QRIS / e-wallet</strong>. Pesanan diproses setelah admin memverifikasi bukti pembayaranmu.
      <?php else: ?>
        Transfer ke <strong>Bank BSI 7370615769 a.n. Arkan Naufal</strong>. Pesanan diproses setelah admin memverifikasi bukti pembayaranmu.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($order['payment_proof']) && upload_ensure($pdo, $order['payment_proof'])): ?>
    <div style="text-align:center; margin:12px 0 0;">
      <img src="<?= UPLOAD_URL . h($order['payment_proof']) ?>" alt="Bukti Pembayaran" style="max-width:180px; border-radius:8px; border:1px solid #ddd;">
      <div style="font-size:11px; color:#888; margin-top:4px;">📎 Bukti pembayaran yang kamu kirim</div>
    </div>
  <?php endif; ?>

  <div class="r-footer" style="margin-top:14px;">
    Terima kasih telah berbelanja di Ark4nnzzStore 🙏<br>
    <em>Invoice ini sah dan diproses oleh komputer.</em>
  </div>
</div>

<?php else: ?>
<!-- ============ LABEL PENGIRIMAN + PACKING LIST : UNTUK PENJUAL ============ -->
<div class="label">
  <div class="lb-top"><b>📦 LABEL PENGIRIMAN</b><span>Ark4nnzzStore</span></div>

  <?php if ($isCod): ?>
    <div class="lb-cod"><div class="sm">COD &mdash; TAGIH KE PENERIMA</div><div class="big"><?= rupiah($order['total']) ?></div></div>
  <?php else: ?>
    <div class="lb-noncod"><?= $confirmed ? 'NON-COD &middot; SUDAH DIBAYAR' : 'NON-COD &middot; CEK BUKTI BAYAR DULU' ?></div>
  <?php endif; ?>

  <div class="lb-bar">
    <?= code39_svg(str_pad($order['id'], 8, '0', STR_PAD_LEFT)) ?>
    <div class="no"><?= str_pad($order['id'], 8, '0', STR_PAD_LEFT) ?></div>
  </div>

  <div class="lb-sec">
    <small>PENERIMA</small>
    <div class="name"><?= h($order['nama_pemesan']) ?><?php if (!empty($order['shipping_phone'])): ?> &middot; <?= h($order['shipping_phone']) ?><?php endif; ?></div>
    <div><?= nl2br(h($order['shipping_address'])) ?></div>
  </div>
  <div class="lb-sec" style="display:flex; justify-content:space-between; gap:10px;">
    <div><small>KURIR</small><strong><?= !empty($order['courier']) ? h($order['courier']) : '-' ?></strong></div>
    <div style="text-align:right;"><small>NO. RESI</small><strong style="font-family:'Courier New', monospace; letter-spacing:.06em;"><?= !empty($order['tracking_no']) ? h($order['tracking_no']) : '(belum diinput)' ?></strong></div>
  </div>
  <div class="lb-sec">
    <small>PENGIRIM</small>
    <div class="name" style="font-size:14px;"><?= h(STORE_NAME) ?></div>
    <div><?= h(STORE_ADDRESS) ?></div>
  </div>

  <div class="lb-sec">
    <small>PACKING LIST &mdash; <?= $totalQty ?> BARANG</small>
    <table>
      <thead><tr><th>PRODUK</th><th style="text-align:right;">QTY</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr><td><span class="box"></span><?= h($it['title']) ?></td><td style="text-align:right; font-weight:800;"><?= $it['qty'] ?>x</td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="lb-sec" style="border-bottom:none; display:flex; justify-content:space-between; gap:10px;">
    <div><small>NO. PESANAN</small>#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></div>
    <div><small>TGL PESAN</small><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></div>
    <div><small>STATUS</small><?= h($order['status']) ?></div>
  </div>
  <div class="lb-foot" style="border-top:1.5px solid #000;"><span>Pembayaran: <?= h($order['payment_method']) ?></span><span>Dicetak <?= date('d/m/Y H:i') ?></span></div>
</div>
<?php endif; ?>
</body>
</html>
