<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');
$userId = $_SESSION['user_id'];

// Toggle add/remove wishlist (dipakai dari tombol hati di kartu buku)
if (isset($_GET['toggle'])){
    $bookId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$userId, $bookId]);
    $existing = $stmt->fetch();
    if ($existing){
        $pdo->prepare("DELETE FROM wishlists WHERE id = ?")->execute([$existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO wishlists (user_id, book_id) VALUES (?, ?)")->execute([$userId, $bookId]);
    }
    $redirect = $_GET['redirect'] ?? (BASE_URL . '/user/wishlist.php');
    header("Location: " . $redirect);
    exit;
}

if (isset($_GET['remove'])){
    $stmt = $pdo->prepare("DELETE FROM wishlists WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['remove'], $userId]);
    header("Location: " . BASE_URL . "/user/wishlist.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT w.id AS wishlist_id, b.*, c.name AS category_name
    FROM wishlists w
    JOIN books b ON b.id = w.book_id
    JOIN categories c ON c.id = b.category_id
    WHERE w.user_id = ? ORDER BY w.created_at DESC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$pageTitle = 'Wishlist Saya';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="cart-page">
  <h1 style="font-size:26px; font-weight:800; margin-bottom:20px;">❤️ Wishlist Saya</h1>

  <?php if (!$items): ?>
    <p style="color:#999; margin-bottom:20px;">Belum ada buku yang kamu simpan. Klik ikon ❤️ di kartu buku buat nyimpen buku favorit kamu.</p>
    <a href="<?= BASE_URL ?>/user/books.php" class="btn btn-primary">Jelajahi Buku</a>
  <?php else: ?>
    <div class="book-grid">
      <?php foreach ($items as $b): $lowStock = $b['stock']>0 && $b['stock']<=5; $soldOut = $b['stock']<=0; ?>
        <div class="book-card">
          <div class="cover" style="position:relative;">
            <?php if ($lowStock): ?><span class="ribbon ribbon-hot">🔥 Stok Terbatas!</span><?php endif; ?>
            <a href="<?= BASE_URL ?>/user/wishlist.php?remove=<?= $b['wishlist_id'] ?>" class="wishlist-heart active" title="Hapus dari wishlist" onclick="return confirm('Hapus dari wishlist?');">♥</a>
            <a href="<?= BASE_URL ?>/user/book_detail.php?id=<?= $b['id'] ?>">
              <img src="<?= $b['cover_image'] ? UPLOAD_URL . h($b['cover_image']) : 'https://placehold.co/200x280?text=' . urlencode($b['title']) ?>" alt="<?= h($b['title']) ?>">
            </a>
          </div>
          <div class="book-info">
            <a href="<?= BASE_URL ?>/user/book_detail.php?id=<?= $b['id'] ?>"><h3><?= h($b['title']) ?></h3></a>
            <div class="author"><?= h($b['author']) ?></div>
            <div class="price-row">
              <span class="price"><?= rupiah($b['price']) ?></span>
              <span class="rating">⭐ <?= number_format($b['rating'],1) ?></span>
            </div>
            <div class="stock-info <?= $lowStock?'urgent':'' ?>">
              <?= $soldOut ? '❌ Stok habis' : ($lowStock ? "⚡ Sisa {$b['stock']} — buruan!" : "Stok: {$b['stock']}") ?>
            </div>
            <?php if (!$soldOut): ?>
            <div class="card-btn-row">
              <form method="post" action="<?= BASE_URL ?>/user/buy_now.php">
                <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="buy-now-btn">⚡ Beli Langsung</button>
              </form>
              <form method="post" action="<?= BASE_URL ?>/user/cart.php">
                <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="action" value="add">
                <button type="submit" class="add-btn">+ Keranjang</button>
              </form>
            </div>
            <?php else: ?>
              <button type="button" class="add-btn" disabled style="opacity:.5; cursor:not-allowed;">Stok Habis</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
