<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = $_GET['id'] ?? null;
if (!$id){ header("Location: " . BASE_URL . "/admin/users.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user){ header("Location: " . BASE_URL . "/admin/users.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if ($name === '' || $email === ''){
        $error = 'Nama dan email wajib diisi.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $id]);
        header("Location: " . BASE_URL . "/admin/users.php?msg=saved");
        exit;
    }
}

$pageTitle = 'Edit User';
$active = 'users';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Edit User</h1></div>
<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="admin-form">
  <div class="form-group">
    <label>Nama</label>
    <input type="text" name="name" value="<?= h($user['name']) ?>" required>
  </div>
  <div class="form-group">
    <label>Email</label>
    <input type="email" name="email" value="<?= h($user['email']) ?>" required>
  </div>
  <div class="form-group">
    <label>Role</label>
    <select name="role">
      <option value="user" <?= $user['role']==='user'?'selected':'' ?>>User</option>
      <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Simpan</button>
  <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline">Batal</a>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
