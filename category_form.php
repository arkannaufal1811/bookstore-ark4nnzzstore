<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
$category = ['name' => ''];
$isEdit = false;

if ($id){
    $isEdit = true;
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category){ header("Location: " . BASE_URL . "/admin/categories.php"); exit; }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    if ($name === ''){
        $error = 'Nama kategori wajib diisi.';
    } else {
        if ($isEdit){
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
        }
        header("Location: " . BASE_URL . "/admin/categories.php?msg=saved");
        exit;
    }
}

$pageTitle = $isEdit ? 'Edit Kategori' : 'Tambah Kategori';
$active = 'categories';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1><?= $isEdit ? 'Edit Kategori' : 'Tambah Kategori' ?></h1>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="admin-form">
  <div class="form-group">
    <label>Nama Kategori</label>
    <input type="text" name="name" value="<?= h($category['name']) ?>" required autofocus>
  </div>
  <button type="submit" class="btn btn-primary">Simpan</button>
  <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-outline">Batal</a>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
