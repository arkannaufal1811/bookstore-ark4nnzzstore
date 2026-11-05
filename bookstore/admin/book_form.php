<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
$book = ['title'=>'','author'=>'','category_id'=>'','price'=>'','stock'=>'','description'=>'','cover_image'=>'','rating'=>0];
$isEdit = false;

if ($id){
    $isEdit = true;
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    if (!$book){ header("Location: " . BASE_URL . "/admin/books.php"); exit; }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $rating = $_POST['rating'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    $coverName = $book['cover_image'];

    if ($title === '' || $category_id === ''){
        $error = 'Judul dan kategori wajib diisi.';
    } else {
        if (!empty($_FILES['cover']['name'])){
            $ext = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
            $coverName = uniqid('book_') . '.' . $ext;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
            move_uploaded_file($_FILES['cover']['tmp_name'], UPLOAD_DIR . $coverName);
        }

        if ($isEdit){
            $stmt = $pdo->prepare("UPDATE books SET title=?, author=?, category_id=?, price=?, stock=?, rating=?, description=?, cover_image=? WHERE id=?");
            $stmt->execute([$title, $author, $category_id, $price, $stock, $rating, $description, $coverName, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO books (title, author, category_id, price, stock, rating, description, cover_image) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$title, $author, $category_id, $price, $stock, $rating, $description, $coverName]);
        }
        header("Location: " . BASE_URL . "/admin/books.php?msg=saved");
        exit;
    }
}

$pageTitle = $isEdit ? 'Edit Buku' : 'Tambah Buku';
$active = 'books';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1><?= $isEdit ? 'Edit Buku' : 'Tambah Buku' ?></h1></div>
<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="admin-form" enctype="multipart/form-data">
  <div class="form-group">
    <label>Judul Buku</label>
    <input type="text" name="title" value="<?= h($book['title']) ?>" required>
  </div>
  <div class="form-group">
    <label>Penulis</label>
    <input type="text" name="author" value="<?= h($book['author']) ?>">
  </div>
  <div class="form-group">
    <label>Kategori</label>
    <select name="category_id" required>
      <option value="">-- Pilih Kategori --</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $book['category_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Harga (Rp)</label>
    <input type="number" name="price" value="<?= h($book['price']) ?>" min="0" required>
  </div>
  <div class="form-group">
    <label>Stok</label>
    <input type="number" name="stock" value="<?= h($book['stock']) ?>" min="0" required>
  </div>
  <div class="form-group">
    <label>Rating Awal (⭐ 0 - 5)</label>
    <input type="number" name="rating" value="<?= h($book['rating']) ?>" min="0" max="5" step="0.1">
    <p style="font-size:12px; color:var(--gray); margin-top:4px;">Rating ini cuma tampil selama belum ada ulasan asli dari pembeli. Begitu ada yang kasih ulasan, rating otomatis dihitung dari rata-rata ulasan.</p>
  </div>
  <div class="form-group">
    <label>Deskripsi</label>
    <textarea name="description" rows="4"><?= h($book['description']) ?></textarea>
  </div>
  <div class="form-group">
    <label>Cover Buku</label>
    <input type="file" name="cover" accept="image/*">
    <?php if ($book['cover_image']): ?>
      <div style="margin-top:8px;"><img src="<?= UPLOAD_URL . h($book['cover_image']) ?>" style="width:80px; border-radius:6px;"></div>
    <?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary">Simpan</button>
  <a href="<?= BASE_URL ?>/admin/books.php" class="btn btn-outline">Batal</a>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
