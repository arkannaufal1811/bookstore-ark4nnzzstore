<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (isset($_GET['delete'])){
    $id = (int) $_GET['delete'];
    if ($id != $_SESSION['user_id']){ // admin tidak bisa hapus akunnya sendiri
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: " . BASE_URL . "/admin/users.php?msg=deleted");
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY (role='admin') DESC, id ASC")->fetchAll();

$pageTitle = 'Data User';
$active = 'users';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Data User</h1></div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">Data user berhasil diperbarui.</div>
<?php endif; ?>

<div class="panel">
  <table class="data-table">
    <thead>
      <tr><th>No.</th><th>Nama</th><th>Email</th><th>Role</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $i => $u): ?>
      <tr>
        <td><?= $i+1 ?>.</td>
        <td><?= h($u['name']) ?></td>
        <td><?= h($u['email']) ?></td>
        <td><?= h(ucfirst($u['role'])) ?></td>
        <td>
          <div class="action-icons">
            <a class="edit" href="<?= BASE_URL ?>/admin/user_form.php?id=<?= $u['id'] ?>">✏️</a>
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <a class="del" href="<?= BASE_URL ?>/admin/users.php?delete=<?= $u['id'] ?>" onclick="return confirm('Hapus user ini?');">🗑️</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
