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

$voices = [];
try {
    $voices = $pdo->query("SELECT r.rating, r.comment, u.name AS uname, b.title FROM reviews r JOIN users u ON u.id = r.user_id JOIN books b ON b.id = r.book_id WHERE r.comment IS NOT NULL AND CHAR_LENGTH(TRIM(r.comment)) >= 15 ORDER BY r.rating DESC, r.created_at DESC LIMIT 3")->fetchAll();
} catch (Throwable $e) {}
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
        <?php if ($lowStock): ?><span class="ribbon ribbon-hot">Stok terbatas</span><?php endif; ?>
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
          <span class="rating"><?= number_format($b['rating'],1) ?> ★</span>
        </div>
        <div class="stock-info <?= $lowStock?'urgent':'' ?>">
          <?= $soldOut ? 'Stok habis' : ($lowStock ? "Sisa {$b['stock']}, segera habis" : "Stok: {$b['stock']}") ?><?= sold_label($pdo, $b['id']) ?>
        </div>
        <?php if (!$soldOut): ?>
        <div class="card-btn-row">
          <form method="post" action="<?= BASE_URL ?>/user/buy_now.php">
            <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
            <input type="hidden" name="qty" value="1">
            <button type="submit" class="buy-now-btn">Beli Langsung</button>
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

<?php $heroCovers = array_slice($books, 0, 3); ?>
<section class="shop-hero">
  <div class="shop-hero-in">
    <div>
      <h1>Temukan buku<br><span class="hl">favoritmu</span> di sini</h1>
      <p>Mood-mu lagi apa hari ini? Pengen tenggelam di dunia fantasi, pengen belajar hal baru, atau lagi butuh bacaan yang bikin pikiran lebih tenang? Dari fiksi sampai filsafat, dari self-improvement sampai bisnis, tinggal pilih. Bayar via COD, transfer, atau QRIS, lalu bukunya meluncur ke rumahmu.</p>
      <div class="cta">
        <a href="<?= BASE_URL ?>/user/books.php" class="cta-main">Lihat semua buku</a>
        <a href="<?= BASE_URL ?>/user/join_club.php" class="cta-alt">Gabung Ark4nnzz Club</a>
      </div>
    </div>
    <div class="hero-covers">
      <?php foreach ($heroCovers as $hb): ?>
        <a class="hc" href="<?= BASE_URL ?>/user/book_detail.php?id=<?= $hb['id'] ?>" title="<?= h($hb['title']) ?>">
          <?php if ($hb['cover_image']): ?><img src="<?= UPLOAD_URL . h($hb['cover_image']) ?>" alt="<?= h($hb['title']) ?>"><?php else: ?><?= h($hb['title']) ?><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $totalSold = array_sum(sold_counts($pdo)); ?>
<div class="service-strip">
  <?php if ($totalSold > 0): ?><div><b><?= number_format($totalSold, 0, ',', '.') ?> buku</b> sudah terjual</div><?php endif; ?>
  <div><b>Gratis ongkir</b> min. belanja Rp150.000</div>
  <div><b>COD</b> bayar di tempat</div>
  <div><b>Original</b> buku baru dari penerbit</div>
</div>

<section class="section">
  <div class="sec-head"><h2>Mau baca apa hari ini?</h2><a href="<?= BASE_URL ?>/user/books.php">Semua kategori</a></div>
  <div class="chips">
    <?php foreach ($categories as $c): ?>
      <a href="<?= BASE_URL ?>/user/books.php?category=<?= $c['id'] ?>"><?= h($c['name']) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="sec-head"><h2>Baru masuk rak</h2><a href="<?= BASE_URL ?>/user/books.php">Lihat semua</a></div>
  <p class="sec-sub">Judul-judul terbaru di toko kami, stoknya masih banyak.</p>
  <div class="book-grid">
    <?php foreach ($newestBooks as $b): render_book_card($b); endforeach; ?>
    <?php if (!$newestBooks): ?>
      <p style="color:#999;">Belum ada buku.</p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="sec-head"><h2>Paling disukai pembaca</h2><a href="<?= BASE_URL ?>/user/books.php">Lihat semua</a></div>
  <p class="sec-sub">Diurutkan dari rating tertinggi.</p>
  <div class="book-grid">
    <?php foreach ($books as $b): render_book_card($b); endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="sec-head"><h2>Belanja buku tanpa ribet</h2></div>
  <div class="why">
    <div><h3>Bayar sesuka kamu</h3><p>COD, transfer Bank BSI, atau QRIS dan e-wallet. Pilih yang paling nyaman buatmu.</p></div>
    <div><h3>Pantau pesananmu</h3><p>Lihat status dari diproses sampai selesai, lengkap dengan kurir dan nomor resi di invoice.</p></div>
    <div><h3>Lebih hemat jadi member</h3><p>Diskon 50% dan gratis ongkir tanpa minimal belanja untuk member Ark4nnzz Club.</p></div>
  </div>
</section>

<?php if ($voices): ?>
<section class="section">
  <div class="sec-head"><h2>Kata pembaca</h2></div>
  <div class="voices">
    <?php foreach ($voices as $v): ?>
      <div class="voice">
        <div class="stars"><?= str_repeat('★', (int) $v['rating']) ?></div>
        <?php
          $cm = trim($v['comment']);
          if (strlen($cm) > 140){ $cm = (preg_match('/^.{1,140}/us', $cm, $mm) ? $mm[0] : substr($cm, 0, 140)) . '...'; }
        ?>
        <q><?= h($cm) ?></q>
        <div class="who"><b><?= h($v['uname']) ?></b> tentang <?= h($v['title']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <?php $isMember = is_member_user($pdo); ?>
  <div class="club">
    <div>
      <h2>Baca lebih banyak, bayar separuhnya</h2>
      <?php if ($isMember): ?>
        <p>Kamu sudah jadi member Ark4nnzz Club. Diskon 50% dan gratis ongkir otomatis berlaku di setiap pesananmu.</p>
        <?= membership_countdown(active_membership($pdo), true) ?>
        <div><span class="club-cta done">Sudah bergabung</span></div>
      <?php else: ?>
        <p>Jadi member Ark4nnzz Club dan nikmati diskon 50% untuk semua buku, plus gratis ongkir tanpa minimal belanja.</p>
        <a href="<?= BASE_URL ?><?= is_logged_in() ? '/user/join_club.php' : '/user/register.php' ?>" class="club-cta">Gabung sekarang</a>
      <?php endif; ?>
    </div>
    <ul>
      <li><svg viewBox="0 0 24 24"><path d="M5 12.500l4.500 4.500L19 7.500"/></svg> Diskon 50% di semua buku</li>
      <li><svg viewBox="0 0 24 24"><path d="M5 12.500l4.500 4.500L19 7.500"/></svg> Gratis ongkir tanpa minimal belanja</li>
      <li><svg viewBox="0 0 24 24"><path d="M5 12.500l4.500 4.500L19 7.500"/></svg> Poin reward dari setiap pembelian</li>
      <li><svg viewBox="0 0 24 24"><path d="M5 12.500l4.500 4.500L19 7.500"/></svg> Akses early sale untuk buku pilihan</li>
    </ul>
  </div>
</section>

<?php require __DIR__ . '/../includes/store_footer.php'; ?>
