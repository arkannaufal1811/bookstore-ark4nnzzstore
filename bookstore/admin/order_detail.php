<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/admin/orders.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $status = $_POST['status'] ?? 'Diproses';
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    header("Location: " . BASE_URL . "/admin/order_detail.php?id=$id&msg=saved");
    exit;
}

$stmt = $pdo->prepare("SELECT o.*, u.name AS nama_pemesan, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order){ header("Location: " . BASE_URL . "/admin/orders.php"); exit; }

$stmt = $pdo->prepare("SELECT oi.*, b.title FROM order_items oi JOIN books b ON b.id = oi.book_id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$pageTitle = 'Detail Pesanan';
$active = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Detail Pesanan #<?= $order['id'] ?></h1>
  <a href="<?= BASE_URL ?>/struk.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline">🧾 Lihat / Cetak Struk</a>
</div>

<?php if (isset($_GET['msg'])): ?><div class="alert alert-success">Status pesanan diperbarui.</div><?php endif; ?>

<div class="panel" style="margin-bottom:24px;">
  <div class="panel-head"><h2>Informasi Pemesan</h2></div>
  <div style="padding:0 22px 22px;">
    <p><strong>Nama:</strong> <?= h($order['nama_pemesan']) ?></p>
    <p><strong>Email:</strong> <?= h($order['email']) ?></p>
    <p><strong>Alamat Pengiriman:</strong> <?= nl2br(h($order['shipping_address'])) ?></p>
    <p><strong>Metode Pembayaran:</strong> <?= h($order['payment_method']) ?></p>
    <p><strong>Tanggal:</strong> <?= date('d F Y H:i', strtotime($order['order_date'])) ?></p>
  </div>
</div>

<div class="panel" style="margin-bottom:24px;">
  <table class="data-table">
    <thead><tr><th>Buku</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= h($it['title']) ?></td>
        <td><?= $it['qty'] ?></td>
        <td><?= rupiah($it['price']) ?></td>
        <td><?= rupiah($it['price'] * $it['qty']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr><td colspan="3" style="text-align:right; font-weight:800;">Total</td><td style="font-weight:800;"><?= rupiah($order['total']) ?></td></tr>
    </tbody>
  </table>
</div>

<form method="post" class="admin-form">
  <div class="form-group">
    <label>Status Pesanan</label>
    <select name="status">
      <option value="Diproses" <?= $order['status']==='Diproses'?'selected':'' ?>>Diproses</option>
      <option value="Dikirim" <?= $order['status']==='Dikirim'?'selected':'' ?>>Dikirim</option>
      <option value="Selesai" <?= $order['status']==='Selesai'?'selected':'' ?>>Selesai</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Update Status</button>
  <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline">Kembali</a>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
