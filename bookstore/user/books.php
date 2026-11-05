<?php
require_once __DIR__ . '/../includes/auth.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$q = trim($_GET['q'] ?? '');
$categoryId = $_GET['category'] ?? '';
$minPrice = $_GET['min_price'] ?? 0;
$maxPrice = $_GET['max_price'] ?? 300000;
$minRating = $_GET['rating'] ?? 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;

$sql = "SELECT b.*, c.name AS category_name FROM books b JOIN categories c ON c.id = b.category_id WHERE 1=1";
$countSql = "SELECT COUNT(*) c FROM books b JOIN categories c ON c.id = b.category_id WHERE 1=1";
$params = [];

if ($q !== ''){
    $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR c.name LIKE ?)";
    $countSql .= " AND (b.title LIKE ? OR b.author LIKE ? OR c.name LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($categoryId !== ''){
    $sql .= " AND b.category_id = ?";
    $countSql .= " AND b.category_id = ?";
    $params[] = $categoryId;
}
$sql .= " AND b.price BETWEEN ? AND ?";
$countSql .= " AND b.price BETWEEN ? AND ?";
$params[] = $minPrice; $params[] = $maxPrice;

if ($minRating > 0){
    $sql .= " AND b.rating >= ?";
    $countSql .= " AND b.rating >= ?";
    $params[] = $minRating;
}

// Hitung total hasil dulu (buat pagination), sebelum LIMIT ditambahkan
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$filteredCount = (int) $stmt->fetch()['c'];
$totalPages = max(1, (int) ceil($filteredCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql .= " ORDER BY b.id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$totalBooksCount = $pdo->query("SELECT COUNT(*) c FROM books")->fetch()['c'];

// Buat query string filter tanpa "page", supaya link pagination bisa nambahin page=N sendiri
$filterQuery = $_GET;
unset($filterQuery['page']);
$filterQueryStr = http_build_query($filterQuery);

function wishlist_btn_books($pdo, $b, $redirectQS){
    $active = is_wishlisted($pdo, $b['id']) ? 'active' : '';
    $redirect = urlencode(BASE_URL . '/user/books.php?' . $redirectQS);
    if (!is_logged_in()){
        return '<a href="' . BASE_URL . '/user/login.php" class="wishlist-heart" title="Login untuk simpan ke wishlist">♡</a>';
    }
    return '<a href="' . BASE_URL . '/user/wishlist.php?toggle=' . $b['id'] . '&redirect=' . $redirect . '" class="wishlist-heart ' . $active . '" title="Simpan ke wishlist">♥</a>';
}

$pageTitle = 'Books';
$active = 'books';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="listing-layout">
  <form class="filters" method="get">
    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
    <h3>Kategori</h3>
    <ul>
      <li class="<?= $categoryId==='' ? 'active':'' ?>"><a href="?<?= $q?'q='.urlencode($q).'&':'' ?>">Semua Kategori</a></li>
      <?php foreach ($categories as $c): ?>
        <li class="<?= $categoryId==$c['id'] ? 'active':'' ?>">
          <a href="?category=<?= $c['id'] ?><?= $q?'&q='.urlencode($q):'' ?>"><?= h($c['name']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <h3 style="margin-top:26px;">Filter Harga</h3>
    <input type="hidden" name="category" value="<?= h($categoryId) ?>">
    <div class="form-group">
      <input type="number" name="min_price" value="<?= h($minPrice) ?>" placeholder="Min" style="width:48%; padding:6px; border:1px solid #ccc; border-radius:6px;">
      <input type="number" name="max_price" value="<?= h($maxPrice) ?>" placeholder="Max" style="width:48%; padding:6px; border:1px solid #ccc; border-radius:6px;">
    </div>
    <div class="range-labels"><span>Rp 0</span><span>Rp 300.000</span></div>

    <h3 style="margin-top:26px;">Rating</h3>
    <?php foreach ([5,4,3,2,1] as $r): ?>
      <label class="rating-opt">
        <input type="radio" name="rating" value="<?= $r ?>" <?= $minRating==$r?'checked':'' ?>>
        <?= str_repeat('⭐', $r) ?>
      </label>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:16px; width:100%;">Terapkan Filter</button>
  </form>

  <div class="listing-main">
    <h2>Semua Buku</h2>
    <p class="result-count">Menampilkan <?= count($books) ?> dari <?= $filteredCount ?> buku (total koleksi: <?= $totalBooksCount ?>)</p>
    <div class="book-grid">
      <?php foreach ($books as $b): $lowStock = $b['stock']>0 && $b['stock']<=5; $soldOut = $b['stock']<=0; ?>
        <div class="book-card">
          <div class="cover">
            <?php if ($lowStock): ?><span class="ribbon ribbon-hot">🔥 Stok Terbatas!</span><?php endif; ?>
            <?= wishlist_btn_books($pdo, $b, $filterQueryStr) ?>
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
      <?php if (!$books): ?>
        <p style="color:#999;">Tidak ada buku yang cocok dengan pencarian/filter kamu.</p>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= $filterQueryStr ?>&page=<?= $page-1 ?>" class="page-btn">‹ Sebelumnya</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?<?= $filterQueryStr ?>&page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= $filterQueryStr ?>&page=<?= $page+1 ?>" class="page-btn">Selanjutnya ›</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
