<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('/user/login.php');

$active = active_membership($pdo);
$pending = pending_membership($pdo);
$plans = membership_plans();

$pageTitle = 'Ark4nnzz Club';
require __DIR__ . '/../includes/store_header.php';
?>
<div class="section" style="max-width:1000px; margin:0 auto;">
  <div style="text-align:center; margin-bottom:32px;">
    <h1 style="font-family:'Fraunces', Georgia, serif; font-size:32px; font-weight:600; margin-bottom:10px;">👑 Ark4nnzz Club</h1>
    <p style="color:var(--gray);">Diskon 50% tiap belanja, gratis ongkir selamanya, dan poin reward setiap transaksi.</p>
  </div>

  <?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success" style="text-align:center;">✅ Bukti transfer berhasil dikirim! Menunggu verifikasi admin.</div>
  <?php endif; ?>

  <?php if ($active): ?>
    <div class="alert alert-success" style="text-align:center; font-size:15px;">
      👑 Kamu member <strong><?= h(ucfirst($active['plan'])) ?></strong> — aktif sampai
      <strong><?= date('d F Y', strtotime($active['expires_at'])) ?></strong>.
      Poin kamu sekarang: <strong><?= user_points($pdo) ?></strong>
      <div style="margin-top:12px;"><?= membership_countdown($active, false) ?></div>
    </div>
  <?php elseif ($pending): ?>
    <div class="alert" style="background:var(--warn-bg); color:var(--warn-tx); text-align:center; font-size:15px;">
      ⏳ Pembayaran paket <strong><?= h(ucfirst($pending['plan'])) ?></strong> (<?= rupiah($pending['price']) ?>) kamu lagi
      <strong>menunggu verifikasi admin</strong>. Biasanya diproses dalam 1x24 jam.
    </div>
  <?php else: ?>
    <div class="plan-grid">
      <?php foreach ($plans as $key => $p): ?>
        <div class="plan-card <?= !empty($p['popular']) ? 'popular' : '' ?>">
          <?php if (!empty($p['popular'])): ?><div class="plan-badge">Paling Populer</div><?php endif; ?>
          <div class="plan-label"><?= h($p['label']) ?></div>
          <div class="plan-price"><?= rupiah($p['price']) ?></div>
          <div class="plan-period">/ <?= $p['days'] ?> hari</div>
          <div class="plan-desc"><?= h($p['desc']) ?></div>
          <ul class="plan-perks">
            <li>✔️ Diskon 50% semua buku</li>
            <li>✔️ Gratis ongkir selamanya</li>
            <li>✔️ Poin reward tiap belanja</li>
            <li>✔️ Akses early sale</li>
          </ul>
          <a href="<?= BASE_URL ?>/user/join_club_payment.php?plan=<?= $key ?>" class="btn btn-primary" style="width:100%; text-align:center;">Pilih Paket</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/store_footer.php'; ?>
