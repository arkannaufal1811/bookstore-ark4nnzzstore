<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$userId = $_SESSION['user_id'];

// Tambah ke keranjang (dibatasi stok)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add'){
    $bookId = (int) $_POST['book_id'];
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    $stmt = $pdo->prepare("SELECT id, stock FROM books WHERE id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
    if (!$book || $book['stock'] < 1){
        header("Location: " . BASE_URL . "/user/cart.php?soldout=1");
        exit;
    }
    $stock = (int) $book['stock'];

    $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$userId, $bookId]);
    $existing = $stmt->fetch();

    $inCart = $existing ? (int) $existing['qty'] : 0;
    $newQty = min($inCart + $qty, $stock);   // tidak boleh melebihi stok
    $limited = ($inCart + $qty) > $stock;

    if ($existing){
        $stmt = $pdo->prepare("UPDATE cart_items SET qty = ? WHERE id = ?");
        $stmt->execute([$newQty, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, book_id, qty) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $bookId, $newQty]);
    }
    header("Location: " . BASE_URL . "/user/cart.php?" . ($limited ? "limited=1" : "added=1"));
    exit;
}

// Update qty (tombol + dibatasi stok)
if (isset($_GET['inc']) || isset($_GET['dec'])){
    $itemId = $_GET['inc'] ?? $_GET['dec'];
    $stmt = $pdo->prepare("SELECT ci.*, b.stock FROM cart_items ci JOIN books b ON b.id = ci.book_id WHERE ci.id = ? AND ci.user_id = ?");
    $stmt->execute([$itemId, $userId]);
    $item = $stmt->fetch();
    $flag = '';
    if ($item){
        if (isset($_GET['inc'])){
            if ($item['qty'] >= $item['stock']){
                $newQty = max(1, (int) $item['stock']);
                $flag = '?limited=1';
            } else {
                $newQty = $item['qty'] + 1;
            }
        } else {
            $newQty = max(1, $item['qty'] - 1);
        }
        $stmt = $pdo->prepare("UPDATE cart_items SET qty = ? WHERE id = ?");
        $stmt->execute([$newQty, $itemId]);
    }
    header("Location: " . BASE_URL . "/user/cart.php" . $flag);
    exit;
}

// Hapus item
if (isset($_GET['remove'])){
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['remove'], $userId]);
    header("Location: " . BASE_URL . "/user/cart.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT ci.id AS cart_id, ci.qty, b.* FROM cart_items ci
    JOIN books b ON b.id = ci.book_id
    WHERE ci.user_id = ?
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

// Rapikan keranjang lama yang jumlahnya sudah melebihi stok
foreach ($items as &$it){
    if ($it['stock'] > 0 && $it['qty'] > $it['stock']){
        $it['qty'] = (int) $it['stock'];
        $pdo->prepare("UPDATE cart_items SET qty = ? WHERE id = ?")->execute([$it['qty'], $it['cart_id']]);
        $_GET['limited'] = 1;
    }
}
unset($it);

$subtotal = 0;
foreach ($items as $it){ $subtotal += $it['price'] * $it['qty']; }
$ongkir = $subtotal >= 150000 || $subtotal == 0 ? 0 : 15000;
$total = $subtotal + $ongkir;

$pageTitle = 'Keranjang';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page">
  <h1 style="font-size:26px; font-weight:800; margin-bottom:20px;">🛒 Keranjang Belanja</h1>

  <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">Buku berhasil ditambahkan ke keranjang!</div>
  <?php endif; ?>

  <?php if (isset($_GET['limited'])): ?>
    <div class="alert alert-error">Jumlah dibatasi sesuai stok yang tersedia — kamu tidak bisa menambah lebih dari stok buku.</div>
  <?php endif; ?>
  <?php if (isset($_GET['soldout'])): ?>
    <div class="alert alert-error">Maaf, stok buku tersebut sedang habis.</div>
  <?php endif; ?>

  <?php if (!$items): ?>
    <p style="color:#999; margin-bottom:20px;">Keranjang kamu masih kosong.</p>
    <a href="<?= BASE_URL ?>/user/books.php" class="btn btn-primary">Mulai Belanja</a>
  <?php else: ?>
    <?php foreach ($items as $it): ?>
      <div class="cart-item">
        <img src="<?= $it['cover_image'] ? UPLOAD_URL . h($it['cover_image']) : 'https://placehold.co/60x82?text=Buku' ?>" alt="">
        <div class="info">
          <h4><?= h($it['title']) ?></h4>
          <div style="color:var(--gray); font-size:13px;"><?= rupiah($it['price']) ?> / buku</div>
          <div style="font-size:12px; color:<?= $it['qty'] >= $it['stock'] ? '#a51c1c' : 'var(--gray)' ?>;">Stok tersedia: <?= (int) $it['stock'] ?><?= $it['qty'] >= $it['stock'] ? ' (maksimal tercapai)' : '' ?></div>
        </div>
        <div class="qty">
          <a href="?dec=<?= $it['cart_id'] ?>"><button type="button">-</button></a>
          <span><?= $it['qty'] ?></span>
          <?php if ($it['qty'] >= $it['stock']): ?>
            <button type="button" disabled title="Sudah mencapai batas stok" style="opacity:.4; cursor:not-allowed;">+</button>
          <?php else: ?>
            <a href="?inc=<?= $it['cart_id'] ?>"><button type="button">+</button></a>
          <?php endif; ?>
        </div>
        <div style="width:120px; text-align:right; font-weight:800;"><?= rupiah($it['price'] * $it['qty']) ?></div>
        <a href="?remove=<?= $it['cart_id'] ?>" onclick="return confirm('Hapus buku ini dari keranjang?');" style="color:#a51c1c; font-size:20px;">🗑️</a>
      </div>
    <?php endforeach; ?>

    <div class="cart-summary">
      <div class="row"><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></div>
      <div class="row"><span>Ongkos Kirim</span><span><?= $ongkir == 0 ? 'GRATIS' : rupiah($ongkir) ?></span></div>
      <div class="row total"><span>Total</span><span><?= rupiah($total) ?></span></div>
      <a href="<?= BASE_URL ?>/user/checkout.php" class="btn btn-primary" style="display:block; text-align:center; margin-top:16px;">Lanjut ke Checkout</a>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
