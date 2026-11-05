<?php
require_once __DIR__ . '/../includes/auth.php';

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/user/books.php"); exit; }

$stmt = $pdo->prepare("SELECT b.*, c.name AS category_name FROM books b JOIN categories c ON c.id=b.category_id WHERE b.id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();
if (!$book){ header("Location: " . BASE_URL . "/user/books.php"); exit; }

// Submit ulasan baru (satu ulasan per user per buku, submit ulang = update)
$reviewError = '';
$reviewSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])){
    require_login('/user/login.php');
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5){
        $reviewError = 'Pilih rating bintang dulu ya.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO reviews (book_id, user_id, rating, comment) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP");
        $stmt->execute([$id, $_SESSION['user_id'], $rating, $comment]);
        $reviewSuccess = true;
    }
}

$ratingInfo = book_rating($pdo, $book);

// Ambil daftar ulasan
$stmt = $pdo->prepare("
    SELECT r.*, u.name AS reviewer_name FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.book_id = ? ORDER BY r.created_at DESC
");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// Cek apakah user yang login sudah pernah review (buat prefill form)
$myReview = null;
if (is_logged_in()){
    foreach ($reviews as $r){
        if ($r['user_id'] == $_SESSION['user_id']){ $myReview = $r; break; }
    }
}

// Buku terkait (kategori sama, exclude buku ini sendiri)
$stmt = $pdo->prepare("
    SELECT b.*, c.name AS category_name FROM books b
    JOIN categories c ON c.id = b.category_id
    WHERE b.category_id = ? AND b.id != ?
    ORDER BY RAND() LIMIT 4
");
$stmt->execute([$book['category_id'], $id]);
$relatedBooks = $stmt->fetchAll();

$isWishlisted = is_wishlisted($pdo, $id);

$pageTitle = $book['title'];
$active = 'books';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="section" style="display:flex; gap:40px; flex-wrap:wrap;">
  <div style="width:280px; flex-shrink:0;">
    <div class="book-card" style="border-radius:10px;">
      <div class="cover">
        <?php if (is_logged_in()): ?>
          <a href="<?= BASE_URL ?>/user/wishlist.php?toggle=<?= $id ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="wishlist-heart <?= $isWishlisted?'active':'' ?>" title="Simpan ke wishlist"><?= $isWishlisted?'♥':'♡' ?></a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/user/login.php" class="wishlist-heart" title="Login untuk simpan ke wishlist">♡</a>
        <?php endif; ?>
        <img src="<?= $book['cover_image'] ? UPLOAD_URL . h($book['cover_image']) : 'https://placehold.co/280x380?text=' . urlencode($book['title']) ?>" alt="">
      </div>
    </div>
  </div>
  <div style="flex:1; min-width:280px;">
    <h1 style="font-size:28px; font-weight:800; margin-bottom:6px;"><?= h($book['title']) ?></h1>
    <p style="color:var(--gray); margin-bottom:10px;">oleh <?= h($book['author']) ?> &middot; Kategori: <?= h($book['category_name']) ?></p>
    <p style="color:#e0a415; font-weight:700; margin-bottom:16px;">
      ⭐ <?= number_format($ratingInfo['rating'],1) ?>
      <?php if ($ratingInfo['count'] > 0): ?>
        <span style="color:var(--gray); font-weight:500;">(<?= $ratingInfo['count'] ?> ulasan)</span>
      <?php else: ?>
        <span style="color:var(--gray); font-weight:500;">(belum ada ulasan)</span>
      <?php endif; ?>
    </p>
    <p style="font-size:26px; font-weight:800; color:var(--primary); margin-bottom:16px;"><?= rupiah($book['price']) ?></p>
    <p style="color:#444; line-height:1.7; margin-bottom:20px;"><?= nl2br(h($book['description'] ?: 'Tidak ada deskripsi untuk buku ini.')) ?></p>

    <?php $lowStock = $book['stock']>0 && $book['stock']<=5; ?>
    <?php if ($lowStock): ?>
      <p style="margin-bottom:14px; color:#e0245e; font-weight:800; background:#fde3ea; display:inline-block; padding:8px 16px; border-radius:20px;">
        🔥 Buruan! Tinggal <?= $book['stock'] ?> buku lagi
      </p>
    <?php else: ?>
      <p style="margin-bottom:20px; color:<?= $book['stock']>0 ? '#178a4c':'#a51c1c' ?>; font-weight:700;">
        <?= $book['stock']>0 ? "Stok tersedia: {$book['stock']}" : "Stok habis" ?>
      </p>
    <?php endif; ?>

    <?php if ($book['stock']>0): ?>
    <div style="display:flex; gap:12px; align-items:center; margin-bottom:14px;">
      <label style="font-weight:700; font-size:14px;">Jumlah:</label>
      <input type="number" id="qtyInput" value="1" min="1" max="<?= $book['stock'] ?>" style="width:70px; padding:10px; border:1.5px solid #ccc; border-radius:8px;">
    </div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <form method="post" action="<?= BASE_URL ?>/user/buy_now.php" id="buyNowForm" style="flex:1; min-width:180px;">
        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
        <input type="hidden" name="qty" id="buyNowQty" value="1">
        <button type="submit" class="buy-now-btn" style="padding:14px; font-size:15px; border-radius:8px;">⚡ Beli Langsung</button>
      </form>
      <form method="post" action="<?= BASE_URL ?>/user/cart.php" id="cartForm" style="flex:1; min-width:180px;">
        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="qty" id="cartQty" value="1">
        <button type="submit" class="btn btn-outline" style="width:100%;">🛒 Masukkan Keranjang</button>
      </form>
    </div>
    <script>
      document.getElementById('qtyInput').addEventListener('input', function(){
        document.getElementById('buyNowQty').value = this.value;
        document.getElementById('cartQty').value = this.value;
      });
    </script>
    <?php endif; ?>
  </div>
</div>

<!-- ============ ULASAN & RATING ============ -->
<section class="section review-section">
  <h2>Ulasan Pembeli</h2>

  <?php if (is_logged_in()): ?>
    <?php if ($reviewSuccess): ?>
      <div class="alert alert-success">Terima kasih, ulasan kamu berhasil disimpan!</div>
    <?php endif; ?>
    <?php if ($reviewError): ?>
      <div class="alert alert-error"><?= h($reviewError) ?></div>
    <?php endif; ?>
    <form method="post" class="review-form">
      <input type="hidden" name="review_submit" value="1">
      <div class="star-input">
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($myReview && $myReview['rating']==$i)?'checked':'' ?> required>
          <label for="star<?= $i ?>">★</label>
        <?php endfor; ?>
      </div>
      <textarea name="comment" rows="3" placeholder="Ceritain pengalaman kamu baca buku ini... (opsional)"><?= h($myReview['comment'] ?? '') ?></textarea>
      <button type="submit" class="btn btn-primary btn-sm"><?= $myReview ? 'Update Ulasan' : 'Kirim Ulasan' ?></button>
    </form>
  <?php else: ?>
    <p style="color:var(--gray); margin-bottom:20px;"><a href="<?= BASE_URL ?>/user/login.php" style="color:var(--primary); font-weight:700;">Login</a> dulu buat kasih ulasan.</p>
  <?php endif; ?>

  <div class="review-list">
    <?php foreach ($reviews as $r): ?>
      <div class="review-item">
        <div class="review-head">
          <strong><?= h($r['reviewer_name']) ?></strong>
          <span class="review-stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5-$r['rating']) ?></span>
          <span class="review-date"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
        </div>
        <?php if ($r['comment']): ?><p class="review-comment"><?= nl2br(h($r['comment'])) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$reviews): ?>
      <p style="color:#999;">Belum ada ulasan untuk buku ini. Jadi yang pertama! 📚</p>
    <?php endif; ?>
  </div>
</section>

<!-- ============ BUKU TERKAIT ============ -->
<?php if ($relatedBooks): ?>
<section class="section">
  <h2>Buku Terkait</h2>
  <div class="book-grid">
    <?php foreach ($relatedBooks as $rb): $rbWishlisted = is_wishlisted($pdo, $rb['id']); $rbLow = $rb['stock']>0 && $rb['stock']<=5; $rbSoldOut = $rb['stock']<=0; ?>
      <div class="book-card">
        <div class="cover">
          <?php if ($rbLow): ?><span class="ribbon ribbon-hot">🔥 Stok Terbatas!</span><?php endif; ?>
          <?php if (is_logged_in()): ?>
            <a href="<?= BASE_URL ?>/user/wishlist.php?toggle=<?= $rb['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="wishlist-heart <?= $rbWishlisted?'active':'' ?>"><?= $rbWishlisted?'♥':'♡' ?></a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/user/book_detail.php?id=<?= $rb['id'] ?>">
            <img src="<?= $rb['cover_image'] ? UPLOAD_URL . h($rb['cover_image']) : 'https://placehold.co/200x280?text=' . urlencode($rb['title']) ?>" alt="<?= h($rb['title']) ?>">
          </a>
        </div>
        <div class="book-info">
          <a href="<?= BASE_URL ?>/user/book_detail.php?id=<?= $rb['id'] ?>"><h3><?= h($rb['title']) ?></h3></a>
          <div class="author"><?= h($rb['author']) ?></div>
          <div class="price-row">
            <span class="price"><?= rupiah($rb['price']) ?></span>
            <span class="rating">⭐ <?= number_format($rb['rating'],1) ?></span>
          </div>
          <div class="stock-info <?= $rbLow?'urgent':'' ?>">
            <?= $rbSoldOut ? '❌ Stok habis' : ($rbLow ? "⚡ Sisa {$rb['stock']} — buruan!" : "Stok: {$rb['stock']}") ?>
          </div>
          <?php if (!$rbSoldOut): ?>
          <div class="card-btn-row">
            <form method="post" action="<?= BASE_URL ?>/user/buy_now.php">
              <input type="hidden" name="book_id" value="<?= $rb['id'] ?>">
              <input type="hidden" name="qty" value="1">
              <button type="submit" class="buy-now-btn">⚡ Beli Langsung</button>
            </form>
            <form method="post" action="<?= BASE_URL ?>/user/cart.php">
              <input type="hidden" name="book_id" value="<?= $rb['id'] ?>">
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
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/store_footer.php'; ?>
