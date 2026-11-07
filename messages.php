<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (isset($_GET['read'])){
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
    $stmt->execute([$_GET['read']]);
    header("Location: " . BASE_URL . "/admin/messages.php");
    exit;
}
if (isset($_GET['delete'])){
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: " . BASE_URL . "/admin/messages.php");
    exit;
}

$messages = $pdo->query("SELECT * FROM messages ORDER BY is_read ASC, created_at DESC")->fetchAll();
$unreadCount = 0;
foreach ($messages as $m){ if (!$m['is_read']) $unreadCount++; }

$pageTitle = 'Pesan Masuk';
$active = 'messages';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar">
  <h1>Pesan Masuk <?php if ($unreadCount>0): ?><span class="badge badge-diproses"><?= $unreadCount ?> belum dibaca</span><?php endif; ?></h1>
</div>

<div class="panel">
  <table class="data-table">
    <thead><tr><th>Nama</th><th>Email</th><th>Pesan</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($messages as $m): ?>
      <tr style="<?= $m['is_read'] ? '' : 'background:#fffaf0;' ?>">
        <td><?= h($m['name']) ?></td>
        <td><?= h($m['email']) ?></td>
        <td style="max-width:320px;"><?= nl2br(h($m['message'])) ?></td>
        <td><?= date('d M Y H:i', strtotime($m['created_at'])) ?></td>
        <td><span class="badge <?= $m['is_read']?'badge-selesai':'badge-diproses' ?>"><?= $m['is_read']?'Dibaca':'Baru' ?></span></td>
        <td>
          <div class="action-icons">
            <?php if (!$m['is_read']): ?>
              <a class="edit" href="?read=<?= $m['id'] ?>" title="Tandai dibaca">✔️</a>
            <?php endif; ?>
            <a class="del" href="?delete=<?= $m['id'] ?>" onclick="return confirm('Hapus pesan ini?');" title="Hapus">🗑️</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$messages): ?>
      <tr><td colspan="6" style="text-align:center; color:#999;">Belum ada pesan masuk</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
