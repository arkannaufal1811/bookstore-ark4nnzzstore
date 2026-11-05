<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$planDays = ['mingguan' => 7, 'bulanan' => 30, 'tahunan' => 365];

if (isset($_GET['approve'])){
    $id = (int) $_GET['approve'];
    $stmt = $pdo->prepare("SELECT * FROM memberships WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if ($m && $m['status'] === 'pending'){
        $days = $planDays[$m['plan']] ?? 30;
        $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $pdo->prepare("UPDATE memberships SET status='active', started_at=NOW(), expires_at=? WHERE id=?")
            ->execute([$expires, $id]);
        $pdo->prepare("UPDATE users SET is_member = 1 WHERE id = ?")->execute([$m['user_id']]);
    }
    header("Location: " . BASE_URL . "/admin/memberships.php?msg=approved");
    exit;
}
if (isset($_GET['reject'])){
    $stmt = $pdo->prepare("UPDATE memberships SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$_GET['reject']]);
    header("Location: " . BASE_URL . "/admin/memberships.php?msg=rejected");
    exit;
}

$memberships = $pdo->query("
    SELECT m.*, u.name AS user_name, u.email FROM memberships m
    JOIN users u ON u.id = m.user_id
    ORDER BY (m.status='pending') DESC, m.created_at DESC
")->fetchAll();

function membershipBadge($status){
    return match($status){
        'pending' => 'badge-diproses',
        'active' => 'badge-selesai',
        'rejected' => 'badge-diproses',
        'expired' => 'badge-dikirim',
        default => ''
    };
}

$pageTitle = 'Membership';
$active = 'memberships';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Ark4nnzz Club Membership</h1></div>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success">Status membership berhasil diperbarui.</div>
<?php endif; ?>

<div class="panel">
  <table class="data-table">
    <thead><tr><th>User</th><th>Paket</th><th>Harga</th><th>Bukti</th><th>Status</th><th>Berlaku Sampai</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($memberships as $m): ?>
      <tr>
        <td><?= h($m['user_name']) ?><br><span style="font-size:12px; color:var(--gray);"><?= h($m['email']) ?></span></td>
        <td><?= h(ucfirst($m['plan'])) ?></td>
        <td><?= rupiah($m['price']) ?></td>
        <td>
          <?php if ($m['payment_proof']): upload_ensure($pdo, $m['payment_proof']); ?>
            <a href="<?= UPLOAD_URL . h($m['payment_proof']) ?>" target="_blank">
              <img src="<?= UPLOAD_URL . h($m['payment_proof']) ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #ccc;">
            </a>
          <?php else: ?>-<?php endif; ?>
        </td>
        <td><span class="badge <?= membershipBadge($m['status']) ?>"><?= h(ucfirst($m['status'])) ?></span></td>
        <td><?= $m['expires_at'] ? date('d M Y', strtotime($m['expires_at'])) : '-' ?></td>
        <td>
          <?php if ($m['status'] === 'pending'): ?>
            <div class="action-icons">
              <a class="edit" href="?approve=<?= $m['id'] ?>" title="Setujui" onclick="return confirm('Aktifkan membership user ini?');">✅</a>
              <a class="del" href="?reject=<?= $m['id'] ?>" title="Tolak" onclick="return confirm('Tolak pembayaran ini?');">❌</a>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$memberships): ?>
      <tr><td colspan="7" style="text-align:center; color:#999;">Belum ada pendaftaran membership</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
