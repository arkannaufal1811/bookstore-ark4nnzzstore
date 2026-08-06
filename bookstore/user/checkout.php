<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT ci.id AS cart_id, ci.qty, b.* FROM cart_items ci
    JOIN books b ON b.id = ci.book_id WHERE ci.user_id = ?
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if (!$items){
    header("Location: " . BASE_URL . "/user/cart.php");
    exit;
}

$subtotal = 0;
foreach ($items as $it){ $subtotal += $it['price'] * $it['qty']; }
$ongkir = $subtotal >= 150000 ? 0 : 15000;
$total = $subtotal + $ongkir;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $address = trim($_POST['address'] ?? '');
    if ($address === ''){
        $error = 'Alamat pengiriman wajib diisi.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, payment_method, shipping_address, status) VALUES (?, ?, 'COD (Bayar di Tempat)', ?, 'Diproses')");
            $stmt->execute([$userId, $total, $address]);
            $orderId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, book_id, qty, price) VALUES (?, ?, ?, ?)");
            $stmtStock = $pdo->prepare("UPDATE books SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
            foreach ($items as $it){
                $stmtItem->execute([$orderId, $it['id'], $it['qty'], $it['price']]);
                $stmtStock->execute([$it['qty'], $it['id']]);
            }

            $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
            $pdo->commit();

            header("Location: " . BASE_URL . "/struk.php?id=" . $orderId . "&success=1");
            exit;
        } catch (Exception $e){
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat memproses pesanan. Coba lagi.';
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page">
  <h1 style="font-size:26px; font-weight:800; margin-bottom:20px;">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <div class="panel" style="margin-bottom:20px;">
    <table class="data-table">
      <thead><tr><th>Buku</th><th>Qty</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= h($it['title']) ?></td>
          <td><?= $it['qty'] ?></td>
          <td><?= rupiah($it['price'] * $it['qty']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post">
    <div class="form-group">
      <label style="font-weight:700; display:block; margin-bottom:8px;">Alamat Pengiriman</label>
      <textarea name="address" rows="3" placeholder="Masukkan alamat lengkap pengiriman" style="width:100%; padding:12px; border:1.5px solid #ccc; border-radius:8px;" required><?= h($_POST['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label style="font-weight:700; display:block; margin-bottom:8px;">Metode Pembayaran</label>
      <div style="border:1.5px solid var(--primary); background:var(--primary-lighter); border-radius:8px; padding:14px;">
        💵 <strong>Payment at Delivery (COD)</strong> — Bayar tunai saat buku diterima
      </div>
    </div>

    <div class="cart-summary">
      <div class="row"><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></div>
      <div class="row"><span>Ongkos Kirim</span><span><?= $ongkir == 0 ? 'GRATIS' : rupiah($ongkir) ?></span></div>
      <div class="row total"><span>Total Bayar</span><span><?= rupiah($total) ?></span></div>
      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">Buat Pesanan (COD)</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
