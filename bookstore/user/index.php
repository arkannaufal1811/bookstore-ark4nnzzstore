<?php
require_once __DIR__ . '/../includes/auth.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name LIMIT 6")->fetchAll();
$books = $pdo->query("
    SELECT b.*, c.name AS category_name FROM books b
    JOIN categories c ON c.id = b.category_id
    ORDER BY b.rating DESC LIMIT 8
")->fetchAll();
$newestBooks = $pdo->query("
    SELECT b.*, c.name AS category_name FROM books b
    JOIN categories c ON c.id = b.category_id
    ORDER BY b.created_at DESC, b.id DESC LIMIT 4
")->fetchAll();

$catIcons = ['Fiksi'=>'📖','Novel'=>'📖','Pengembangan Diri'=>'💡','Bisnis'=>'💼','Teknologi'=>'💻','Komputer'=>'💻','Sejarah'=>'🌍','Anak-anak'=>'🧒','Filsafat'=>'🦉','Pelajaran'=>'🏫','Sastra Indonesia'=>'✍️'];

function wishlist_btn($pdo, $b){
    $active = is_wishlisted($pdo, $b['id']) ? 'active' : '';
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    if (!is_logged_in()){
        return '<a href="' . BASE_URL . '/user/login.php" class="wishlist-heart" title="Login untuk simpan ke wishlist">♡</a>';
    }
    return '<a href="' . BASE_URL . '/user/wishlist.php?toggle=' . $b['id'] . '&redirect=' . $redirect . '" class="wishlist-heart ' . $active . '" title="Simpan ke wishlist">♥</a>';
}

function render_book_card($b){
    global $pdo;
    $lowStock = $b['stock'] > 0 && $b['stock'] <= 5;
    $soldOut = $b['stock'] <= 0;
    ?>
    <div class="book-card">
      <div class="cover">
        <?php if ($lowStock): ?><span class="ribbon ribbon-hot">🔥 Stok Terbatas!</span><?php endif; ?>
        <?= wishlist_btn($pdo, $b) ?>
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
    <?php
}

$pageTitle = 'Home';
$active = 'home';
require __DIR__ . '/../includes/store_header.php';
?>

<section class="hero">
  <div class="hero-text">
    <h1>Temukan Buku<span>Favorite Anda</span></h1>
    <p>Ribuan buku pilihan dengan berbagai genre untuk menemani setiap momen berharga.</p>
    <div class="hero-actions">
      <a href="<?= BASE_URL ?>/user/books.php" class="btn btn-primary">Belanja Sekarang</a>
      <a href="<?= BASE_URL ?>/user/books.php" class="btn btn-outline">Lihat Koleksi</a>
    </div>
    <div class="hero-perks">
      <div>🚚 Gratis Ongkir <small>&nbsp;Min. belanja 150k</small></div>
      <div>💵 Pembayaran COD <small>&nbsp;Bayar di tempat</small></div>
      <div>🏅 100% Original <small>&nbsp;Buku Original Dan Baru</small></div>
    </div>
  </div>
  <div class="discount-badge">Diskon<br>Up To<b>30%</b></div>
</section>

<section class="section">
  <h2>Kategori Populer</h2>
  <div class="cat-popular">
    <?php foreach ($categories as $c): ?>
      <a href="<?= BASE_URL ?>/user/books.php?category=<?= $c['id'] ?>" class="cat-item">
        <div class="icon"><?= $catIcons[$c['name']] ?? '📚' ?></div>
        <?= h($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <h2>🆕 Buku Terbaru</h2>
  <div class="book-grid">
    <?php foreach ($newestBooks as $b): render_book_card($b); endforeach; ?>
    <?php if (!$newestBooks): ?>
      <p style="color:#999;">Belum ada buku.</p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <h2>Rekomendasi Untukmu</h2>
  <div class="book-grid">
    <?php foreach ($books as $b): render_book_card($b); endforeach; ?>
  </div>
</section>

<section class="section">
  <?php $isMember = is_member_user($pdo); ?>
  <div class="club-banner">
    <?php if ($isMember): ?>
      <div class="member-badge">✓ Kamu Member Ark4nnzz Club</div>
      <div><?= membership_countdown(active_membership($pdo), true) ?></div>
    <?php endif; ?>
    <div class="club-banner-top">
      <h3>👑 Gabung Ark4nnzz Club</h3>
      <?php if ($isMember): ?>
        <span class="btn" style="background:#fff; color:var(--primary); cursor:default;">✓ Sudah Bergabung</span>
      <?php elseif (is_logged_in()): ?>
        <a href="<?= BASE_URL ?>/user/join_club.php" class="btn" style="background:#fff; color:var(--primary);">Gabung Sekarang</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/user/register.php" class="btn" style="background:#fff; color:var(--primary);">Gabung Sekarang</a>
      <?php endif; ?>
    </div>
    <div class="club-perks">
      <div><div class="p-icon">🏷️</div>50% Diskon Spesial Untuk Member</div>
      <div><div class="p-icon">🥇</div>Akses Early Sale Produk Pilihan</div>
      <div><div class="p-icon">⭐</div>Poin Reward Setiap Pembelian</div>
      <div><div class="p-icon">🚚</div>Gratis Ongkir Setiap Saat</div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/store_footer.php'; ?>
