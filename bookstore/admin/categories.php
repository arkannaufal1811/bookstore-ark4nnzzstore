<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (isset($_GET['delete'])){
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . BASE_URL . "/admin/categories.php?msg=deleted");
    exit;
}

$categories = $pdo->query("
    SELECT c.id, c.name, COUNT(b.id) AS jumlah_buku
    FROM categories c LEFT JOIN books b ON b.category_id = c.id
    GROUP BY c.id ORDER BY c.id ASC
")->fetchAll();

$pageTitle = 'Data Kategori';
$active = 'categories';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Data Kategori</h1>
  <a href="<?= BASE_URL ?>/admin/category_form.php" class="btn btn-primary">+ Tambah Kategori</a>
</div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">Data kategori berhasil diperbarui.</div>
<?php endif; ?>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>No.</th><th>Nama Kategori</th><th>Jumlah Buku</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $i => $c): ?>
      <tr>
        <td><?= $i+1 ?>.</td>
        <td><?= h($c['name']) ?></td>
        <td><?= $c['jumlah_buku'] ?></td>
        <td>
          <div class="action-icons">
            <a class="edit" href="<?= BASE_URL ?>/admin/category_form.php?id=<?= $c['id'] ?>">✏️</a>
            <a class="del" href="<?= BASE_URL ?>/admin/categories.php?delete=<?= $c['id'] ?>" onclick="return confirm('Hapus kategori ini?');">🗑️</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$categories): ?>
      <tr><td colspan="4" style="text-align:center; color:#999;">Belum ada kategori</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
