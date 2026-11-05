<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (isset($_GET['delete'])){
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . BASE_URL . "/admin/books.php?msg=deleted");
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$totalBooks = (int) $pdo->query("SELECT COUNT(*) c FROM books")->fetch()['c'];
$totalPages = max(1, (int) ceil($totalBooks / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$books = $pdo->query("
    SELECT b.*, c.name AS category_name
    FROM books b JOIN categories c ON c.id = b.category_id
    ORDER BY b.id ASC LIMIT $perPage OFFSET $offset
")->fetchAll();

$pageTitle = 'Data Buku';
$active = 'books';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Data Buku</h1>
  <a href="<?= BASE_URL ?>/admin/book_form.php" class="btn btn-primary">+ Tambah Buku</a>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">Data buku berhasil diperbarui.</div>
<?php endif; ?>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>No.</th><th>Cover</th><th>Judul</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($books as $i => $b): ?>
      <tr>
        <td><?= $offset + $i + 1 ?>.</td>
        <td class="tbl-cover">
          <img src="<?= $b['cover_image'] ? UPLOAD_URL . h($b['cover_image']) : BASE_URL.'/assets/images/no-cover.png' ?>" alt="">
        </td>
        <td><?= h($b['title']) ?></td>
        <td><?= h($b['category_name']) ?></td>
        <td><?= rupiah($b['price']) ?></td>
        <td><?= $b['stock'] ?></td>
        <td>
          <div class="action-icons">
            <a class="edit" href="<?= BASE_URL ?>/admin/book_form.php?id=<?= $b['id'] ?>">✏️</a>
            <a class="del" href="<?= BASE_URL ?>/admin/books.php?delete=<?= $b['id'] ?>" onclick="return confirm('Hapus buku ini?');">🗑️</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$books): ?>
      <tr><td colspan="7" style="text-align:center; color:#999;">Belum ada buku</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>" class="page-btn">‹ Sebelumnya</a><?php endif; ?>
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>" class="page-btn">Selanjutnya ›</a><?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
