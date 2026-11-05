<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$userId = $_SESSION['user_id'];

// Nomor HP tersimpan di akun (kalau sudah ada)
$savedPhone = '';
if (db_has_column($pdo, 'users', 'phone')){
    $stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $savedPhone = (string) $stmt->fetchColumn();
}

// Ubah jumlah langsung di halaman checkout (dibatasi stok)
$qtyNotice = '';
if (isset($_GET['inc']) || isset($_GET['dec'])){
    $itemId = $_GET['inc'] ?? $_GET['dec'];
    $stmt = $pdo->prepare("SELECT ci.*, b.stock FROM cart_items ci JOIN books b ON b.id = ci.book_id WHERE ci.id = ? AND ci.user_id = ?");
    $stmt->execute([$itemId, $userId]);
    $row = $stmt->fetch();
    $flag = '';
    if ($row){
        if (isset($_GET['inc'])){
            if ($row['qty'] >= $row['stock']){ $newQty = max(1, (int) $row['stock']); $flag = '?limited=1'; }
            else { $newQty = $row['qty'] + 1; }
        } else {
            $newQty = max(1, $row['qty'] - 1);
        }
        $pdo->prepare("UPDATE cart_items SET qty = ? WHERE id = ?")->execute([$newQty, $itemId]);
    }
    header("Location: " . BASE_URL . "/user/checkout.php" . $flag);
    exit;
}
if (isset($_GET['limited'])) $qtyNotice = 'Jumlah dibatasi sesuai stok yang tersedia.';

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

$isMember = is_member_user($pdo);
$memberDiscount = $isMember ? round($subtotal * 0.5) : 0;
$subtotalAfterDiscount = $subtotal - $memberDiscount;
$ongkir = ($isMember || $subtotalAfterDiscount >= 150000) ? 0 : 15000;
$total = $subtotalAfterDiscount + $ongkir;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $address = trim($_POST['address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $phone = normalize_phone($_POST['phone'] ?? '');

    $paymentLabels = ['cod' => 'COD (Bayar di Tempat)', 'transfer' => 'Transfer Bank', 'qris' => 'QRIS / E-Wallet'];
    if (!isset($paymentLabels[$paymentMethod])) $paymentMethod = 'cod';
    $paymentLabel = $paymentLabels[$paymentMethod];
    $needProof = in_array($paymentMethod, ['transfer', 'qris']);
    $proofName = null;

    if ($phone === false){
        $error = 'Nomor HP penerima tidak valid. Contoh: 081234567890';
    } elseif ($address === ''){
        $error = 'Alamat pengiriman wajib diisi.';
    } elseif ($needProof && empty($_FILES['payment_proof']['name'])){
        $error = 'Bukti pembayaran wajib diupload untuk metode ' . $paymentLabel . '.';
    } else {
        if ($needProof && !empty($_FILES['payment_proof']['name'])){
            $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowedExt)){
                $error = 'Format bukti transfer harus JPG, PNG, atau WEBP.';
            } else {
                $proofName = 'bukti_' . uniqid() . '.' . $ext;
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
                move_uploaded_file($_FILES['payment_proof']['tmp_name'], UPLOAD_DIR . $proofName);
                upload_backup_save($pdo, $proofName);
            }
        }

        if (!$error){
            $pdo->beginTransaction();
            try {
                // ===== SISTEM ANTI-REBUTAN STOK (siapa checkout duluan yang dapat) =====
                // Kunci baris buku yang mau dibeli, biar kalau ada 2 orang checkout stok terakhir
                // bersamaan, cuma salah satu yang berhasil (gak bisa oversell / stok minus).
                $stmtLock = $pdo->prepare("SELECT id, title, stock FROM books WHERE id = ? FOR UPDATE");
                foreach ($items as $it){
                    $stmtLock->execute([$it['id']]);
                    $locked = $stmtLock->fetch();
                    if (!$locked || $locked['stock'] < $it['qty']){
                        throw new Exception("Yah, kehabisan! Stok '{$it['title']}' baru aja diambil pembeli lain. Coba kurangi jumlahnya atau pilih buku lain ya.");
                    }
                }

                if (db_has_column($pdo, 'orders', 'shipping_phone')){
                    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, payment_method, payment_proof, shipping_address, shipping_phone, status) VALUES (?, ?, ?, ?, ?, ?, 'Baru')");
                    $stmt->execute([$userId, $total, $paymentLabel, $proofName, $address, $phone]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, payment_method, payment_proof, shipping_address, status) VALUES (?, ?, ?, ?, ?, 'Baru')");
                    $stmt->execute([$userId, $total, $paymentLabel, $proofName, $address]);
                }

                $orderId = $pdo->lastInsertId();
                // simpan nomor HP ke akun kalau belum ada (setelah ID pesanan diambil)
                if (db_has_column($pdo, 'users', 'phone') && $savedPhone === ''){
                    $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $userId]);
                }

                $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, book_id, qty, price) VALUES (?, ?, ?, ?)");
                $stmtStock = $pdo->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
                foreach ($items as $it){
                    $stmtItem->execute([$orderId, $it['id'], $it['qty'], $it['price']]);
                    $stmtStock->execute([$it['qty'], $it['id']]);
                }

                $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);

                // Poin reward buat member: 1 poin tiap kelipatan Rp10.000 belanja
                if ($isMember){
                    $earnedPoints = (int) floor($total / 10000);
                    if ($earnedPoints > 0){
                        $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$earnedPoints, $userId]);
                    }
                }

                $pdo->commit();

                header("Location: " . BASE_URL . "/user/index.php?struk=" . $orderId . "&success=1");
                exit;
            } catch (Throwable $e){
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = ($e instanceof PDOException) ? 'Database error: ' . $e->getMessage() . ' (cek apakah semua migration sudah dijalankan)' : ($e->getMessage() ?: 'Terjadi kesalahan saat memproses pesanan. Coba lagi.');
            }
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page">
  <h1 style="font-size:26px; font-weight:800; margin-bottom:20px;">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($qtyNotice): ?><div class="alert alert-error"><?= h($qtyNotice) ?></div><?php endif; ?>

  <div class="panel" style="margin-bottom:20px;">
    <table class="data-table">
      <thead><tr><th>Buku</th><th>Jumlah</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= h($it['title']) ?></td>
          <td>
            <div class="qty" style="display:flex; align-items:center; gap:10px;">
              <a href="?dec=<?= $it['cart_id'] ?>"><button type="button" style="width:28px; height:28px; border:1.5px solid #333; background:#fff; border-radius:6px; font-weight:800;">-</button></a>
              <span><?= $it['qty'] ?></span>
              <?php if ($it['qty'] >= $it['stock']): ?>
                <button type="button" disabled title="Sudah mencapai batas stok" style="width:28px; height:28px; border:1.5px solid #333; background:#fff; border-radius:6px; font-weight:800; opacity:.4; cursor:not-allowed;">+</button>
              <?php else: ?>
                <a href="?inc=<?= $it['cart_id'] ?>"><button type="button" style="width:28px; height:28px; border:1.5px solid #333; background:#fff; border-radius:6px; font-weight:800;">+</button></a>
              <?php endif; ?>
            </div>
            <div style="font-size:12px; color:<?= $it['qty'] >= $it['stock'] ? '#a51c1c' : 'var(--gray)' ?>; margin-top:4px;">Stok: <?= (int) $it['stock'] ?></div>
          </td>
          <td><?= rupiah($it['price'] * $it['qty']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" enctype="multipart/form-data">
    <div class="form-group">
      <label style="font-weight:700; display:block; margin-bottom:8px;">Nomor HP Penerima</label>
      <input type="tel" name="phone" placeholder="Contoh: 081234567890" value="<?= h($_POST['phone'] ?? $savedPhone) ?>" style="width:100%; padding:12px; border:1.5px solid #ccc; border-radius:8px;" required>
    </div>

    <div class="form-group">
      <label style="font-weight:700; display:block; margin-bottom:8px;">Alamat Pengiriman</label>
      <textarea name="address" rows="3" placeholder="Masukkan alamat lengkap pengiriman" style="width:100%; padding:12px; border:1.5px solid #ccc; border-radius:8px;" required><?= h($_POST['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label style="font-weight:700; display:block; margin-bottom:10px;">Metode Pembayaran</label>

      <label class="payment-option">
        <input type="radio" name="payment_method" value="cod" onclick="selectPay('cod')" <?= (($_POST['payment_method'] ?? 'cod')==='cod')?'checked':'' ?>>
        <div class="payment-option-body">
          <strong>💵 COD (Bayar di Tempat)</strong>
          <p>Bayar tunai saat buku diterima kurir.</p>
        </div>
      </label>

      <label class="payment-option">
        <input type="radio" name="payment_method" value="transfer" onclick="selectPay('transfer')" <?= (($_POST['payment_method'] ?? '')==='transfer')?'checked':'' ?>>
        <div class="payment-option-body">
          <strong>🏦 Transfer Bank</strong>
          <p>Transfer ke rekening toko, lalu upload bukti transfer.</p>
        </div>
      </label>

      <label class="payment-option">
        <input type="radio" name="payment_method" value="qris" onclick="selectPay('qris')" <?= (($_POST['payment_method'] ?? '')==='qris')?'checked':'' ?>>
        <div class="payment-option-body">
          <strong>📱 QRIS / E-Wallet</strong>
          <p>Scan QRIS pakai DANA, OVO, GoPay, ShopeePay, atau m-banking.</p>
        </div>
      </label>

      <div id="transferBox" class="transfer-box" style="display:<?= (($_POST['payment_method'] ?? '')==='transfer') ? 'block' : 'none' ?>;">
        <div class="transfer-rekening">
          🏦 <strong>Bank BSI</strong> &middot; 7370615769 &middot; a.n. Arkan Naufal
        </div>
      </div>

      <div id="qrisBox" class="transfer-box" style="display:<?= (($_POST['payment_method'] ?? '')==='qris') ? 'block' : 'none' ?>; text-align:center;">
        <img src="<?= BASE_URL ?>/assets/images/qris-ala.svg" alt="QRIS" style="width:220px; max-width:100%;">
        <div style="margin-top:10px; display:flex; gap:6px; justify-content:center; flex-wrap:wrap;">
          <span class="ewallet-chip" style="background:#118eea;">DANA</span>
          <span class="ewallet-chip" style="background:#4c2a86;">OVO</span>
          <span class="ewallet-chip" style="background:#00aed6;">GoPay</span>
          <span class="ewallet-chip" style="background:#ee4d2d;">ShopeePay</span>
        </div>
        <p style="font-size:12.5px; color:var(--gray); margin-top:8px;">Scan QRIS di atas, bayar sesuai total pesanan, lalu upload bukti pembayarannya.</p>
      </div>

      <div id="proofBox" class="transfer-box" style="display:<?= in_array(($_POST['payment_method'] ?? ''), ['transfer','qris']) ? 'block' : 'none' ?>;">
        <label style="font-weight:700; font-size:13px; display:block; margin:0 0 6px;">Upload Bukti Pembayaran</label>
        <input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp">
        <p style="font-size:12px; color:var(--gray); margin-top:6px;">Format JPG/PNG/WEBP. Pesanan akan diverifikasi admin setelah bukti pembayaran diterima.</p>
      </div>

      <script>
      function selectPay(m){
        document.getElementById('transferBox').style.display = (m === 'transfer') ? 'block' : 'none';
        document.getElementById('qrisBox').style.display     = (m === 'qris') ? 'block' : 'none';
        document.getElementById('proofBox').style.display    = (m === 'cod') ? 'none' : 'block';
      }
      </script>
    </div>

    <div class="cart-summary">
      <?php if ($isMember): ?>
        <div class="alert alert-success" style="margin-bottom:14px;">👑 Kamu member Ark4nnzz Club — diskon 50% & gratis ongkir otomatis diterapkan!</div>
      <?php endif; ?>
      <div class="row"><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></div>
      <?php if ($isMember): ?>
        <div class="row" style="color:#178a4c;"><span>Diskon Member (50%)</span><span>- <?= rupiah($memberDiscount) ?></span></div>
      <?php endif; ?>
      <div class="row"><span>Ongkos Kirim</span><span><?= $ongkir == 0 ? 'GRATIS' : rupiah($ongkir) ?></span></div>
      <div class="row total"><span>Total Bayar</span><span><?= rupiah($total) ?></span></div>
      <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">Buat Pesanan</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
