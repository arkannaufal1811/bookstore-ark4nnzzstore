<?php
require_once __DIR__ . '/../includes/auth.php';

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/user/books.php"); exit; }

$stmt = $pdo->prepare("SELECT b.*, c.name AS category_name FROM books b JOIN categories c ON c.id=b.category_id WHERE b.id = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();
if (!$book){ header("Location: " . BASE_URL . "/user/books.php"); exit; }

$pageTitle = $book['title'];
$active = 'books';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="section" style="display:flex; gap:40px; flex-wrap:wrap;">
  <div style="width:280px; flex-shrink:0;">
    <div class="book-card" style="border-radius:10px;">
      <div class="cover">
        <img src="<?= $book['cover_image'] ? UPLOAD_URL . h($book['cover_image']) : 'https://placehold.co/280x380?text=' . urlencode($book['title']) ?>" alt="">
      </div>
    </div>
  </div>
  <div style="flex:1; min-width:280px;">
    <h1 style="font-size:28px; font-weight:800; margin-bottom:6px;"><?= h($book['title']) ?></h1>
    <p style="color:var(--gray); margin-bottom:10px;">oleh <?= h($book['author']) ?> &middot; Kategori: <?= h($book['category_name']) ?></p>
    <p style="color:#e0a415; font-weight:700; margin-bottom:16px;">⭐ <?= number_format($book['rating'],1) ?></p>
    <p style="font-size:26px; font-weight:800; color:var(--primary); margin-bottom:16px;"><?= rupiah($book['price']) ?></p>
    <p style="color:#444; line-height:1.7; margin-bottom:20px;"><?= nl2br(h($book['description'] ?: 'Tidak ada deskripsi untuk buku ini.')) ?></p>
    <p style="margin-bottom:20px; color:<?= $book['stock']>0 ? '#178a4c':'#a51c1c' ?>; font-weight:700;">
      <?= $book['stock']>0 ? "Stok tersedia: {$book['stock']}" : "Stok habis" ?>
    </p>
    <?php if ($book['stock']>0): ?>
    <form method="post" action="<?= BASE_URL ?>/user/cart.php" style="display:flex; gap:10px; align-items:center;">
      <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
      <input type="hidden" name="action" value="add">
      <input type="number" name="qty" value="1" min="1" max="<?= $book['stock'] ?>" style="width:70px; padding:10px; border:1.5px solid #ccc; border-radius:8px;">
      <button type="submit" class="btn btn-primary">🛒 Add to Cart</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
