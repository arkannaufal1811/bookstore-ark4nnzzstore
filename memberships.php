<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$ready = membership_ready($pdo);

// ===== Filter status =====
$tabs = ['' => 'Semua', 'pending' => 'Menunggu', 'active' => 'Aktif', 'expired' => 'Kedaluwarsa', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
$filter = $_GET['status'] ?? '';
if (!isset($tabs[$filter])) $filter = '';

$where = '';
if ($filter === 'active')        $where = "WHERE m.status = 'active' AND m.expires_at > NOW()";
elseif ($filter === 'expired')   $where = "WHERE (m.status = 'expired' OR (m.status = 'active' AND m.expires_at <= NOW()))";
elseif ($filter !== '')          $where = "WHERE m.status = " . $pdo->quote($filter);

// ===== Laporan ringkas =====
// Pendapatan = paket yang pernah disetujui (aktif / kedaluwarsa). Yang ditolak atau dibatalkan tidak dihitung.
$st = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(status = 'pending') AS pending,
           SUM(status = 'active' AND expires_at > NOW()) AS active_now,
           SUM(status = 'expired' OR (status = 'active' AND expires_at <= NOW())) AS expired,
           SUM(status = 'rejected') AS rejected,
           SUM(status = 'cancelled') AS cancelled,
           COUNT(DISTINCT CASE WHEN status = 'active' AND expires_at > NOW() THEN user_id END) AS active_users,
           COALESCE(SUM(CASE WHEN status IN ('active','expired') THEN price END), 0) AS revenue
    FROM memberships
")->fetch();
$cnt = [
    ''          => (int) $st['total'],
    'pending'   => (int) $st['pending'],
    'active'    => (int) $st['active_now'],
    'expired'   => (int) $st['expired'],
    'rejected'  => (int) $st['rejected'],
    'cancelled' => (int) $st['cancelled'],
];

$perPlan = [];
foreach (membership_plans() as $key => $p) $perPlan[$key] = ['label' => $p['label'], 'jumlah' => 0, 'omzet' => 0];
foreach ($pdo->query("SELECT plan, COUNT(*) AS jumlah, SUM(price) AS omzet FROM memberships WHERE status IN ('active','expired') GROUP BY plan")->fetchAll() as $r){
    if (isset($perPlan[$r['plan']])){ $perPlan[$r['plan']]['jumlah'] = (int) $r['jumlah']; $perPlan[$r['plan']]['omzet'] = (float) $r['omzet']; }
}

$memberships = $pdo->query("
    SELECT m.*, u.name AS user_name, u.email FROM memberships m
    JOIN users u ON u.id = m.user_id
    $where
    ORDER BY (m.status = 'pending') DESC, m.created_at DESC
")->fetchAll();

$pageTitle = 'Membership';
$active = 'memberships';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-topbar"><h1>Ark4nnzz Club Membership</h1></div>

<?php $msg = $_GET['msg'] ?? ''; ?>
<?php if ($msg === 'approved'): ?><div class="alert alert-success">Membership diaktifkan.</div>
<?php elseif ($msg === 'rejected'): ?><div class="alert alert-success">Pendaftaran membership ditolak. User akan melihat alasannya.</div>
<?php elseif ($msg === 'cancelled'): ?><div class="alert alert-success">Membership dibatalkan. Diskon &amp; gratis ongkir user sudah tidak berlaku.</div>
<?php endif; ?>
<?php if (!$ready): ?><div class="alert alert-error">⚠️ Jalankan <code>database/migration_v11.sql</code> untuk mengaktifkan pembatalan membership dan catatan admin.</div><?php endif; ?>

<div class="stat-cards">
  <div class="stat-card pendapatan"><div class="lbl">Pendapatan Membership</div><div class="val" style="font-size:24px;"><?= rupiah($st['revenue']) ?></div></div>
  <div class="stat-card pesanan"><div class="lbl">Member Aktif</div><div class="val"><?= (int) $st['active_users'] ?></div></div>
  <div class="stat-card user"><div class="lbl">Menunggu Verifikasi</div><div class="val"><?= $cnt['pending'] ?></div></div>
  <div class="stat-card buku"><div class="lbl">Ditolak / Dibatalkan</div><div class="val"><?= $cnt['rejected'] + $cnt['cancelled'] ?></div></div>
</div>

<div class="panel" style="margin-bottom:24px;">
  <table class="data-table">
    <thead><tr><th>Paket</th><th>Terjual</th><th>Pendapatan</th></tr></thead>
    <tbody>
      <?php foreach ($perPlan as $pp): ?>
      <tr><td><?= h($pp['label']) ?></td><td><?= $pp['jumlah'] ?>x</td><td><?= rupiah($pp['omzet']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="?<?= $key !== '' ? 'status=' . h($key) : '' ?>" class="btn btn-sm <?= $filter === $key ? 'btn-primary' : 'btn-outline' ?>"><?= h($label) ?> (<?= $cnt[$key] ?>)</a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <table class="data-table">
    <thead><tr><th>User</th><th>Paket</th><th>Harga</th><th>Bukti</th><th>Status</th><th>Berlaku Sampai</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($memberships as $m): $eff = membership_effective_status($m); ?>
      <tr>
        <td><?= h($m['user_name']) ?><br><span style="font-size:12px; color:var(--gray);"><?= h($m['email']) ?></span></td>
        <td><?= h(ucfirst($m['plan'])) ?></td>
        <td><?= rupiah($m['price']) ?></td>
        <td>
          <?php if ($m['payment_proof']): ?>
            <?php if (upload_ensure($pdo, $m['payment_proof'])): ?>
              <a href="<?= UPLOAD_URL . h(basename($m['payment_proof'])) ?>" target="_blank" title="Klik untuk lihat ukuran penuh">
                <img src="<?= UPLOAD_URL . h(basename($m['payment_proof'])) ?>" alt="Bukti" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #ccc;">
              </a>
            <?php else: ?>
              <span class="badge badge-diproses" title="File tidak ditemukan di folder uploads">File hilang</span>
            <?php endif; ?>
          <?php else: ?>-<?php endif; ?>
        </td>
        <td><span class="badge <?= membership_badge_class($eff) ?>"><?= h(membership_status_label($eff)) ?></span></td>
        <td><?= $m['expires_at'] ? date('d M Y', strtotime($m['expires_at'])) : '-' ?></td>
        <td>
          <div class="action-icons">
            <a class="edit" href="<?= BASE_URL ?>/admin/membership_detail.php?id=<?= (int) $m['id'] ?>" title="Lihat detail<?= $m['status'] === 'pending' ? ' &amp; verifikasi' : '' ?>">🧾</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$memberships): ?>
      <tr><td colspan="7" style="text-align:center; color:#999;">Belum ada pendaftaran membership<?= $filter !== '' ? ' dengan status ini' : '' ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
